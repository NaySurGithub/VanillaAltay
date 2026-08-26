<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\mineshaft;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;

use function intdiv;

final class MineshaftCorridor extends MineshaftPiece
{
	private bool $hasRails;
	private bool $spiderCorridor;
	private int $numSections;
	private bool $hasPlacedSpider = false;

	public function __construct(int $genDepth, Random $random, BoundingBox $boundingBox, ?int $orientation, bool $mesa)
	{
		parent::__construct($genDepth, $mesa);

		$this->setOrientation($orientation);
		$this->boundingBox = $boundingBox;
		$this->hasRails = $random->nextBoundedInt(3) === 0;
		$this->spiderCorridor = !$this->hasRails && $random->nextBoundedInt(23) === 0;

		if ($orientation === null || Facing::axis($orientation) === Axis::Z) {
			$this->numSections = intdiv($boundingBox->getZSpan(), 5);
		} else {
			$this->numSections = intdiv($boundingBox->getXSpan(), 5);
		}
	}

	/**
	 * @param MineshaftPiece[] $pieces
	 */
	public static function findCorridorSize(array $pieces, Random $random, int $x, int $y, int $z, ?int $orientation) : ?BoundingBox
	{
		$box = new BoundingBox($x, $y, $z, $x, $y + 2, $z);

		for ($count = $random->nextBoundedInt(3) + 2; $count > 0; --$count) {
			$i = $count * 5;

			switch ($orientation) {
				case Facing::SOUTH:
					$box->x1 = $x + 2;
					$box->z1 = $z + $i - 1;
					break;
				case Facing::WEST:
					$box->x0 = $x - ($i - 1);
					$box->z1 = $z + 2;
					break;
				case Facing::EAST:
					$box->x1 = $x + $i - 1;
					$box->z1 = $z + 2;
					break;
				case Facing::NORTH:
				default:
					$box->x1 = $x + 2;
					$box->z0 = $z - ($i - 1);
					break;
			}

			if (self::findCollisionPiece($pieces, $box) === null) {
				return $box;
			}
		}

		return null;
	}

