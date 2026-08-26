<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\BlockTypeTags;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;

/**
 * Unlike the other structures this one has no region placement: it tries once per chunk with a one in five
 * hundred chance, which is how it is scattered in vanilla.
 */
final class DesertWell implements Structure
{
	private const CHANCE = 500;

	private const MAX_HEIGHT = 128;

	public function getName() : string
	{
		return "desert_well";
	}

	public function getPlacement() : StructurePlacement
	{
		return new StructurePlacement(0, 0, 1, fn(int $biomeId) => $biomeId === BiomeIds::DESERT);
	}

	public function place(ChunkManager $world, Random $random, int $x, int $y, int $z) : void
	{
		if ($random->nextBoundedInt(self::CHANCE) !== 0 || $y > self::MAX_HEIGHT) {
			return;
		}

		if (!$world->getBlockAt($x, $y, $z)->hasTypeTag(BlockTypeTags::SAND)) {
			return;
		}

		for ($dx = -2; $dx <= 2; ++$dx) {
			for ($dz = -2; $dz <= 2; ++$dz) {
				if (
					$world->getBlockAt($x + $dx, $y - 1, $z + $dz)->getTypeId() === BlockTypeIds::AIR &&
					$world->getBlockAt($x + $dx, $y - 2, $z + $dz)->getTypeId() === BlockTypeIds::AIR
				) {
					return;
				}
			}
		}

		$sandstone = VanillaBlocks::SANDSTONE();
		$slab = VanillaBlocks::SANDSTONE_SLAB();
		$water = VanillaBlocks::WATER();

		for ($dy = -1; $dy <= 0; ++$dy) {
			for ($dx = -2; $dx <= 2; ++$dx) {
				for ($dz = -2; $dz <= 2; ++$dz) {
					$world->setBlockAt($x + $dx, $y + $dy, $z + $dz, $sandstone);
				}
			}
		}

		foreach ([[0, 0], [-1, 0], [1, 0], [0, -1], [0, 1]] as [$dx, $dz]) {
			$world->setBlockAt($x + $dx, $y, $z + $dz, $water);
		}

		for ($dx = -2; $dx <= 2; ++$dx) {
			for ($dz = -2; $dz <= 2; ++$dz) {
				if ($dx === -2 || $dx === 2 || $dz === -2 || $dz === 2) {
					$world->setBlockAt($x + $dx, $y + 1, $z + $dz, $sandstone);
				}
			}
		}

		foreach ([[2, 0], [-2, 0], [0, 2], [0, -2]] as [$dx, $dz]) {
			$world->setBlockAt($x + $dx, $y + 1, $z + $dz, $slab);
		}

		for ($dx = -1; $dx <= 1; ++$dx) {
			for ($dz = -1; $dz <= 1; ++$dz) {
				$world->setBlockAt($x + $dx, $y + 4, $z + $dz, $dx === 0 && $dz === 0 ? $sandstone : $slab);
			}
		}

		for ($dy = 1; $dy <= 3; ++$dy) {
			foreach ([[-1, -1], [-1, 1], [1, -1], [1, 1]] as [$dx, $dz]) {
				$world->setBlockAt($x + $dx, $y + $dy, $z + $dz, $sandstone);
			}
		}
	}
}
