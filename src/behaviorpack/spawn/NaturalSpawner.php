<?php

declare(strict_types=1);

namespace behaviorpack\spawn;

use behaviorpack\entity\BehaviorEntity;
use pocketmine\block\BubbleColumn;
use pocketmine\block\Lava;
use pocketmine\block\Liquid;
use pocketmine\block\Water;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\world\World;
use Throwable;
use function abs;
use function array_key_exists;
use function array_values;
use function cos;
use function count;
use function explode;
use function floor;
use function in_array;
use function intdiv;
use function lcg_value;
use function max;
use function mt_rand;
use function shuffle;
use function sin;
use function sqrt;
use function str_contains;
use function strtolower;
use function trim;
use const M_PI;

/**
 * Spawns the custom entities of the behavior packs around the players,
 * following their spawn rules and the population cap of each category.
 */
final class NaturalSpawner extends Task{

	public const PERIOD = 20;

	private const ATTEMPTS = 3;

	private const MIN_DISTANCE = 24.0;
	private const MAX_DISTANCE = 128.0;

	private const CAPS = [
		"monster" => 70,
		"animal" => 10,
		"water_animal" => 5,
		"ambient" => 15,
		"cat" => 10,
		"pillager" => 5,
		"villager" => 10
	];

	private const CYCLES = [
		"monster" => 1,
		"animal" => 20,
		"water_animal" => 1,
		"ambient" => 1,
		"cat" => 20,
		"pillager" => 20,
		"villager" => 20
	];

	/** @var array<string, list<SpawnRule>> */
	private array $byCategory = [];

	/** @var array<string, int> */
	private array $nextDelay = [];

	private int $run = 0;

	/**
	 * @param array<string, SpawnRule> $rules
	 */
	public function __construct(
		private Server $server,
		private array $rules
	){
		foreach($rules as $rule){
			$this->byCategory[$rule->category][] = $rule;
		}
	}

	public function onRun() : void{
		$this->run++;
		foreach($this->server->getWorldManager()->getWorlds() as $world){
			$players = [];
			foreach($world->getPlayers() as $player){
				if($player->isAlive() && !$player->isSpectator()){
					$players[] = $player;
				}
			}
			if(count($players) === 0){
				continue;
			}
			try{
				$this->tickWorld($world, $players);
			}catch(Throwable $e){
				$this->server->getLogger()->debug("Behavior packs: natural spawning failed in " . $world->getFolderName() . ": " . $e->getMessage());
			}
		}
	}

	/**
	 * @param list<Player> $players
	 */
	private function tickWorld(World $world, array $players) : void{
		$counts = $this->census($world);
		foreach($this->byCategory as $category => $rules){
			if($this->run % (self::CYCLES[$category] ?? 1) !== 0){
				continue;
			}
			if($category === "monster" && $world->getDifficulty() === World::DIFFICULTY_PEACEFUL){
				continue;
			}
			$cap = (self::CAPS[$category] ?? 10) * count($players);
			foreach($players as $player){
				for($i = 0; $i < self::ATTEMPTS; $i++){
					if(($counts["categories"][$category] ?? 0) >= $cap){
						continue 3;
					}
					$this->attempt($world, $player, $rules, $players, $counts);
				}
			}
		}
	}

	/**
	 * Counts the living entities of the packs by category, and by type on the
	 * surface and underground.
	 *
	 * @return array{categories: array<string, int>, types: array<string, array{0: int, 1: int}>}
	 */
	private function census(World $world) : array{
		$counts = ["categories" => [], "types" => []];
		foreach($world->getEntities() as $entity){
			if(!$entity instanceof BehaviorEntity || $entity->isClosed() || !$entity->isAlive()){
				continue;
			}
			$identifier = strtolower($entity->getIdentifier());
			$rule = $this->rules[$identifier] ?? null;
			if($rule === null){
				continue;
			}
			$counts["categories"][$rule->category] = ($counts["categories"][$rule->category] ?? 0) + 1;
			$index = $this->isSurface($world, $entity->getPosition()) ? 0 : 1;
			$counts["types"][$identifier] ??= [0, 0];
			$counts["types"][$identifier][$index]++;
		}
		return $counts;
	}

