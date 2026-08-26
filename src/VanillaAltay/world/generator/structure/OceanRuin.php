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
use function min;

final class OceanRuin implements Structure
{
	private const SALT = 14357621;

	private const BIOMES = [
		BiomeIds::OCEAN,
		BiomeIds::DEEP_OCEAN,
		BiomeIds::WARM_OCEAN,
		BiomeIds::DEEP_WARM_OCEAN,
		BiomeIds::LUKEWARM_OCEAN,
		BiomeIds::DEEP_LUKEWARM_OCEAN,
		BiomeIds::COLD_OCEAN,
		BiomeIds::DEEP_COLD_OCEAN,
		BiomeIds::FROZEN_OCEAN,
		BiomeIds::DEEP_FROZEN_OCEAN,
		BiomeIds::LEGACY_FROZEN_OCEAN,
	];

	private const WARM_BIOMES = [
		BiomeIds::WARM_OCEAN,
		BiomeIds::DEEP_WARM_OCEAN,
		BiomeIds::LUKEWARM_OCEAN,
		BiomeIds::DEEP_LUKEWARM_OCEAN,
	];

	private const COLD_PREFIXES = ["brick", "mossy", "cracked"];

	private const BIG_VARIANTS = [1, 2, 3, 8];

	public function getName() : string
	{
		return "ocean_ruin";
	}

	public function getPlacement() : StructurePlacement
	{
		return new StructurePlacement(self::SALT, 8, 20, fn(int $biomeId) => in_array($biomeId, self::BIOMES, true));
	}

	public function place(ChunkManager $world, Random $random, int $x, int $y, int $z) : void
	{
		$warm = in_array(self::getBiomeId($world, $x, $z), self::WARM_BIOMES, true);
		$large = $random->nextBoundedInt(100) <= 30;

		if (!$this->placeRuin($world, $random, $x, $y, $z, $warm, $large)) {
			return;
		}

		if (!$large || $random->nextBoundedInt(100) > 90) {
			return;
		}

		$extra = $random->nextRange(4, 7);
		for ($i = 0; $i < $extra; ++$i) {
			$this->placeRuin($world, $random, $x + $random->nextRange(-16, 16), $y, $z + $random->nextRange(-16, 16), $warm, false);
		}
	}

	private function placeRuin(ChunkManager $world, Random $random, int $x, int $y, int $z, bool $warm, bool $large) : bool
	{
		$template = TemplateArchive::getInstance()->get($this->pickTemplate($random, $warm, $large));
		if ($template === null) {
			return false;
		}

		$rotation = $random->nextBoundedInt(4);
		[$spanX, $spanZ] = $rotation === StructureTemplate::ROTATION_90 || $rotation === StructureTemplate::ROTATION_270
			? [$template->getSizeZ(), $template->getSizeX()]
			: [$template->getSizeX(), $template->getSizeZ()];

		$floorY = self::getSeaFloor($world, $x, $y, $z, $spanX, $spanZ);
		if ($floorY === -1) {
			return false;
		}

		$template->place($world, $x, $floorY, $z, $rotation);

		return true;
	}

	private function pickTemplate(Random $random, bool $warm, bool $large) : string
	{
		if ($warm) {
			return $large
				? "underwater_ruin/big_warm_" . (4 + $random->nextBoundedInt(4))
				: "underwater_ruin/warm_" . ($random->nextBoundedInt(8) + 1);
		}

		$prefix = self::COLD_PREFIXES[$random->nextBoundedInt(count(self::COLD_PREFIXES))];

		return $large
			? "underwater_ruin/big_" . $prefix . "_" . self::BIG_VARIANTS[$random->nextBoundedInt(count(self::BIG_VARIANTS))]
			: "underwater_ruin/" . $prefix . "_" . ($random->nextBoundedInt(8) + 1);
	}

	/**
	 * Sinks the ruin onto the lowest solid block under its footprint, so it never hangs in the water nor buries
	 * half of itself in a slope. Returns -1 when the site is not covered by water.
	 */
	private static function getSeaFloor(ChunkManager $world, int $x, int $startY, int $z, int $spanX, int $spanZ) : int
	{
		if ($world->getBlockAt($x, $startY - 1, $z)->getTypeId() !== BlockTypeIds::WATER) {
			return -1;
		}

		$floor = $startY;

		for ($offsetX = 0; $offsetX < $spanX; ++$offsetX) {
			for ($offsetZ = 0; $offsetZ < $spanZ; ++$offsetZ) {
				$column = self::getColumnFloor($world, $x + $offsetX, $startY, $z + $offsetZ);
				if ($column === -1) {
					return -1;
				}

				$floor = min($floor, $column);
			}
		}

		return $floor;
	}

	private static function getColumnFloor(ChunkManager $world, int $x, int $startY, int $z) : int
	{
		for ($y = $startY; $y > $world->getMinY(); --$y) {
			$typeId = $world->getBlockAt($x, $y, $z)->getTypeId();
			if ($typeId !== BlockTypeIds::AIR && $typeId !== BlockTypeIds::WATER) {
				return $y;
			}
		}

		return -1;
	}

	private static function getBiomeId(ChunkManager $world, int $x, int $z) : int
	{
		return $world->getChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE)?->getBiomeId($x & Chunk::COORD_MASK, 0, $z & Chunk::COORD_MASK) ?? BiomeIds::OCEAN;
	}
}
