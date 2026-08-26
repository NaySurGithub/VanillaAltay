<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure;

use pocketmine\block\Block;
use pocketmine\block\utils\LeverFacing;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;

final class JungleTemple implements Structure
{
	private const SALT = 14357619;

	public function getName() : string
	{
		return "jungle_temple";
	}

	public function getPlacement() : StructurePlacement
	{
		return new StructurePlacement(self::SALT, 8, 32, fn(int $biomeId) => $biomeId === BiomeIds::JUNGLE);
	}

	public function place(ChunkManager $world, Random $random, int $x, int $y, int $z) : void
	{
		$builder = new StructureBuilder($world, $x, $y - 3, $z);

		$mossy = VanillaBlocks::MOSSY_COBBLESTONE();
		$chiseled = VanillaBlocks::CHISELED_STONE_BRICKS();
		$tripwire = VanillaBlocks::TRIPWIRE();
		$wire = VanillaBlocks::REDSTONE_WIRE();
		$stairs = VanillaBlocks::COBBLESTONE_STAIRS();

		$stairsN = (clone $stairs)->setFacing(Facing::NORTH);
		$stairsE = (clone $stairs)->setFacing(Facing::EAST);
		$stairsS = (clone $stairs)->setFacing(Facing::SOUTH);
		$stairsW = (clone $stairs)->setFacing(Facing::WEST);

		$hookE = (clone VanillaBlocks::TRIPWIRE_HOOK())->setFacing(Facing::EAST)->setConnected(true);
		$hookW = (clone VanillaBlocks::TRIPWIRE_HOOK())->setFacing(Facing::WEST)->setConnected(true);
		$hookN = (clone VanillaBlocks::TRIPWIRE_HOOK())->setFacing(Facing::NORTH)->setConnected(true);
		$hookS = (clone VanillaBlocks::TRIPWIRE_HOOK())->setFacing(Facing::SOUTH)->setConnected(true);

		$lever = (clone VanillaBlocks::LEVER())->setFacing(LeverFacing::SOUTH);
		$repeater = (clone VanillaBlocks::REDSTONE_REPEATER())->setFacing(Facing::SOUTH)->setDelay(2);

		$vineN = (clone VanillaBlocks::VINES())->setFace(Facing::NORTH, true);
		$vineE = (clone VanillaBlocks::VINES())->setFace(Facing::EAST, true);

		// 1st floor
		$this->fillRandom($builder, $random, 0, 0, 0, 11, 0, 14);
		$this->fillRandom($builder, $random, 0, 1, 0, 11, 3, 0);
		$this->fillRandom($builder, $random, 11, 1, 1, 11, 3, 13);
		$this->fillRandom($builder, $random, 0, 1, 1, 0, 3, 13);
		$this->fillRandom($builder, $random, 0, 1, 14, 11, 3, 14);
		$this->fillRandom($builder, $random, 0, 4, 0, 11, 4, 14);
		$builder->fill(4, 4, 0, 7, 4, 0, $stairsN);
		$builder->clear(1, 1, 1, 10, 3, 13);
		$builder->clear(5, 4, 7, 6, 4, 9);

		// 2nd floor
		$this->fillRandom($builder, $random, 2, 5, 2, 9, 6, 2);
		$this->fillRandom($builder, $random, 9, 5, 3, 9, 6, 11);
		$this->fillRandom($builder, $random, 2, 5, 12, 9, 6, 12);
		$this->fillRandom($builder, $random, 2, 5, 3, 2, 6, 11);
		$this->fillRandom($builder, $random, 1, 7, 1, 10, 7, 13);
		$builder->clear(3, 5, 3, 8, 6, 11);
		$builder->clear(4, 7, 6, 7, 7, 9);
		$builder->clear(5, 5, 2, 6, 6, 2);
		$builder->clear(5, 6, 12, 6, 6, 12);

		// 3rd floor
		$this->fillRandom($builder, $random, 1, 8, 1, 10, 9, 1);
		$this->fillRandom($builder, $random, 10, 8, 2, 10, 9, 12);
		$this->fillRandom($builder, $random, 1, 8, 13, 10, 9, 13);
		$this->fillRandom($builder, $random, 1, 8, 2, 1, 9, 12);
		$builder->clear(2, 8, 2, 9, 9, 12);
		$builder->clear(5, 9, 1, 6, 9, 1);
		$builder->clear(5, 9, 13, 6, 9, 13);
		$builder->clear(10, 9, 5, 10, 9, 5);
		$builder->clear(10, 9, 9, 10, 9, 9);
		$builder->clear(1, 9, 5, 1, 9, 5);
		$builder->clear(1, 9, 9, 1, 9, 9);

		// roof
		$this->fillRandom($builder, $random, 1, 10, 1, 10, 10, 4);
		$this->fillRandom($builder, $random, 8, 10, 5, 10, 10, 9);
		$this->fillRandom($builder, $random, 1, 10, 5, 3, 10, 9);
		$this->fillRandom($builder, $random, 1, 10, 10, 10, 10, 13);
		$this->fillRandom($builder, $random, 3, 11, 3, 8, 11, 5);
		$this->fillRandom($builder, $random, 7, 11, 6, 8, 11, 8);
		$this->fillRandom($builder, $random, 3, 11, 6, 4, 11, 8);
		$this->fillRandom($builder, $random, 3, 11, 9, 8, 11, 11);
		$this->fillRandom($builder, $random, 4, 12, 4, 7, 12, 10);
		$builder->clear(4, 10, 5, 7, 10, 9);
		$builder->clear(5, 11, 6, 6, 11, 8);

		// outside walls decorations
		$this->fillRandom($builder, $random, 2, 8, 0, 2, 9, 0);
		$this->fillRandom($builder, $random, 4, 8, 0, 4, 9, 0);
		$this->fillRandom($builder, $random, 7, 8, 0, 7, 9, 0);
		$this->fillRandom($builder, $random, 9, 8, 0, 9, 9, 0);
		$this->fillRandom($builder, $random, 5, 10, 0, 6, 10, 0);
		for ($i = 0; $i < 6; ++$i) {
			$this->fillRandom($builder, $random, 11, 8, 2 + ($i << 1), 11, 9, 2 + ($i << 1));
			$this->fillRandom($builder, $random, 0, 8, 2 + ($i << 1), 0, 9, 2 + ($i << 1));
		}
		$builder->set(11, 10, 5, $this->randomStone($random));
		$builder->set(11, 10, 9, $this->randomStone($random));
		$builder->set(0, 10, 5, $this->randomStone($random));
		$builder->set(0, 10, 9, $this->randomStone($random));
		$this->fillRandom($builder, $random, 2, 8, 14, 2, 9, 14);
		$this->fillRandom($builder, $random, 4, 8, 14, 4, 9, 14);
		$this->fillRandom($builder, $random, 7, 8, 14, 7, 9, 14);
		$this->fillRandom($builder, $random, 9, 8, 14, 9, 9, 14);

		// roof decorations
		$this->fillRandom($builder, $random, 2, 11, 2, 2, 13, 2);
		$this->fillRandom($builder, $random, 9, 11, 2, 9, 13, 2);
		$this->fillRandom($builder, $random, 9, 11, 12, 9, 13, 12);
		$this->fillRandom($builder, $random, 2, 11, 12, 2, 13, 12);
		$builder->set(4, 13, 4, $this->randomStone($random));
		$builder->set(7, 13, 4, $this->randomStone($random));
		$builder->set(7, 13, 10, $this->randomStone($random));
		$builder->set(4, 13, 10, $this->randomStone($random));
		$builder->fill(5, 13, 6, 6, 13, 6, $stairsN);
		$this->fillRandom($builder, $random, 5, 13, 7, 6, 13, 7);
		$builder->fill(5, 13, 8, 6, 13, 8, $stairsS);

		// 1st floor inside
		for ($i = 0; $i < 6; ++$i) {
			$this->fillRandom($builder, $random, 1, 3, 2 + ($i << 1), 3, 3, 2 + ($i << 1));
		}
		for ($i = 0; $i < 7; ++$i) {
			$this->fillRandom($builder, $random, 1, 1, 1 + ($i << 1), 1, 2, 1 + ($i << 1));
		}
		$builder->set(2, 2, 1, $this->randomStone($random));
		$builder->set(3, 1, 1, $mossy);
		$this->fillRandom($builder, $random, 4, 2, 1, 5, 2, 1);
		$builder->set(6, 1, 1, $this->randomStone($random));
		$builder->set(6, 3, 1, $this->randomStone($random));
		$this->fillRandom($builder, $random, 7, 2, 1, 9, 2, 1);
		$builder->set(8, 1, 1, $mossy);
		$this->fillRandom($builder, $random, 10, 1, 1, 10, 3, 7);
		$this->fillRandom($builder, $random, 9, 3, 1, 9, 3, 7);
		$builder->set(9, 1, 2, $mossy);
		$builder->set(9, 1, 4, $mossy);
		$builder->set(8, 1, 5, $mossy);
		$builder->fill(7, 2, 5, 7, 3, 5, $mossy);
		$builder->set(6, 1, 5, $mossy);
		$builder->set(6, 2, 5, $this->randomStone($random));
		$builder->fill(5, 2, 5, 5, 3, 5, $mossy);
		$builder->set(4, 1, 5, $mossy);
		$this->fillRandom($builder, $random, 7, 1, 6, 7, 3, 11);
		$this->fillRandom($builder, $random, 4, 1, 6, 4, 3, 11);
		$this->fillRandom($builder, $random, 5, 3, 11, 6, 3, 11);
		$this->fillRandom($builder, $random, 8, 3, 11, 10, 3, 11);
		$this->fillRandom($builder, $random, 8, 1, 11, 10, 1, 11);
		$this->fillRandom($builder, $random, 5, 1, 8, 6, 1, 8);
		$this->fillRandom($builder, $random, 6, 1, 7, 6, 2, 7);
		$builder->set(5, 2, 7, $this->randomStone($random));
		$this->fillRandom($builder, $random, 6, 1, 6, 6, 3, 6);
		$this->fillRandom($builder, $random, 5, 2, 6, 5, 3, 6);
		$this->fillRandom($builder, $random, 8, 2, 6, 9, 2, 6);
		$builder->set(8, 3, 6, $this->randomStone($random));
		$this->fillRandom($builder, $random, 9, 1, 7, 9, 2, 7);
		$this->fillRandom($builder, $random, 8, 1, 7, 8, 3, 7);
		$this->fillRandom($builder, $random, 10, 1, 8, 10, 1, 10);
		$builder->set(10, 2, 9, $mossy);
		$this->fillRandom($builder, $random, 8, 1, 8, 8, 1, 10);
		$builder->fill(8, 2, 11, 10, 2, 11, $chiseled);
		$builder->fill(8, 2, 12, 10, 2, 12, $lever);
		$builder->set(3, 2, 2, $vineN);
		$builder->fill(8, 2, 3, 8, 3, 3, $vineE);
		$builder->fill(2, 1, 8, 3, 1, 8, $tripwire);
		$builder->set(4, 1, 8, $hookE);
		$builder->set(1, 1, 8, $hookW);
		$builder->fill(5, 1, 1, 5, 1, 7, $wire);
		$builder->set(4, 1, 1, $wire);
		$builder->fill(7, 1, 2, 7, 1, 4, $tripwire);
		$builder->set(7, 1, 1, $hookN);
		$builder->set(7, 1, 5, $hookS);
		$builder->fill(8, 1, 6, 9, 1, 6, $wire);
		$builder->set(9, 1, 5, $wire);
		$builder->set(9, 2, 4, $wire);
		$builder->set(10, 3, 9, $wire);
		$builder->fill(8, 2, 9, 8, 2, 10, $wire);
		$builder->set(10, 2, 10, $repeater);
		$builder->set(8, 1, 3, (clone VanillaBlocks::CHEST())->setFacing(Facing::WEST));
		$builder->set(9, 1, 10, (clone VanillaBlocks::CHEST())->setFacing(Facing::NORTH));

		// 2nd floor inside
		for ($i = 0; $i < 4; ++$i) {
			$builder->fill(5, 4 - $i, 6 + $i, 6, 4 - $i, 6 + $i, $stairsS);
		}
		$this->fillRandom($builder, $random, 4, 5, 10, 7, 6, 10);
		$builder->set(4, 5, 9, $this->randomStone($random));
		$builder->set(7, 5, 9, $this->randomStone($random));
		for ($i = 0; $i < 3; ++$i) {
			$builder->set(7, 5 + $i, 8 + $i, $stairsN);
			$builder->set(4, 5 + $i, 8 + $i, $stairsN);
		}

		// 3rd floor inside
		$this->fillRandom($builder, $random, 5, 8, 5, 6, 8, 5);
		$builder->set(7, 8, 5, $stairsE);
		$builder->set(4, 8, 5, $stairsW);
	}

	private function randomStone(Random $random) : Block
	{
		return $random->nextBoundedInt(10) < 4 ? VanillaBlocks::COBBLESTONE() : VanillaBlocks::MOSSY_COBBLESTONE();
	}

	private function fillRandom(StructureBuilder $builder, Random $random, int $x1, int $y1, int $z1, int $x2, int $y2, int $z2) : void
	{
		for ($y = $y1; $y <= $y2; ++$y) {
			for ($x = $x1; $x <= $x2; ++$x) {
				for ($z = $z1; $z <= $z2; ++$z) {
					$builder->set($x, $y, $z, $this->randomStone($random));
				}
			}
		}
	}
}