	private function isSurface(World $world, Vector3 $position) : bool{
		$x = (int) floor($position->x);
		$z = (int) floor($position->z);
		$top = $world->getHighestBlockAt($x, $z);
		if($top === null || (int) floor($position->y) >= $top){
			return true;
		}
		return $world->getBlockAt($x, $top, $z) instanceof Liquid;
	}

	/**
	 * @param list<SpawnRule> $rules
	 * @param list<Player>    $players
	 * @param array{categories: array<string, int>, types: array<string, array{0: int, 1: int}>} $counts
	 */
	private function attempt(World $world, Player $player, array $rules, array $players, array &$counts) : void{
		$origin = $player->getPosition();
		$angle = lcg_value() * 2 * M_PI;
		$distance = self::MIN_DISTANCE + lcg_value() * (self::MAX_DISTANCE - self::MIN_DISTANCE);
		$x = (int) floor($origin->x + cos($angle) * $distance);
		$z = (int) floor($origin->z + sin($angle) * $distance);
		if(!$world->isChunkLoaded($x >> 4, $z >> 4)){
			return;
		}

		$cache = [];
		$candidates = [];
		$total = 0;
		foreach($rules as $rule){
			foreach($rule->conditions as $condition){
				$spot = $this->spotFor($world, $x, $z, $condition, $cache);
				if($spot === null || !$this->test($world, $rule, $condition, $spot, $players, $counts, true)){
					continue;
				}
				$candidates[] = [$rule, $condition, $spot];
				$total += $condition->weight;
			}
		}
		if($total <= 0){
			return;
		}

		$roll = mt_rand(1, $total);
		foreach($candidates as [$rule, $condition, $spot]){
			$roll -= $condition->weight;
			if($roll > 0){
				continue;
			}
			if($condition->delayMin !== null){
				$key = $this->delayKey($rule, $condition);
				$this->nextDelay[$key] = $this->server->getTick() + mt_rand($condition->delayMin, (int) $condition->delayMax) * 20;
				if(mt_rand(1, 100) > $condition->delayChance){
					return;
				}
			}
			$this->spawnHerd($world, $rule, $condition, $spot, $players, $counts);
			return;
		}
	}

	/**
	 * @param array<string, Vector3|null> $cache
	 */
	private function spotFor(World $world, int $x, int $z, SpawnCondition $condition, array &$cache) : ?Vector3{
		if($condition->underwater){
			$modes = ["water"];
		}elseif($condition->lava){
			$modes = ["lava"];
		}else{
			$modes = [];
			if($condition->surface){
				$modes[] = "surface";
			}
			if($condition->underground){
				$modes[] = "underground";
			}
			shuffle($modes);
		}
		foreach($modes as $mode){
			if(!array_key_exists($mode, $cache)){
				$cache[$mode] = $this->computeSpot($world, $x, $z, $mode);
			}
			if($cache[$mode] !== null){
				return $cache[$mode];
			}
		}
		return null;
	}

	private function computeSpot(World $world, int $x, int $z, string $mode) : ?Vector3{
		$top = $world->getHighestBlockAt($x, $z);
		if($top === null){
			return null;
		}
		$minY = $world->getMinY();
		if($mode === "water" || $mode === "lava"){
			$class = $mode === "water" ? Water::class : Lava::class;
			if(!$world->getBlockAt($x, $top, $z) instanceof $class){
				return null;
			}
			$bottom = $top;
			while($bottom - 1 > $minY && $world->getBlockAt($x, $bottom - 1, $z) instanceof $class){
				$bottom--;
			}
			return new Vector3($x, mt_rand($bottom, $top), $z);
		}
		if($mode === "surface"){
			$y = $top;
			while($y > $minY){
				$block = $world->getBlockAt($x, $y, $z);
				if($block->isSolid() || $block instanceof Liquid){
					break;
				}
				$y--;
			}
			$ground = $world->getBlockAt($x, $y, $z);
			if(!$ground->isSolid() || !$this->isFree($world, $x, $y + 1, $z)){
				return null;
			}
			return new Vector3($x, $y + 1, $z);
		}
		if($top - 2 <= $minY + 1){
			return null;
		}
		for($i = 0; $i < 4; $i++){
			$y = mt_rand($minY + 1, $top - 2);
			if($world->getBlockAt($x, $y - 1, $z)->isSolid() && $this->isFree($world, $x, $y, $z)){
				return new Vector3($x, $y, $z);
			}
		}
		return null;
	}

