<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\object;

use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use pocketmine\world\generator\object\Tree;

use function abs;

/**
 * Altay has no dark oak: its TreeType case falls through TreeFactory and produces nothing, which leaves roofed
 * forests bare.
 *
 * A dark oak grows on a two by two trunk and spreads a wide, flat canopy that touches the ground level of the
 * forest, which is what makes those forests dark in the first place.
 */
final class DarkOakTree extends Tree
{
	private const MIN_HEIGHT = 6;

	private const HEIGHT_RANDOM = 3;

	public function __construct()
	{
		parent::__construct(VanillaBlocks::DARK_OAK_LOG(), VanillaBlocks::DARK_OAK_LEAVES(), self::MIN_HEIGHT);
	}

	public function canPlaceObject(ChunkManager $world, int $x, int $y, int $z, Random $random) : bool
	{
		for ($xx = 0; $xx <= 1; ++$xx) {
			for ($zz = 0; $zz <= 1; ++$zz) {
				for ($yy = 0; $yy < self::MIN_HEIGHT + self::HEIGHT_RANDOM + 3; ++$yy) {
					if (!$this->canOverride($world->getBlockAt($x + $xx, $y + $yy, $z + $zz))) {
						return false;
					}
				}
			}
		}

		return true;
	}

	protected function generateTrunkHeight(Random $random) : int
	{
		return self::MIN_HEIGHT + $random->nextBoundedInt(self::HEIGHT_RANDOM);
	}

	protected function placeTrunk(int $x, int $y, int $z, Random $random, int $trunkHeight, BlockTransaction $transaction) : void
	{
		for ($xx = 0; $xx <= 1; ++$xx) {
			for ($zz = 0; $zz <= 1; ++$zz) {
				$transaction->addBlockAt($x + $xx, $y - 1, $z + $zz, VanillaBlocks::DIRT());

				for ($yy = 0; $yy < $trunkHeight; ++$yy) {
					$transaction->addBlockAt($x + $xx, $y + $yy, $z + $zz, $this->trunkBlock);
				}
			}
		}

		$this->placeDarkOakCanopy($x, $y + $trunkHeight, $z, $transaction);
	}

	protected function placeCanopy(int $x, int $y, int $z, Random $random, BlockTransaction $transaction) : void
	{
		//the canopy is placed from placeTrunk instead, since its height depends on the randomized trunk
	}

	private function placeDarkOakCanopy(int $x, int $y, int $z, BlockTransaction $transaction) : void
	{
		//a wide slab of leaves, then a smaller cap on top
		for ($yy = -1; $yy <= 0; ++$yy) {
			$radius = $yy === 0 ? 2 : 3;
			for ($xx = -$radius; $xx <= $radius + 1; ++$xx) {
				for ($zz = -$radius; $zz <= $radius + 1; ++$zz) {
					if (abs($xx) === $radius + 1 && abs($zz) === $radius + 1) {
						continue;
					}
					$this->addLeaf($transaction, $x + $xx, $y + $yy, $z + $zz);
				}
			}
		}

		for ($xx = 0; $xx <= 1; ++$xx) {
			for ($zz = 0; $zz <= 1; ++$zz) {
				$this->addLeaf($transaction, $x + $xx, $y + 1, $z + $zz);
			}
		}
	}

	private function addLeaf(BlockTransaction $transaction, int $x, int $y, int $z) : void
	{
		if ($this->canOverride($transaction->fetchBlockAt($x, $y, $z))) {
			$transaction->addBlockAt($x, $y, $z, $this->leafBlock);
		}
	}
}
