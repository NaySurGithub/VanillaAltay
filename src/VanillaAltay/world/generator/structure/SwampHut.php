<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure;

use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;

final class SwampHut implements Structure
{
	private const SALT = 14357620;

	public function getName() : string
	{
		return "swamp_hut";
	}

	public function getPlacement() : StructurePlacement
	{
		return new StructurePlacement(self::SALT, 8, 32, fn(int $biomeId) => $biomeId === BiomeIds::SWAMPLAND);
	}

	public function place(ChunkManager $world, Random $random, int $x, int $y, int $z) : void
	{
		$builder = new StructureBuilder($world, $x, $y, $z);

		$planks = VanillaBlocks::SPRUCE_PLANKS();
		$fence = VanillaBlocks::OAK_FENCE();
		$log = VanillaBlocks::OAK_LOG();
		$stairs = VanillaBlocks::SPRUCE_STAIRS();

		$builder->fill(1, 1, 2, 5, 4, 7, $planks);
		$builder->fill(1, 1, 1, 5, 1, 1, $planks);
		$builder->fill(2, 1, 0, 4, 1, 0, $planks);

		$builder->clear(2, 2, 3, 4, 3, 6);
		$builder->clear(4, 2, 2, 4, 3, 2);
		$builder->clear(5, 3, 4, 5, 3, 5);
		$builder->clear(1, 3, 4, 1, 3, 4);

		$builder->set(1, 3, 5, VanillaBlocks::FLOWER_POT());
		$builder->set(2, 3, 2, $fence);
		$builder->set(3, 3, 7, $fence);

		$builder->fill(0, 4, 1, 6, 4, 1, (clone $stairs)->setFacing(Facing::NORTH));
		$builder->fill(6, 4, 2, 6, 4, 7, (clone $stairs)->setFacing(Facing::EAST));
		$builder->fill(0, 4, 8, 6, 4, 8, (clone $stairs)->setFacing(Facing::SOUTH));
		$builder->fill(0, 4, 2, 0, 4, 7, (clone $stairs)->setFacing(Facing::WEST));

		foreach ([[1, 2], [5, 2], [1, 7], [5, 7]] as [$px, $pz]) {
			$builder->fill($px, 0, $pz, $px, 3, $pz, $log);
			$builder->fillDownward($px, -1, $pz, $log);
		}

		$builder->set(1, 2, 1, $fence);
		$builder->set(5, 2, 1, $fence);
		$builder->set(4, 2, 6, VanillaBlocks::CAULDRON());
		$builder->set(3, 2, 6, VanillaBlocks::CRAFTING_TABLE());
	}
}
