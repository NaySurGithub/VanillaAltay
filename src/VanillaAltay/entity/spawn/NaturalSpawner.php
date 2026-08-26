<?php

declare(strict_types=1);

namespace VanillaAltay\entity\spawn;

use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use VanillaAltay\entity\IntelligentMob;

use function array_filter;
use function array_map;
use function array_sum;
use function array_values;
use function cos;
use function max;
use function min;
use function mt_rand;
use function sin;

final class NaturalSpawner
{
	private const MIN_PLAYER_DISTANCE = 24;

	private const MAX_PLAYER_DISTANCE = 44;

	/** @param list<SpawnRule> $rules */
	public function __construct(private array $rules, private int $monsterCap = 70, private int $creatureCap = 10, private int $waterCreatureCap = 20, private int $ambientCap = 15)
	{
	}

	public function tick(World $world) : void
	{
		$players = array_values(array_filter($world->getPlayers(), static fn(Player $player) : bool => !$player->isSpectator()));
		if ($players === []) {
			return;
		}

		$counts = $this->countCategories($world);
		foreach ($players as $player) {
			foreach ([SpawnCategory::MONSTER, SpawnCategory::CREATURE, SpawnCategory::WATER_CREATURE, SpawnCategory::AMBIENT] as $category) {
				$cap = match ($category) {
					SpawnCategory::MONSTER => $this->monsterCap,
					SpawnCategory::CREATURE => $this->creatureCap,
					SpawnCategory::WATER_CREATURE => $this->waterCreatureCap,
					SpawnCategory::AMBIENT => $this->ambientCap,
				};
				if (($counts[$category->name] ?? 0) >= $cap) {
					continue;
				}
				$rule = $this->pickRule($category);
				if ($rule !== null) {
					$counts[$category->name] = ($counts[$category->name] ?? 0) + $this->trySpawnHerd($world, $player, $rule);
				}
			}
		}
	}

	/** @return array<string, int> */
	private function countCategories(World $world) : array
	{
		$result = [];
		foreach ($world->getEntities() as $entity) {
			if ($entity instanceof IntelligentMob) {
				$category = match (true) {
					$entity instanceof \VanillaAltay\entity\aquatic\WaterMob => SpawnCategory::WATER_CREATURE,
					$entity instanceof \VanillaAltay\entity\flying\Bat => SpawnCategory::AMBIENT,
					$entity->isHostile() => SpawnCategory::MONSTER,
					default => SpawnCategory::CREATURE,
				};
				$result[$category->name] = ($result[$category->name] ?? 0) + 1;
			}
		}
		return $result;
	}

	private function pickRule(SpawnCategory $category) : ?SpawnRule
	{
		$candidates = array_values(array_filter($this->rules, static fn(SpawnRule $rule) : bool => $rule->category === $category));
		$total = array_sum(array_map(static fn(SpawnRule $rule) : int => $rule->weight, $candidates));
		if ($total === 0) {
			return null;
		}
		$roll = mt_rand(1, $total);
		foreach ($candidates as $rule) {
			$roll -= $rule->weight;
			if ($roll <= 0) {
				return $rule;
			}
		}
		return null;
	}

	private function trySpawnHerd(World $world, Player $player, SpawnRule $rule) : int
	{
		$angle = mt_rand(0, 6283) / 1000;
		$distance = mt_rand(self::MIN_PLAYER_DISTANCE, self::MAX_PLAYER_DISTANCE);
		$baseX = $player->getPosition()->getFloorX() + (int) (cos($angle) * $distance);
		$baseZ = $player->getPosition()->getFloorZ() + (int) (sin($angle) * $distance);
		if (!$world->isChunkLoaded($baseX >> Chunk::COORD_BIT_SIZE, $baseZ >> Chunk::COORD_BIT_SIZE)) {
			return 0;
		}
		$spawned = 0;
		$amount = mt_rand($rule->herdMin, $rule->herdMax);
		for ($i = 0; $i < $amount; ++$i) {
			$x = $baseX + mt_rand(-4, 4);
			$z = $baseZ + mt_rand(-4, 4);
			$position = $this->pickPosition($world, $rule->category, $x, $z);
			if ($position === null) {
				continue;
			}
			if (!$rule->canSpawn($world, $position) || !$this->farEnoughFromPlayers($world, $position)) {
				continue;
			}
			$entity = $rule->create($world, $position);
			if ($entity instanceof IntelligentMob) {
				$entity->setNaturallySpawned();
			}
			$entity->spawnToAll();
			++$spawned;
		}
		return $spawned;
	}

	private function pickPosition(World $world, SpawnCategory $category, int $x, int $z) : ?Vector3
	{
		$highest = $world->getHighestBlockAt($x, $z);
		if ($highest === null) {
			return null;
		}
		if ($category === SpawnCategory::WATER_CREATURE) {
			$minWaterY = $world->getMinY() + 1;
			$maxWaterY = min($highest, 63);
			if ($maxWaterY < $minWaterY) {
				return null;
			}
			$top = null;
			$bottom = null;
			for ($y = $maxWaterY; $y >= $minWaterY; --$y) {
				if ($world->getBlockAt($x, $y, $z)->getTypeId() === \pocketmine\block\BlockTypeIds::WATER) {
					$top ??= $y;
					$bottom = $y;
				} elseif ($top !== null) {
					break;
				}
			}
			if ($top !== null && $bottom !== null) {
				return new Vector3($x + .5, mt_rand($bottom, $top), $z + .5);
			}
			return null;
		}
		if ($category !== SpawnCategory::MONSTER && $category !== SpawnCategory::AMBIENT || ($category === SpawnCategory::MONSTER && mt_rand(0, 1) === 0)) {
			return new Vector3($x + 0.5, $highest + 1, $z + 0.5);
		}

		$startY = mt_rand($world->getMinY() + 2, max($world->getMinY() + 2, $highest - 2));
		for ($y = $startY; $y > $world->getMinY(); --$y) {
			$ground = $world->getBlockAt($x, $y - 1, $z);
			if ($ground->isSolid() && $world->getBlockAt($x, $y, $z)->canBeReplaced() && $world->getBlockAt($x, $y + 1, $z)->canBeReplaced()) {
				return new Vector3($x + 0.5, $y, $z + 0.5);
			}
		}
		return null;
	}

	private function farEnoughFromPlayers(World $world, Vector3 $position) : bool
	{
		foreach ($world->getPlayers() as $player) {
			if ($player->getPosition()->distanceSquared($position) < self::MIN_PLAYER_DISTANCE ** 2) {
				return false;
			}
		}
		return true;
	}
}