	private function isFree(World $world, int $x, int $y, int $z) : bool{
		if(!$world->isInWorld($x, $y + 1, $z)){
			return false;
		}
		foreach([$y, $y + 1] as $level){
			$block = $world->getBlockAt($x, $level, $z);
			if($block->isSolid() || $block instanceof Liquid){
				return false;
			}
		}
		return true;
	}

	/**
	 * @param list<Player> $players
	 * @param array{categories: array<string, int>, types: array<string, array{0: int, 1: int}>} $counts
	 */
	private function test(World $world, SpawnRule $rule, SpawnCondition $condition, Vector3 $spot, array $players, array $counts, bool $leader) : bool{
		$x = (int) $spot->x;
		$y = (int) $spot->y;
		$z = (int) $spot->z;
		if(($condition->minY !== null && $y < $condition->minY) || ($condition->maxY !== null && $y > $condition->maxY)){
			return false;
		}
		if($condition->requiresVillage){
			return false;
		}
		if($condition->mobEvent !== null && str_contains($condition->mobEvent, "ender_dragon")){
			return false;
		}

		$nearest = null;
		foreach($players as $player){
			$distance = $player->getPosition()->distanceSquared(new Vector3($x + 0.5, $y, $z + 0.5));
			if($nearest === null || $distance < $nearest){
				$nearest = $distance;
			}
		}
		$nearest = sqrt((float) $nearest);
		if($nearest < max(self::MIN_DISTANCE, $condition->minDistance ?? 0.0)){
			return false;
		}
		if($condition->maxDistance !== null && $nearest > $condition->maxDistance){
			return false;
		}

		$difficulty = $world->getDifficulty();
		if($difficulty < $condition->minDifficulty || $difficulty > $condition->maxDifficulty){
			return false;
		}
		if($condition->worldAgeMin !== null && intdiv($world->getTime(), 20) < $condition->worldAgeMin){
			return false;
		}
		if($condition->minLight !== null){
			$light = $world->getFullLightAt($x, $y, $z);
			if($light < $condition->minLight || $light > (int) $condition->maxLight){
				return false;
			}
		}

		if($condition->onBlocks !== null && !in_array(BlockNames::of($world->getBlockAt($x, $y - 1, $z)), $condition->onBlocks, true)){
			return false;
		}
		if($condition->preventedBlocks !== null && in_array(BlockNames::of($world->getBlockAt($x, $y - 1, $z)), $condition->preventedBlocks, true)){
			return false;
		}
		if($condition->aboveBlocks !== null && !$this->isAbove($world, $x, $y, $z, $condition)){
			return false;
		}
		if($condition->noBubble && $world->getBlockAt($x, $y, $z) instanceof BubbleColumn){
			return false;
		}
		if($condition->biomeFilter !== null && !BiomeFilter::test($condition->biomeFilter, $world, $spot)){
			return false;
		}

		if(!$leader){
			return true;
		}
		if($condition->delayMin !== null && $this->server->getTick() < ($this->nextDelay[$this->delayKey($rule, $condition)] ?? 0)){
			return false;
		}
		$surface = $this->isSurface($world, $spot);
		$limit = $surface ? $condition->densitySurface : $condition->densityUnderground;
		$current = $counts["types"][$rule->identifier][$surface ? 0 : 1] ?? 0;
		return $limit === null || $current < $limit;
	}

	private function isAbove(World $world, int $x, int $y, int $z, SpawnCondition $condition) : bool{
		$range = max(1, $condition->aboveDistance);
		for($d = 1; $d <= $range; $d++){
			if(!$world->isInWorld($x, $y - $d, $z)){
				return false;
			}
			if(in_array(BlockNames::of($world->getBlockAt($x, $y - $d, $z)), (array) $condition->aboveBlocks, true)){
				return true;
			}
		}
		return false;
	}

