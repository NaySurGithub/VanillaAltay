<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure;

use pocketmine\block\Block;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\utils\SlabType;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;

final class DesertPyramid implements Structure{

	private const SALT = 14357617;

	public function getName() : string{
		return "desert_pyramid";
	}

	public function getPlacement() : StructurePlacement{
		return new StructurePlacement(self::SALT, 8, 32, fn(int $biomeId) => $biomeId === BiomeIds::DESERT);
	}

	public function place(ChunkManager $world, Random $random, int $x, int $y, int $z) : void{
		$builder = new StructureBuilder($world, $x, $y - 20, $z);

		$sandstone = VanillaBlocks::SANDSTONE();
		$cut = VanillaBlocks::CUT_SANDSTONE();
		$chiseled = VanillaBlocks::CHISELED_SANDSTONE();
		$slab = VanillaBlocks::SANDSTONE_SLAB();
		$slabTop = VanillaBlocks::SANDSTONE_SLAB()->setSlabType(SlabType::TOP);
		$stairs = VanillaBlocks::SANDSTONE_STAIRS();
		$stairsNorth = (clone $stairs)->setFacing(Facing::NORTH);
		$stairsEast = (clone $stairs)->setFacing(Facing::EAST);
		$stairsSouth = (clone $stairs)->setFacing(Facing::SOUTH);
		$stairsWest = (clone $stairs)->setFacing(Facing::WEST);
		$orange = VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::ORANGE);
		$blue = VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::BLUE);
		$chest = VanillaBlocks::CHEST();

		for($x2 = 0; $x2 < 21; ++$x2){
			for($z2 = 0; $z2 < 21; ++$z2){
				$builder->fillDownward($x2, 13, $z2, $sandstone, 24);
			}
		}

		$builder->fill(0, 14, 0, 20, 18, 20, $sandstone);
		for($i = 1; $i <= 9; ++$i){
			$builder->fill($i, $i + 18, $i, 20 - $i, $i + 18, 20 - $i, $sandstone);
			$builder->clear($i + 1, $i + 18, $i + 1, 19 - $i, $i + 18, 19 - $i);
		}

		$this->fillHollowed($builder, 0, 18, 0, 4, 27, 4, $sandstone);
		$builder->fill(1, 28, 1, 3, 28, 3, $sandstone);
		$builder->set(2, 28, 0, $stairsNorth);
		$builder->set(4, 28, 2, $stairsEast);
		$builder->set(2, 28, 4, $stairsSouth);
		$builder->set(0, 28, 2, $stairsWest);
		$builder->fill(1, 19, 5, 3, 22, 11, $sandstone);
		$builder->clear(2, 22, 4, 2, 24, 4);
		$builder->fill(1, 19, 3, 2, 20, 3, $sandstone);
		$builder->set(1, 19, 2, $sandstone);
		$builder->set(1, 20, 2, $slab);
		$builder->set(2, 19, 2, $stairsEast);
		for($i = 0; $i < 2; ++$i){
			$builder->set(2, 21 + $i, 4 + $i, $stairsNorth);
		}

		$this->fillHollowed($builder, 16, 18, 0, 20, 27, 4, $sandstone);
		$builder->fill(17, 28, 1, 19, 28, 3, $sandstone);
		$builder->set(18, 28, 0, $stairsNorth);
		$builder->set(20, 28, 2, $stairsEast);
		$builder->set(18, 28, 4, $stairsSouth);
		$builder->set(16, 28, 2, $stairsWest);
		$builder->fill(17, 19, 5, 19, 22, 11, $sandstone);
		$builder->clear(18, 22, 4, 18, 24, 4);
		$builder->fill(18, 19, 3, 19, 20, 3, $sandstone);
		$builder->set(19, 19, 2, $sandstone);
		$builder->set(19, 20, 2, $slabTop);
		$builder->set(18, 19, 2, $stairsWest);
		for($i = 0; $i < 2; ++$i){
			$builder->set(18, 21 + $i, 4 + $i, $stairsNorth);
		}

		for($i = 0; $i < 2; ++$i){
			$o = $i << 4;
			$builder->fill(1 + $o, 20, 0, 1 + $o, 21, 0, $cut);
			$builder->fill(2 + $o, 20, 0, 2 + $o, 21, 0, $orange);
			$builder->fill(3 + $o, 20, 0, 3 + $o, 21, 0, $cut);
			$builder->set(1 + $o, 22, 0, $orange);
			$builder->set(2 + $o, 22, 0, $chiseled);
			$builder->set(3 + $o, 22, 0, $orange);
			$builder->set(1 + $o, 23, 0, $cut);
			$builder->set(2 + $o, 23, 0, $orange);
			$builder->set(3 + $o, 23, 0, $cut);
			$builder->set(1 + $o, 24, 0, $orange);
			$builder->set(2 + $o, 24, 0, $chiseled);
			$builder->set(3 + $o, 24, 0, $orange);
			$builder->fill(1 + $o, 25, 0, 3 + $o, 25, 0, $orange);
			$builder->fill(1 + $o, 26, 0, 3 + $o, 26, 0, $cut);

			$s = $i * 20;
			$builder->fill($s, 20, 1, $s, 21, 1, $cut);
			$builder->fill($s, 20, 2, $s, 21, 2, $orange);
			$builder->fill($s, 20, 3, $s, 21, 3, $cut);
			$builder->set($s, 22, 1, $orange);
			$builder->set($s, 22, 2, $chiseled);
			$builder->set($s, 22, 3, $orange);
			$builder->set($s, 23, 1, $cut);
			$builder->set($s, 23, 2, $orange);
			$builder->set($s, 23, 3, $cut);
			$builder->set($s, 24, 1, $orange);
			$builder->set($s, 24, 2, $chiseled);
			$builder->set($s, 24, 3, $orange);
			$builder->fill($s, 25, 1, $s, 25, 3, $orange);
			$builder->fill($s, 26, 1, $s, 26, 3, $cut);
		}

		$this->fillHollowed($builder, 8, 18, 1, 12, 22, 4, $sandstone);
		$builder->clear(9, 19, 0, 11, 21, 4);
		$builder->fill(9, 19, 1, 9, 20, 1, $cut);
		$builder->fill(11, 19, 1, 11, 20, 1, $cut);
		$builder->fill(9, 21, 1, 11, 21, 1, $cut);
		$builder->fill(8, 18, 0, 8, 21, 0, $sandstone);
		$builder->fill(12, 18, 0, 12, 21, 0, $sandstone);
		$builder->fill(8, 22, 0, 12, 22, 0, $cut);
		$builder->set(8, 23, 0, $cut);
		$builder->set(9, 23, 0, $orange);
		$builder->set(10, 23, 0, $chiseled);
		$builder->set(11, 23, 0, $orange);
		$builder->set(12, 23, 0, $cut);
		$builder->fill(9, 24, 0, 11, 24, 0, $cut);

		$builder->fill(5, 23, 9, 5, 25, 11, $cut);
		$builder->fill(6, 25, 9, 6, 25, 11, $sandstone);
		$builder->clear(5, 23, 10, 6, 24, 10);

		$builder->fill(15, 23, 9, 15, 25, 11, $cut);
		$builder->fill(14, 25, 9, 14, 25, 11, $sandstone);
		$builder->clear(14, 23, 10, 15, 24, 10);

		$this->fillHollowed($builder, 4, 19, 1, 8, 21, 3, $sandstone);
		$builder->clear(4, 19, 2, 8, 20, 2);

		$this->fillHollowed($builder, 12, 19, 1, 16, 21, 3, $sandstone);
		$builder->clear(12, 19, 2, 16, 20, 2);

		$builder->fill(8, 19, 8, 8, 21, 8, $cut);
		$builder->fill(12, 19, 8, 12, 21, 8, $cut);
		$builder->fill(12, 19, 12, 12, 21, 12, $cut);
		$builder->fill(8, 19, 12, 8, 21, 12, $cut);

		$builder->fill(5, 22, 5, 15, 22, 15, $sandstone);
		$builder->clear(9, 22, 9, 11, 22, 11);

		$builder->clear(3, 19, 5, 3, 20, 11);
		$builder->fill(4, 21, 5, 4, 21, 16, $sandstone);
		$builder->clear(17, 19, 5, 17, 20, 11);
		$builder->fill(16, 21, 5, 16, 21, 16, $sandstone);
		$builder->fill(2, 19, 12, 2, 19, 18, $sandstone);
		$builder->fill(18, 19, 12, 18, 19, 18, $sandstone);
		$builder->fill(3, 19, 18, 18, 19, 18, $sandstone);
		for($i = 0; $i < 7; ++$i){
			$builder->set(4, 19, 5 + ($i << 1), $cut);
			$builder->set(4, 20, 5 + ($i << 1), $chiseled);
			$builder->set(16, 19, 5 + ($i << 1), $cut);
			$builder->set(16, 20, 5 + ($i << 1), $chiseled);
		}

		$builder->set(9, 18, 9, $orange);
		$builder->set(11, 18, 9, $orange);
		$builder->set(11, 18, 11, $orange);
		$builder->set(9, 18, 11, $orange);
		$builder->set(10, 18, 10, $blue);
		$builder->fill(10, 18, 7, 10, 18, 8, $orange);
		$builder->fill(12, 18, 10, 13, 18, 10, $orange);
		$builder->fill(10, 18, 12, 10, 18, 13, $orange);
		$builder->fill(7, 18, 10, 8, 18, 10, $orange);

		$builder->fill(8, 0, 8, 12, 3, 12, $cut);
		$builder->fill(8, 4, 8, 12, 4, 12, $chiseled);
		$builder->fill(8, 5, 8, 12, 5, 12, $cut);
		$builder->fill(8, 6, 8, 12, 13, 12, $sandstone);
		$builder->clear(9, 3, 9, 11, 17, 11);
		$builder->fill(9, 1, 9, 11, 1, 11, VanillaBlocks::TNT());
		$builder->fill(9, 2, 9, 11, 2, 11, $cut);
		$builder->clear(10, 3, 8, 10, 4, 8);
		$builder->set(10, 3, 7, $cut);
		$builder->set(10, 4, 7, $chiseled);
		$builder->clear(12, 3, 10, 12, 4, 10);
		$builder->set(13, 3, 10, $cut);
		$builder->set(13, 4, 10, $chiseled);
		$builder->clear(10, 3, 12, 10, 4, 12);
		$builder->set(10, 3, 13, $cut);
		$builder->set(10, 4, 13, $chiseled);
		$builder->clear(8, 3, 10, 8, 4, 10);
		$builder->set(7, 3, 10, $cut);
		$builder->set(7, 4, 10, $chiseled);
		$builder->set(10, 3, 10, VanillaBlocks::STONE_PRESSURE_PLATE());

		$builder->set(10, 3, 12, $chest);
		$builder->set(8, 3, 10, $chest);
		$builder->set(10, 3, 8, $chest);
		$builder->set(12, 3, 10, $chest);
	}

	/**
	 * Shell of the given block with a hollow interior, matching the two-state fill of the Java source.
	 */
	private function fillHollowed(StructureBuilder $builder, int $x1, int $y1, int $z1, int $x2, int $y2, int $z2, Block $block) : void{
		$builder->fillHollow($x1, $y1, $z1, $x2, $y2, $z2, $block);
		if($x2 - $x1 > 1 && $y2 - $y1 > 1 && $z2 - $z1 > 1){
			$builder->clear($x1 + 1, $y1 + 1, $z1 + 1, $x2 - 1, $y2 - 1, $z2 - 1);
		}
	}
}
