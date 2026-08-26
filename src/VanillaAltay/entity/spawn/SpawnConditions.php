<?php

declare(strict_types=1);

namespace VanillaAltay\entity\spawn;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\Leaves;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\math\Vector3;
use pocketmine\world\World;

use function in_array;
use function str_contains;
use function strtolower;

final class SpawnConditions
{
	private function __construct()
	{
		//NOOP
	}

	public static function monsterOnGround(World $world, Vector3 $position) : bool
	{
		if ($world->getDifficulty() === World::DIFFICULTY_PEACEFUL || $world->getFullLight($position) > 7) {
			return false;
		}
		return self::hasGroundAndSpace($world, $position);
	}

	public static function creatureOnGrass(World $world, Vector3 $position) : bool
	{
		return $world->getFullLight($position) >= 7 && self::hasGroundAndSpace($world, $position) &&
			$world->getBlock($position->down())->getTypeId() === BlockTypeIds::GRASS;
	}

	public static function monsterInDesert(World $world, Vector3 $position) : bool
	{
		return self::monsterOnGround($world, $position) && $world->getBiomeId($position->getFloorX(), $position->getFloorY(), $position->getFloorZ()) === BiomeIds::DESERT;
	}

	public static function underwater(World $world, Vector3 $position) : bool
	{
		return $world->getBlock($position)->getTypeId() === BlockTypeIds::WATER && $world->getBlock($position->up())->getTypeId() === BlockTypeIds::WATER;
	}

	public static function coldOceanWater(World $world, Vector3 $position) : bool
	{
		if (!self::underwater($world, $position) || $position->y > 64) {
			return false;
		}
		return in_array($world->getBiomeId($position->getFloorX(), $position->getFloorY(), $position->getFloorZ()), [
			BiomeIds::OCEAN, BiomeIds::DEEP_OCEAN, BiomeIds::COLD_OCEAN, BiomeIds::DEEP_COLD_OCEAN,
			BiomeIds::FROZEN_OCEAN, BiomeIds::DEEP_FROZEN_OCEAN,
		], true);
	}

	public static function warmOceanWater(World $world, Vector3 $position) : bool
	{
		return self::underwater($world, $position) && in_array($world->getBiomeId($position->getFloorX(), $position->getFloorY(), $position->getFloorZ()), [
			BiomeIds::WARM_OCEAN, BiomeIds::DEEP_WARM_OCEAN, BiomeIds::LUKEWARM_OCEAN, BiomeIds::DEEP_LUKEWARM_OCEAN,
		], true);
	}

	public static function glowSquidWater(World $world, Vector3 $position) : bool
	{
		return $position->y <= 30 && self::underwater($world, $position) && $world->getFullLight($position) === 0;
	}

	public static function axolotlWater(World $world, Vector3 $position) : bool
	{
		return $position->y < 64 && self::underwater($world, $position) &&
			$world->getBlock($position->down())->getTypeId() === BlockTypeIds::CLAY &&
			$world->getBiomeId($position->getFloorX(), $position->getFloorY(), $position->getFloorZ()) === BiomeIds::LUSH_CAVES;
	}

	public static function dolphinWater(World $world, Vector3 $position) : bool
	{
		if ($position->y < 0 || $position->y > 64 || !self::underwater($world, $position)) {
			return false;
		}
		return in_array($world->getBiomeId($position->getFloorX(), $position->getFloorY(), $position->getFloorZ()), [
			BiomeIds::OCEAN, BiomeIds::DEEP_OCEAN, BiomeIds::COLD_OCEAN, BiomeIds::DEEP_COLD_OCEAN,
			BiomeIds::WARM_OCEAN, BiomeIds::DEEP_WARM_OCEAN, BiomeIds::LUKEWARM_OCEAN, BiomeIds::DEEP_LUKEWARM_OCEAN,
		], true);
	}

	public static function batCave(World $world, Vector3 $position) : bool
	{
		return $position->y <= 63 && $world->getFullLight($position) <= 4 && $world->getBlock($position)->canBeReplaced() && $world->getBlock($position->up())->canBeReplaced();
	}

	public static function parrotJungle(World $world, Vector3 $position) : bool
	{
		if (!self::creatureOnGrass($world, $position)) {
			return false;
		}
		return in_array($world->getBiomeId($position->getFloorX(), $position->getFloorY(), $position->getFloorZ()), [BiomeIds::JUNGLE, BiomeIds::JUNGLE_HILLS, BiomeIds::JUNGLE_EDGE, BiomeIds::JUNGLE_MUTATED, BiomeIds::JUNGLE_EDGE_MUTATED], true);
	}

	public static function camelDesert(World $world, Vector3 $position) : bool
	{
		return $world->getFullLight($position) >= 7 && self::hasGroundAndSpace($world, $position) && $world->getBiomeId($position->getFloorX(), $position->getFloorY(), $position->getFloorZ()) === BiomeIds::DESERT;
	}

	public static function mountainAnimal(World $world, Vector3 $position) : bool
	{
		return $position->y >= 80 && $world->getFullLight($position) >= 7 && self::hasGroundAndSpace($world, $position);
	}

	public static function armadilloBiome(World $world, Vector3 $position) : bool
	{
		return self::hasGroundAndSpace($world, $position) && in_array($world->getBiomeId($position->getFloorX(), $position->getFloorY(), $position->getFloorZ()), [BiomeIds::SAVANNA, BiomeIds::MESA], true);
	}