	private function delayKey(SpawnRule $rule, SpawnCondition $condition) : string{
		return $condition->delayIdentifier ?? $rule->identifier;
	}

	/**
	 * @param list<Player> $players
	 * @param array{categories: array<string, int>, types: array<string, array{0: int, 1: int}>} $counts
	 */
	private function spawnHerd(World $world, SpawnRule $rule, SpawnCondition $condition, Vector3 $spot, array $players, array &$counts) : void{
		$size = mt_rand($condition->herdMin, $condition->herdMax);
		$spawned = 0;
		for($i = 0; $i < $size; $i++){
			$position = $i === 0 ? $spot : $this->herdSpot($world, $rule, $condition, $spot, $players, $counts);
			if($position === null){
				continue;
			}
			[$identifier, $typeEvent] = $this->pickType($rule, $condition);
			$entity = $this->create($world, $identifier, $position);
			if($entity === null){
				continue;
			}
			if($entity instanceof BehaviorEntity){
				if($typeEvent !== null){
					$entity->triggerEvent($typeEvent);
				}
				if($condition->initialEvent !== null && $spawned < $condition->initialEventCount){
					$entity->triggerEvent($condition->initialEvent);
				}elseif($condition->herdEvent !== null && $spawned >= $condition->herdEventSkip){
					$entity->triggerEvent($condition->herdEvent);
				}
			}
			$entity->spawnToAll();
			$spawned++;
			$counts["categories"][$rule->category] = ($counts["categories"][$rule->category] ?? 0) + 1;
			$counts["types"][$rule->identifier] ??= [0, 0];
			$counts["types"][$rule->identifier][$this->isSurface($world, $position) ? 0 : 1]++;
		}
	}

	/**
	 * @param list<Player> $players
	 * @param array{categories: array<string, int>, types: array<string, array{0: int, 1: int}>} $counts
	 */
	private function herdSpot(World $world, SpawnRule $rule, SpawnCondition $condition, Vector3 $origin, array $players, array $counts) : ?Vector3{
		for($try = 0; $try < 4; $try++){
			$x = (int) $origin->x + mt_rand(-4, 4);
			$z = (int) $origin->z + mt_rand(-4, 4);
			if(!$world->isChunkLoaded($x >> 4, $z >> 4)){
				continue;
			}
			$cache = [];
			$spot = $this->spotFor($world, $x, $z, $condition, $cache);
			if($spot !== null && abs($spot->y - $origin->y) <= 4 && $this->test($world, $rule, $condition, $spot, $players, $counts, false)){
				return $spot;
			}
		}
		return null;
	}

	/**
	 * Picks the entity type from "minecraft:permute_type", with an optional
	 * spawn event written as "identifier<event>".
	 *
	 * @return array{0: string, 1: string|null}
	 */
	private function pickType(SpawnRule $rule, SpawnCondition $condition) : array{
		$type = $rule->identifier;
		$total = 0;
		foreach($condition->permutations as $permutation){
			$total += $permutation["weight"];
		}
		if($total > 0){
			$roll = mt_rand(1, $total);
			foreach($condition->permutations as $permutation){
				$roll -= $permutation["weight"];
				if($roll <= 0){
					$type = $permutation["type"];
					break;
				}
			}
		}
		if(!str_contains($type, "<")){
			return [strtolower($type), null];
		}
		$parts = array_values(explode("<", $type, 2));
		$event = trim($parts[1] ?? "", "> ");
		return [strtolower(trim($parts[0])), $event === "" ? null : $event];
	}

	private function create(World $world, string $identifier, Vector3 $position) : ?Entity{
		$nbt = CompoundTag::create()
			->setString(EntityFactory::TAG_IDENTIFIER, $identifier)
			->setTag(Entity::TAG_POS, new ListTag([
				new DoubleTag($position->x + 0.5),
				new DoubleTag($position->y),
				new DoubleTag($position->z + 0.5)
			]))
			->setTag(Entity::TAG_ROTATION, new ListTag([
				new FloatTag(lcg_value() * 360),
				new FloatTag(0.0)
			]));
		try{
			return EntityFactory::getInstance()->createFromData($world, $nbt);
		}catch(Throwable){
			return null;
		}
	}
}
