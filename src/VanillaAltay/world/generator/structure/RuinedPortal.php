<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure;

use pocketmine\block\BlockTypeIds;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use VanillaAltay\world\generator\structure\template\StructureTemplate;
use VanillaAltay\world\generator\structure\template\TemplateArchive;

use function count;
use function in_array;

final class RuinedPortal implements Structure
{
	private const SALT = 34222645;

	private const HEIGHT_ON_LAND_SURFACE = 0;
	private const HEIGHT_PARTLY_BURIED = 1;
	private const HEIGHT_ON_OCEAN_FLOOR = 2;
	private const HEIGHT_IN_MOUNTAIN = 3;
	private const HEIGHT_UNDERGROUND = 4;
	private const HEIGHT_IN_NETHER = 5;

	private const GIANT_CHANCE = 20;

	private const PORTALS = [
		"ruined_portal/portal_1",
		"ruined_portal/portal_2",
		"ruined_portal/portal_3",
		"ruined_portal/portal_4",
		"ruined_portal/portal_5",
		"ruined_portal/portal_6",
		"ruined_portal/portal_7",
		"ruined_portal/portal_8",
		"ruined_portal/portal_9",
		"ruined_portal/portal_10",
	];

	private const GIANT_PORTALS = [
		"ruined_portal/giant_portal_1",
		"ruined_portal/giant_portal_2",
		"ruined_portal/giant_portal_3",
	];

	private const DESERT_BIOMES = [BiomeIds::DESERT, BiomeIds::DESERT_HILLS, BiomeIds::DESERT_MUTATED];

	private const JUNGLE_BIOMES = [
		BiomeIds::JUNGLE, BiomeIds::JUNGLE_HILLS, BiomeIds::JUNGLE_EDGE, BiomeIds::JUNGLE_MUTATED,
		BiomeIds::JUNGLE_EDGE_MUTATED, BiomeIds::BAMBOO_JUNGLE, BiomeIds::BAMBOO_JUNGLE_HILLS,
	];

	private const SWAMP_BIOMES = [BiomeIds::SWAMPLAND, BiomeIds::SWAMPLAND_MUTATED, BiomeIds::MANGROVE_SWAMP];

	private const MOUNTAIN_BIOMES = [
		BiomeIds::EXTREME_HILLS, BiomeIds::EXTREME_HILLS_EDGE, BiomeIds::EXTREME_HILLS_MUTATED,
		BiomeIds::EXTREME_HILLS_PLUS_TREES, BiomeIds::EXTREME_HILLS_PLUS_TREES_MUTATED, BiomeIds::ICE_MOUNTAINS,
		BiomeIds::JAGGED_PEAKS, BiomeIds::FROZEN_PEAKS, BiomeIds::STONY_PEAKS, BiomeIds::SNOWY_SLOPES,
		BiomeIds::GROVE, BiomeIds::MEADOW,
	];

	private const OCEAN_BIOMES = [
		BiomeIds::OCEAN, BiomeIds::DEEP_OCEAN, BiomeIds::WARM_OCEAN, BiomeIds::DEEP_WARM_OCEAN,
		BiomeIds::LUKEWARM_OCEAN, BiomeIds::DEEP_LUKEWARM_OCEAN, BiomeIds::COLD_OCEAN, BiomeIds::DEEP_COLD_OCEAN,
		BiomeIds::FROZEN_OCEAN, BiomeIds::DEEP_FROZEN_OCEAN, BiomeIds::LEGACY_FROZEN_OCEAN,
	];

	private const NETHER_BIOMES = [
		BiomeIds::HELL, BiomeIds::SOULSAND_VALLEY, BiomeIds::CRIMSON_FOREST, BiomeIds::WARPED_FOREST,
		BiomeIds::BASALT_DELTAS,
	];

	public function getName() : string
	{
		return "ruined_portal";
	}

	public function getPlacement() : StructurePlacement
	{
		return new StructurePlacement(self::SALT, 15, 40, fn(int $biomeId) => true);
	}

