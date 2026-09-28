<?php

declare(strict_types=1);

namespace behaviorpack\script\api;

use behaviorpack\script\ScriptException;
use behaviorpack\script\ScriptLoader;
use behaviorpack\script\ScriptValues;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Liquid;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\World;
use stdClass;
use function abs;
use function count;
use function floor;
use function get_object_vars;
use function in_array;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_string;
use function max;
use function min;
use function mt_rand;
use function spl_object_id;

/**
 * Handles the "dimx." requests: block volumes (fill, query, contains),
 * weather and the nearest block above or below a location.
 */
final class DimensionApi{

	public const MAX_FILL = 32768;

	/** @var array<int, array{type: string, end: int, intensity: int, synced: array<int, true>, world: World}> */
	private array $weather = [];

	private bool $ticking = false;

	public function __construct(
		private ScriptValues $values,
		private ScriptLoader $loader
	){}

	/**
	 * @param list<mixed> $a
	 */
	public function handle(string $method, array $a) : mixed{
		return match($method){
			"dimx.fill" => $this->fill($a[0] ?? null, $a[1] ?? null, $a[2] ?? null, $a[3] ?? null, ($a[4] ?? false) === true),
			"dimx.blocks" => $this->blocks($a[0] ?? null, $a[1] ?? null, $a[2] ?? null, ($a[3] ?? false) === true, ($a[4] ?? false) === true, null),
			"dimx.contains" => $this->blocks($a[0] ?? null, $a[1] ?? null, $a[2] ?? null, ($a[3] ?? false) === true, false, 1) !== [],
			"dimx.weather.get" => $this->getWeather($this->values->world($a[0] ?? null)),
			"dimx.weather.set" => $this->setWeather($this->values->world($a[0] ?? null), $a[1] ?? null, $a[2] ?? null),
			"dimx.near" => $this->near($a[0] ?? null, $a[1] ?? null, (int) ($a[2] ?? 1), is_array($a[3] ?? null) ? $a[3] : []),
			default => throw new ScriptException("Unknown request " . $method)
		};
	}

	/**
	 * Returns the block positions of a volume, either a box {from, to} or a
	 * list {list: [[x, y, z]...]}.
	 *
	 * @return list<array{0: int, 1: int, 2: int}>
	 */
	private function positions(mixed $volume, ?int $limit) : array{
		if(!is_array($volume)){
			throw new ScriptException("Invalid block volume");
		}
		if(isset($volume["list"]) && is_array($volume["list"])){
			$result = [];
			foreach($volume["list"] as $entry){
				if(is_array($entry) && isset($entry[0], $entry[1], $entry[2])){
					$result[] = [(int) $entry[0], (int) $entry[1], (int) $entry[2]];
				}
			}
			if($limit !== null && count($result) > $limit){
				throw new ScriptException("The volume exceeds the maximum of " . $limit . " blocks");
			}
			return $result;
		}
		$from = $this->values->vector($volume["from"] ?? null);
		$to = $this->values->vector($volume["to"] ?? null);
		$minX = (int) floor(min($from->x, $to->x));
		$minY = (int) floor(min($from->y, $to->y));
		$minZ = (int) floor(min($from->z, $to->z));
		$maxX = (int) floor(max($from->x, $to->x));
		$maxY = (int) floor(max($from->y, $to->y));
		$maxZ = (int) floor(max($from->z, $to->z));
		$capacity = ($maxX - $minX + 1) * ($maxY - $minY + 1) * ($maxZ - $minZ + 1);
		if($limit !== null && $capacity > $limit){
			throw new ScriptException("The volume of " . $capacity . " blocks exceeds the maximum of " . $limit . " blocks");
		}
		$result = [];
		for($x = $minX; $x <= $maxX; ++$x){
			for($y = $minY; $y <= $maxY; ++$y){
				for($z = $minZ; $z <= $maxZ; ++$z){
					$result[] = [$x, $y, $z];
				}
			}
		}
		return $result;
	}

	/**
	 * Returns whether the position can be read, throwing on an unloaded
	 * chunk unless unloaded chunks are allowed.
	 *
	 * @param array{0: int, 1: int, 2: int} $position
	 */
	private function readable(World $world, array $position, bool $allowUnloaded) : bool{
		[$x, $y, $z] = $position;
		if($world->isInWorld($x, $y, $z) && $world->isChunkLoaded($x >> 4, $z >> 4)){
			return true;
		}
		if(!$allowUnloaded){
			throw new ScriptException("UnloadedChunksError: the volume contains unloaded chunks or is outside the world bounds");
		}
		return false;
	}