	public function addChildren(MineshaftPiece $start, array &$pieces, Random $random) : void
	{
		$genDepth = $this->genDepth;
		$target = $random->nextBoundedInt(4);
		$orientation = $this->orientation;

		if ($orientation !== null) {
			switch ($orientation) {
				case Facing::SOUTH:
					if ($target <= 1) {
						self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0, $this->boundingBox->y0 - 1 + $random->nextBoundedInt(3), $this->boundingBox->z1 + 1, $orientation, $genDepth);
					} elseif ($target === 2) {
						self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0 - 1, $this->boundingBox->y0 - 1 + $random->nextBoundedInt(3), $this->boundingBox->z1 - 3, Facing::WEST, $genDepth);
					} else {
						self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x1 + 1, $this->boundingBox->y0 - 1 + $random->nextBoundedInt(3), $this->boundingBox->z1 - 3, Facing::EAST, $genDepth);
					}
					break;
				case Facing::WEST:
					if ($target <= 1) {
						self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0 - 1, $this->boundingBox->y0 - 1 + $random->nextBoundedInt(3), $this->boundingBox->z0, $orientation, $genDepth);
					} elseif ($target === 2) {
						self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0, $this->boundingBox->y0 - 1 + $random->nextBoundedInt(3), $this->boundingBox->z0 - 1, Facing::NORTH, $genDepth);
					} else {
						self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0, $this->boundingBox->y0 - 1 + $random->nextBoundedInt(3), $this->boundingBox->z1 + 1, Facing::SOUTH, $genDepth);
					}
					break;
				case Facing::EAST:
					if ($target <= 1) {
						self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x1 + 1, $this->boundingBox->y0 - 1 + $random->nextBoundedInt(3), $this->boundingBox->z0, $orientation, $genDepth);
					} elseif ($target === 2) {
						self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x1 - 3, $this->boundingBox->y0 - 1 + $random->nextBoundedInt(3), $this->boundingBox->z0 - 1, Facing::NORTH, $genDepth);
					} else {
						self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x1 - 3, $this->boundingBox->y0 - 1 + $random->nextBoundedInt(3), $this->boundingBox->z1 + 1, Facing::SOUTH, $genDepth);
					}
					break;
				case Facing::NORTH:
				default:
					if ($target <= 1) {
						self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0, $this->boundingBox->y0 - 1 + $random->nextBoundedInt(3), $this->boundingBox->z0 - 1, $orientation, $genDepth);
					} elseif ($target === 2) {
						self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0 - 1, $this->boundingBox->y0 - 1 + $random->nextBoundedInt(3), $this->boundingBox->z0, Facing::WEST, $genDepth);
					} else {
						self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x1 + 1, $this->boundingBox->y0 - 1 + $random->nextBoundedInt(3), $this->boundingBox->z0, Facing::EAST, $genDepth);
					}
					break;
			}
		}

		if ($genDepth >= self::MAX_DEPTH) {
			return;
		}

		if ($orientation !== Facing::NORTH && $orientation !== Facing::SOUTH) {
			for ($x = $this->boundingBox->x0 + 3; $x + 3 <= $this->boundingBox->x1; $x += 5) {
				$type = $random->nextBoundedInt(5);
				if ($type === 0) {
					self::generateAndAddPiece($start, $pieces, $random, $x, $this->boundingBox->y0, $this->boundingBox->z0 - 1, Facing::NORTH, $genDepth + 1);
				} elseif ($type === 1) {
					self::generateAndAddPiece($start, $pieces, $random, $x, $this->boundingBox->y0, $this->boundingBox->z1 + 1, Facing::SOUTH, $genDepth + 1);
				}
			}
		} else {
			for ($z = $this->boundingBox->z0 + 3; $z + 3 <= $this->boundingBox->z1; $z += 5) {
				$type = $random->nextBoundedInt(5);
				if ($type === 0) {
					self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0 - 1, $this->boundingBox->y0, $z, Facing::WEST, $genDepth + 1);
				} elseif ($type === 1) {
					self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x1 + 1, $this->boundingBox->y0, $z, Facing::EAST, $genDepth + 1);
				}
			}
		}
	}

	public function postProcess(ChunkManager $world, Random $random, BoundingBox $clip) : bool
	{
		if ($this->edgesLiquid($world, $clip)) {
			return false;
		}

		$air = VanillaBlocks::AIR();
		$cobweb = VanillaBlocks::COBWEB();
		$z1 = $this->numSections * 5 - 1;

		$this->generateBox($world, $clip, 0, 0, 0, 2, 1, $z1, $air, $air);
		$this->generateMaybeBox($world, $clip, $random, 80, 0, 2, 0, 2, 2, $z1, $air, $air, false);

		if ($this->spiderCorridor) {
			$this->generateMaybeBox($world, $clip, $random, 60, 0, 0, 0, 2, 1, $z1, $cobweb, $air, true);
		}

		for ($i = 0; $i < $this->numSections; ++$i) {
			$z = 2 + $i * 5;

			$this->placeSupport($world, $clip, $random, 0, 0, $z, 2, 2);
			$this->placeCobWeb($world, $clip, $random, 10, 0, 2, $z - 1);
			$this->placeCobWeb($world, $clip, $random, 10, 2, 2, $z - 1);
			$this->placeCobWeb($world, $clip, $random, 10, 0, 2, $z + 1);
			$this->placeCobWeb($world, $clip, $random, 10, 2, 2, $z + 1);
			$this->placeCobWeb($world, $clip, $random, 5, 0, 2, $z - 2);
			$this->placeCobWeb($world, $clip, $random, 5, 2, 2, $z - 2);
			$this->placeCobWeb($world, $clip, $random, 5, 0, 2, $z + 2);
			$this->placeCobWeb($world, $clip, $random, 5, 2, 2, $z + 2);

			if ($random->nextBoundedInt(100) === 0) {
				$this->createChest($world, $clip, 2, 0, $z - 1);
			}
			if ($random->nextBoundedInt(100) === 0) {
				$this->createChest($world, $clip, 0, 0, $z + 1);
			}

			if ($this->spiderCorridor && !$this->hasPlacedSpider) {
				$pz = $z - 1 + $random->nextBoundedInt(3);
				if ($this->isInterior($world, $clip, 1, 0, $pz)) {
					$this->hasPlacedSpider = true;
					$this->placeBlock($world, $clip, VanillaBlocks::MONSTER_SPAWNER(), 1, 0, $pz);
				}
			}
		}

		$planks = $this->getPlanksBlock();
		for ($x = 0; $x <= 2; ++$x) {
			for ($z = 0; $z <= $z1; ++$z) {
				$this->setPlanksBlock($world, $clip, $planks, $x, -1, $z);
			}
		}

		$this->placeDoubleLowerOrUpperSupport($world, $clip, 0, -1, 2);
		if ($this->numSections > 1) {
			$this->placeDoubleLowerOrUpperSupport($world, $clip, 0, -1, $z1 - 2);
		}

		if ($this->hasRails) {
			$rail = VanillaBlocks::RAIL()->setShape(self::RAIL_NORTH_SOUTH);
			for ($z = 0; $z <= $z1; ++$z) {
				$below = $this->getBlock($world, $clip, 1, -1, $z);
				if ($below->getTypeId() !== BlockTypeIds::AIR && $below->isSolid() && !$below->isTransparent()) {
					$this->maybeGenerateBlock($world, $clip, $random, $this->isInterior($world, $clip, 1, 0, $z) ? 70 : 90, 1, 0, $z, $rail);
				}
			}
		}

		return true;
	}

	private function createChest(ChunkManager $world, BoundingBox $clip, int $x, int $y, int $z) : void
	{
		$worldX = $this->getWorldX($x, $z);
		$worldY = $this->getWorldY($y);
		$worldZ = $this->getWorldZ($x, $z);

		if (!$this->canWrite($world, $clip, $worldX, $worldY, $worldZ)) {
			return;
		}

		if (
			$world->getBlockAt($worldX, $worldY, $worldZ)->getTypeId() === BlockTypeIds::AIR
			&& $world->getBlockAt($worldX, $worldY - 1, $worldZ)->getTypeId() !== BlockTypeIds::AIR
		) {
			$world->setBlockAt($worldX, $worldY, $worldZ, VanillaBlocks::CHEST());
		}
	}

	private function placeSupport(ChunkManager $world, BoundingBox $clip, Random $random, int $x0, int $y0, int $z, int $y1, int $x1) : void
	{
		if (!$this->isSupportingBox($world, $clip, $x0, $x1, $y1, $z)) {
			return;
		}

		$air = VanillaBlocks::AIR();
		$fence = $this->getFenceBlock();
		$this->generateBox($world, $clip, $x0, $y0, $z, $x0, $y1 - 1, $z, $fence, $air);
		$this->generateBox($world, $clip, $x1, $y0, $z, $x1, $y1 - 1, $z, $fence, $air);

		$planks = $this->getPlanksBlock();
		if ($random->nextBoundedInt(4) === 0) {
			$this->generateBox($world, $clip, $x0, $y1, $z, $x0, $y1, $z, $planks, $air);
			$this->generateBox($world, $clip, $x1, $y1, $z, $x1, $y1, $z, $planks, $air);
		} else {
			$this->generateBox($world, $clip, $x0, $y1, $z, $x1, $y1, $z, $planks, $air);
			$this->maybeGenerateBlock($world, $clip, $random, 5, $x0 + 1, $y1, $z - 1, VanillaBlocks::TORCH()->setFacing(Facing::SOUTH));
			$this->maybeGenerateBlock($world, $clip, $random, 5, $x0 + 1, $y1, $z + 1, VanillaBlocks::TORCH()->setFacing(Facing::NORTH));
		}
	}

	private function placeCobWeb(ChunkManager $world, BoundingBox $clip, Random $random, int $probability, int $x, int $y, int $z) : void
	{
		if (!$this->isInterior($world, $clip, $x, $y, $z)) {
			return;
		}

		if ($this->hasSturdyNeighbours($world, $clip, $this->getWorldX($x, $z), $this->getWorldY($y), $this->getWorldZ($x, $z))) {
			$this->maybeGenerateBlock($world, $clip, $random, $probability, $x, $y, $z, VanillaBlocks::COBWEB());
		}
	}

	private function hasSturdyNeighbours(ChunkManager $world, BoundingBox $clip, int $x, int $y, int $z) : bool
	{
		$sturdy = 0;

		foreach (Facing::ALL as $facing) {
			[$dx, $dy, $dz] = Facing::OFFSET[$facing];
			if ($this->getAt($world, $clip, $x + $dx, $y + $dy, $z + $dz)->isSolid() && ++$sturdy >= 2) {
				return true;
			}
		}

		return false;
	}

	private function placeDoubleLowerOrUpperSupport(ChunkManager $world, BoundingBox $clip, int $x, int $y, int $z) : void
	{
		$planks = $this->getPlanksBlock();
		$wood = $this->getWoodBlock();

		if ($this->getBlock($world, $clip, $x, $y, $z)->hasSameTypeId($planks)) {
			$this->fillPillarDownOrChainUp($world, $clip, $wood, $x, $y, $z);
		}
		if ($this->getBlock($world, $clip, $x + 2, $y, $z)->hasSameTypeId($planks)) {
			$this->fillPillarDownOrChainUp($world, $clip, $wood, $x + 2, $y, $z);
		}
	}

	private function fillPillarDownOrChainUp(ChunkManager $world, BoundingBox $clip, Block $pillar, int $x, int $y, int $z) : void
	{
		$worldX = $this->getWorldX($x, $z);
		$worldY = $this->getWorldY($y);
		$worldZ = $this->getWorldZ($x, $z);

		if (!$this->canWrite($world, $clip, $worldX, $worldY, $worldZ)) {
			return;
		}

		for ($distance = 1; $distance <= 20 && $worldY - $distance > $world->getMinY() + 1; ++$distance) {
			$ny = $worldY - $distance;
			$current = $this->getAt($world, $clip, $worldX, $ny, $worldZ);

			if ($this->isLiquid($current)) {
				return;
			}
			if ($current->getTypeId() !== BlockTypeIds::AIR) {
				if ($current->isSolid()) {
					for ($py = $ny + 1; $py < $worldY; ++$py) {
						$this->setAt($world, $clip, $worldX, $py, $worldZ, $pillar);
					}
				}

				return;
			}
		}

		for ($distance = 1; $distance <= 50 && $worldY + $distance < $world->getMaxY(); ++$distance) {
			$ny = $worldY + $distance;
			$current = $this->getAt($world, $clip, $worldX, $ny, $worldZ);

			if ($current->getTypeId() === BlockTypeIds::AIR || $this->isLiquid($current)) {
				continue;
			}

			if ($current->isSolid()) {
				$this->setAt($world, $clip, $worldX, $worldY + 1, $worldZ, $this->getFenceBlock());
				for ($py = $worldY + 2; $py < $ny; ++$py) {
					$this->setAt($world, $clip, $worldX, $py, $worldZ, VanillaBlocks::CHAIN());
				}
			}

			return;
		}
	}
}