	public static function mushroomIsland(World $world, Vector3 $position) : bool
	{
		return self::creatureOnGrass($world, $position) && in_array($world->getBiomeId($position->getFloorX(), $position->getFloorY(), $position->getFloorZ()), [BiomeIds::MUSHROOM_ISLAND, BiomeIds::MUSHROOM_ISLAND_SHORE], true);
	}

	public static function netherMonster(World $world, Vector3 $position) : bool
	{
		return str_contains(strtolower($world->getProvider()->getWorldData()->getGenerator()), "nether") && self::hasGroundAndSpace($world, $position);
	}

	public static function jungleAnimal(World $world, Vector3 $position) : bool
	{
		return self::creatureOnGrass($world, $position) && in_array($world->getBiomeId($position->getFloorX(), $position->getFloorY(), $position->getFloorZ()), [BiomeIds::JUNGLE, BiomeIds::JUNGLE_HILLS, BiomeIds::JUNGLE_EDGE, BiomeIds::JUNGLE_MUTATED], true);
	}

	public static function taigaAnimal(World $world, Vector3 $position) : bool
	{
		return self::creatureOnGrass($world, $position) && in_array($world->getBiomeId($position->getFloorX(), $position->getFloorY(), $position->getFloorZ()), [BiomeIds::TAIGA, BiomeIds::MEGA_TAIGA, BiomeIds::COLD_TAIGA], true);
	}

	public static function frozenAnimal(World $world, Vector3 $position) : bool
	{
		return self::hasGroundAndSpace($world, $position) && in_array($world->getBiomeId($position->getFloorX(), $position->getFloorY(), $position->getFloorZ()), [BiomeIds::ICE_PLAINS, BiomeIds::ICE_MOUNTAINS, BiomeIds::COLD_TAIGA], true);
	}

	public static function turtleBeach(World $world, Vector3 $position) : bool
	{
		return self::hasGroundAndSpace($world, $position) && $world->getBiomeId($position->getFloorX(), $position->getFloorY(), $position->getFloorZ()) === BiomeIds::BEACH;
	}

	public static function frogWetland(World $world, Vector3 $position) : bool
	{
		return self::hasGroundAndSpace($world, $position) && $world->getBiomeId($position->getFloorX(), $position->getFloorY(), $position->getFloorZ()) === BiomeIds::SWAMPLAND;
	}

	public static function swampMonster(World $world, Vector3 $position) : bool
	{
		return self::monsterOnGround($world, $position) && in_array($world->getBiomeId($position->getFloorX(), $position->getFloorY(), $position->getFloorZ()), [BiomeIds::SWAMPLAND,BiomeIds::MANGROVE_SWAMP], true);
	}

	public static function frozenMonster(World $world, Vector3 $position) : bool
	{
		return self::monsterOnGround($world, $position) && in_array($world->getBiomeId($position->getFloorX(), $position->getFloorY(), $position->getFloorZ()), [BiomeIds::ICE_PLAINS,BiomeIds::ICE_MOUNTAINS,BiomeIds::COLD_TAIGA,BiomeIds::FROZEN_PEAKS,BiomeIds::FROZEN_RIVER], true);
	}

	public static function drownedWater(World $world, Vector3 $position) : bool
	{
		return $world->getDifficulty() !== World::DIFFICULTY_PEACEFUL && $world->getFullLight($position) <= 7 && self::underwater($world, $position);
	}

	public static function lavaStrider(World $world, Vector3 $position) : bool
	{
		return str_contains(strtolower($world->getProvider()->getWorldData()->getGenerator()), "nether") && $world->getBlock($position->down())->getTypeId() === BlockTypeIds::LAVA && $world->getBlock($position)->canBeReplaced();
	}

	public static function sulfurCaves(World $world, Vector3 $position) : bool
	{
		return $world->getDifficulty() !== World::DIFFICULTY_PEACEFUL && self::hasGroundAndSpace($world, $position) && $world->getBiomeId($position->getFloorX(), $position->getFloorY(), $position->getFloorZ()) === BiomeIds::SULFUR_CAVES;
	}

	public static function netherAirMonster(World $world, Vector3 $position) : bool
	{
		return $world->getDifficulty() !== World::DIFFICULTY_PEACEFUL && str_contains(strtolower($world->getProvider()->getWorldData()->getGenerator()), "nether") && $world->getBlock($position)->canBeReplaced() && $world->getBlock($position->up(3))->canBeReplaced();
	}

	public static function phantomNight(World $world, Vector3 $position) : bool
	{
		$time = $world->getTime() % World::TIME_FULL;
		return $world->getDifficulty() !== World::DIFFICULTY_PEACEFUL && $time >= World::TIME_NIGHT && $time < World::TIME_SUNRISE && $world->getFullLight($position) <= 7 && $world->getBlock($position)->canBeReplaced() && $world->getBlock($position->up())->canBeReplaced();
	}

	public static function hasGroundAndSpace(World $world, Vector3 $position) : bool
	{
		$feet = $world->getBlock($position);
		$head = $world->getBlock($position->up());
		$ground = $world->getBlock($position->down());
		return $feet->canBeReplaced() && $head->canBeReplaced() && $ground->isSolid() && !$ground instanceof Leaves;
	}
}