	/**
	 * @return list<mixed>
	 */
	private function blocks(mixed $dimension, mixed $volume, mixed $filter, bool $allowUnloaded, bool $withPermutations, ?int $stopAfter) : array{
		$world = $this->values->world($dimension);
		$filter = is_array($filter) ? $filter : [];
		$result = [];
		foreach($this->positions($volume, null) as $position){
			if(!$this->readable($world, $position, $allowUnloaded)){
				continue;
			}
			$block = $world->getBlockAt($position[0], $position[1], $position[2]);
			if(!$this->matches($block, $filter)){
				continue;
			}
			$result[] = $withPermutations ? ["l" => $position, "p" => $this->values->permutation($block)] : $position;
			if($stopAfter !== null && count($result) >= $stopAfter){
				break;
			}
		}
		return $result;
	}

	/**
	 * @return list<array{0: int, 1: int, 2: int}>
	 */
	private function fill(mixed $dimension, mixed $volume, mixed $blockValue, mixed $filter, bool $ignoreUnloaded) : array{
		$world = $this->values->world($dimension);
		$block = $this->values->block($blockValue);
		$filter = is_array($filter) ? $filter : [];
		$positions = $this->positions($volume, self::MAX_FILL);
		if(!$ignoreUnloaded){
			foreach($positions as $position){
				$this->readable($world, $position, false);
			}
		}
		$placed = [];
		foreach($positions as $position){
			if(!$this->readable($world, $position, true)){
				continue;
			}
			[$x, $y, $z] = $position;
			if(count($filter) > 0 && !$this->matches($world->getBlockAt($x, $y, $z), $filter)){
				continue;
			}
			$world->setBlockAt($x, $y, $z, $block);
			$placed[] = $position;
		}
		return $placed;
	}

	/**
	 * @param array<string, mixed> $filter
	 */
	private function matches(Block $block, array $filter) : bool{
		$typeId = $this->values->blockTypeId($block);
		$includeTypes = $this->typeList($filter["includeTypes"] ?? null);
		$excludeTypes = $this->typeList($filter["excludeTypes"] ?? null);
		$includePermutations = is_array($filter["includePermutations"] ?? null) ? $filter["includePermutations"] : [];
		$excludePermutations = is_array($filter["excludePermutations"] ?? null) ? $filter["excludePermutations"] : [];
		if(in_array($typeId, $excludeTypes, true)){
			return false;
		}
		$permutation = null;
		if(count($includePermutations) > 0 || count($excludePermutations) > 0){
			$permutation = $this->values->permutation($block);
		}
		foreach($excludePermutations as $candidate){
			if($this->permutationMatches($permutation, $candidate)){
				return false;
			}
		}
		if(count($includeTypes) === 0 && count($includePermutations) === 0){
			return true;
		}
		if(in_array($typeId, $includeTypes, true)){
			return true;
		}
		foreach($includePermutations as $candidate){
			if($this->permutationMatches($permutation, $candidate)){
				return true;
			}
		}
		return false;
	}

	/**
	 * @return list<string>
	 */
	private function typeList(mixed $value) : array{
		if(!is_array($value)){
			return [];
		}
		$result = [];
		foreach($value as $entry){
			if(is_string($entry)){
				$result[] = ScriptValues::namespaced($entry);
			}
		}
		return $result;
	}