	public function place(ChunkManager $world, Random $random, int $x, int $y, int $z) : void
	{
		$height = $this->pickHeight($random, $this->getBiomeId($world, $x, $z));

		$giant = $random->nextBoundedInt(self::GIANT_CHANCE) === 0;
		$variants = $giant ? self::GIANT_PORTALS : self::PORTALS;

		$template = TemplateArchive::getInstance()->get($variants[$random->nextBoundedInt(count($variants))]);
		if ($template === null) {
			return;
		}

		$airPocket = $height === self::HEIGHT_IN_NETHER && $random->nextFloat() < 0.5;
		$originY = $this->findSuitableY($world, $random, $x, $y, $z, $height, $airPocket, $template);

		$template->place($world, $x, $originY, $z, StructureTemplate::ROTATION_NONE);
	}

	private function pickHeight(Random $random, int $biomeId) : int
	{
		if (in_array($biomeId, self::DESERT_BIOMES, true)) {
			return self::HEIGHT_PARTLY_BURIED;
		}

		if (in_array($biomeId, self::JUNGLE_BIOMES, true) || in_array($biomeId, self::SWAMP_BIOMES, true)) {
			return self::HEIGHT_ON_LAND_SURFACE;
		}

		if (in_array($biomeId, self::MOUNTAIN_BIOMES, true)) {
			return $random->nextBoolean() ? self::HEIGHT_ON_LAND_SURFACE : self::HEIGHT_IN_MOUNTAIN;
		}

		if (in_array($biomeId, self::OCEAN_BIOMES, true)) {
			return self::HEIGHT_ON_OCEAN_FLOOR;
		}

		if (in_array($biomeId, self::NETHER_BIOMES, true)) {
			return self::HEIGHT_IN_NETHER;
		}

		return $random->nextBoolean() ? self::HEIGHT_ON_LAND_SURFACE : self::HEIGHT_UNDERGROUND;
	}

	/**
	 * Starts from the height the variant asks for and walks down until at least three of the four corners rest
	 * on solid ground.
	 */
	private function findSuitableY(ChunkManager $world, Random $random, int $x, int $surfaceY, int $z, int $height, bool $airPocket, StructureTemplate $template) : int
	{
		$minY = $world->getMinY() + 15;

		$centerSurfaceY = $height === self::HEIGHT_ON_OCEAN_FLOOR
			? $this->getFloorY($world, $x + ($template->getSizeX() >> 1), $surfaceY, $z + ($template->getSizeZ() >> 1))
			: $surfaceY;

		$start = match ($height) {
			self::HEIGHT_IN_NETHER => $airPocket ? $random->nextRange(32, 100) : ($random->nextBoolean() ? $random->nextRange(27, 29) : $random->nextRange(29, 100)),
			self::HEIGHT_IN_MOUNTAIN => self::randomWithin($random, 70, $centerSurfaceY - $template->getSizeY()),
			self::HEIGHT_UNDERGROUND => self::randomWithin($random, $minY, $centerSurfaceY - $template->getSizeY()),
			self::HEIGHT_PARTLY_BURIED => $centerSurfaceY - $template->getSizeY() + $random->nextRange(2, 8),
			default => $centerSurfaceY,
		};

		$maxX = $x + $template->getSizeX() - 1;
		$maxZ = $z + $template->getSizeZ() - 1;

		for ($y = $start; $y > $minY; --$y) {
			$corners = 0;
			foreach ([[$x, $z], [$maxX, $z], [$x, $maxZ], [$maxX, $maxZ]] as [$cornerX, $cornerZ]) {
				if ($this->isOpaqueGround($world, $cornerX, $y, $cornerZ)) {
					++$corners;
				}
			}

			if ($corners >= 3) {
				return $y;
			}
		}

		return $minY;
	}

	private function isOpaqueGround(ChunkManager $world, int $x, int $y, int $z) : bool
	{
		$block = $world->getBlockAt($x, $y, $z);

		return $block->isSolid() && !$block->canBeReplaced() && !$block->isTransparent();
	}

	private function getFloorY(ChunkManager $world, int $x, int $y, int $z) : int
	{
		for (; $y > $world->getMinY(); --$y) {
			if ($world->getBlockAt($x, $y, $z)->getTypeId() !== BlockTypeIds::WATER) {
				break;
			}
		}

		return $y;
	}

	private function getBiomeId(ChunkManager $world, int $x, int $z) : int
	{
		$chunk = $world->getChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE);

		return $chunk?->getBiomeId($x & Chunk::COORD_MASK, 0, $z & Chunk::COORD_MASK) ?? BiomeIds::PLAINS;
	}

	private static function randomWithin(Random $random, int $start, int $end) : int
	{
		return $start < $end ? $random->nextRange($start, $end) : $end;
	}
}