	/**
	 * @param array<string, mixed>|null $permutation
	 */
	private function permutationMatches(?array $permutation, mixed $candidate) : bool{
		if($permutation === null || !is_array($candidate) || !is_string($candidate["\$p"] ?? null)){
			return false;
		}
		if(ScriptValues::namespaced($candidate["\$p"]) !== $permutation["\$p"]){
			return false;
		}
		$actual = $this->states($permutation["s"] ?? null);
		foreach($this->states($candidate["s"] ?? null) as $name => $value){
			if(!isset($actual[$name]) || !$this->sameState($actual[$name], $value)){
				return false;
			}
		}
		return true;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function states(mixed $states) : array{
		if($states instanceof stdClass){
			return get_object_vars($states);
		}
		return is_array($states) ? $states : [];
	}

	private function sameState(mixed $a, mixed $b) : bool{
		if(is_numeric($a) && is_numeric($b) && !is_bool($a) && !is_bool($b)){
			return abs((float) $a - (float) $b) < 0.000001;
		}
		return $a === $b;
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>|null
	 */
	private function near(mixed $dimension, mixed $location, int $direction, array $options) : ?array{
		$world = $this->values->world($dimension);
		$position = $this->values->vector($location)->floor();
		$x = (int) $position->x;
		$z = (int) $position->z;
		if(!$world->isChunkLoaded($x >> 4, $z >> 4)){
			return null;
		}
		$includeLiquids = ($options["includeLiquidBlocks"] ?? false) === true;
		$includePassable = ($options["includePassableBlocks"] ?? false) === true;
		$step = $direction < 0 ? -1 : 1;
		$y = max($world->getMinY() - 1, min($world->getMaxY(), (int) $position->y)) + $step;
		for(; $y >= $world->getMinY() && $y < $world->getMaxY(); $y += $step){
			$block = $world->getBlockAt($x, $y, $z);
			if($block->getTypeId() === BlockTypeIds::AIR){
				continue;
			}
			if($block instanceof Liquid){
				if($includeLiquids){
					return $this->values->blockRef($block);
				}
				continue;
			}
			if(!$includePassable && !$block->isSolid()){
				continue;
			}
			return $this->values->blockRef($block);
		}
		return null;
	}

	private function getWeather(World $world) : string{
		$state = $this->weather[spl_object_id($world)] ?? null;
		if($state !== null){
			return $state["type"];
		}
		$data = $world->getProvider()->getWorldData();
		if($data->getLightningLevel() > 0){
			return "Thunder";
		}
		if($data->getRainLevel() > 0){
			return "Rain";
		}
		return "Clear";
	}

	private function setWeather(World $world, mixed $type, mixed $duration) : bool{
		if(!in_array($type, ["Clear", "Rain", "Thunder"], true)){
			throw new ScriptException("Invalid weather type");
		}
		$ticks = is_numeric($duration) ? max(1, (int) $duration) : mt_rand(6000, 18000);
		$state = [
			"type" => $type,
			"end" => $this->loader->getPlugin()->getServer()->getTick() + $ticks,
			"intensity" => $type === "Clear" ? 0 : mt_rand(10000, 60000),
			"synced" => []
		];
		$data = $world->getProvider()->getWorldData();
		$data->setRainLevel($type === "Clear" ? 0.0 : $state["intensity"] / 65535);
		$data->setLightningLevel($type === "Thunder" ? $state["intensity"] / 65535 : 0.0);
		$data->setRainTime($ticks);
		$data->setLightningTime($ticks);
		$this->weather[spl_object_id($world)] = $state + ["world" => $world];
		foreach($world->getPlayers() as $player){
			$this->sendWeather($player, $type, $state["intensity"]);
			$this->weather[spl_object_id($world)]["synced"][spl_object_id($player)] = true;
		}
		$this->startTicking();
		return true;
	}

	private function sendWeather(Player $player, string $type, int $intensity) : void{
		$session = $player->getNetworkSession();
		if($type === "Clear"){
			$session->sendDataPacket(LevelEventPacket::create(LevelEvent::STOP_RAIN, 0, null));
			$session->sendDataPacket(LevelEventPacket::create(LevelEvent::STOP_THUNDER, 0, null));
			return;
		}
		$session->sendDataPacket(LevelEventPacket::create(LevelEvent::START_RAIN, $intensity, null));
		if($type === "Thunder"){
			$session->sendDataPacket(LevelEventPacket::create(LevelEvent::START_THUNDER, $intensity, null));
		}else{
			$session->sendDataPacket(LevelEventPacket::create(LevelEvent::STOP_THUNDER, 0, null));
		}
	}

	private function startTicking() : void{
		if($this->ticking){
			return;
		}
		$this->ticking = true;
		$this->loader->getPlugin()->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
			$this->tickWeather();
		}), 20);
	}

	private function tickWeather() : void{
		$tick = $this->loader->getPlugin()->getServer()->getTick();
		foreach($this->weather as $key => $state){
			$world = $state["world"];
			if(!$world instanceof World || !$world->isLoaded()){
				unset($this->weather[$key]);
				continue;
			}
			if($tick >= $state["end"]){
				$this->setWeather($world, $state["type"] === "Clear" ? "Rain" : "Clear", null);
				continue;
			}
			$present = [];
			foreach($world->getPlayers() as $player){
				$id = spl_object_id($player);
				$present[$id] = true;
				if(!isset($state["synced"][$id])){
					$this->sendWeather($player, $state["type"], $state["intensity"]);
				}
			}
			$this->weather[$key]["synced"] = $present;
		}
	}
}
