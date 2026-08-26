<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\mineshaft;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;

final class MineshaftCrossing extends MineshaftPiece
{
	private bool $isTwoFloored;

	public function __construct(int $genDepth, BoundingBox $boundingBox, private ?int $direction, bool $mesa)
	{
		parent::__construct($genDepth, $mesa);

		$this->boundingBox = $boundingBox;
		$this->isTwoFloored = $boundingBox->getYSpan() > 3;
	}

	/**
	 * @param MineshaftPiece[] $pieces
	 */
	public static function findCrossing(array $pieces, Random $random, int $x, int $y, int $z, ?int $orientation) : ?BoundingBox
	{
		$box = new BoundingBox($x, $y, $z, $x, $y + 2, $z);
		if ($random->nextBoundedInt(4) === 0) {
			$box->y1 += 4;
		}

		switch ($orientation) {
			case Facing::SOUTH:
				$box->x0 = $x - 1;
				$box->x1 = $x + 3;
				$box->z1 = $z + 4;
				break;
			case Facing::WEST:
				$box->x0 = $x - 4;
				$box->z0 = $z - 1;
				$box->z1 = $z + 3;
				break;
			case Facing::EAST:
				$box->x1 = $x + 4;
				$box->z0 = $z - 1;
				$box->z1 = $z + 3;
				break;
			case Facing::NORTH:
			default:
				$box->x0 = $x - 1;
				$box->x1 = $x + 3;
				$box->z0 = $z - 4;
				break;
		}

		return self::findCollisionPiece($pieces, $box) === null ? $box : null;
	}

	public function addChildren(MineshaftPiece $start, array &$pieces, Random $random) : void
	{
		$genDepth = $this->genDepth;

		switch ($this->direction) {
			case Facing::SOUTH:
				self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0 + 1, $this->boundingBox->y0, $this->boundingBox->z1 + 1, Facing::SOUTH, $genDepth);
				self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0 - 1, $this->boundingBox->y0, $this->boundingBox->z0 + 1, Facing::WEST, $genDepth);
				self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x1 + 1, $this->boundingBox->y0, $this->boundingBox->z0 + 1, Facing::EAST, $genDepth);
				break;
			case Facing::WEST:
				self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0 + 1, $this->boundingBox->y0, $this->boundingBox->z0 - 1, Facing::NORTH, $genDepth);
				self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0 + 1, $this->boundingBox->y0, $this->boundingBox->z1 + 1, Facing::SOUTH, $genDepth);
				self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0 - 1, $this->boundingBox->y0, $this->boundingBox->z0 + 1, Facing::WEST, $genDepth);
				break;
			case Facing::EAST:
				self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0 + 1, $this->boundingBox->y0, $this->boundingBox->z0 - 1, Facing::NORTH, $genDepth);
				self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0 + 1, $this->boundingBox->y0, $this->boundingBox->z1 + 1, Facing::SOUTH, $genDepth);
				self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x1 + 1, $this->boundingBox->y0, $this->boundingBox->z0 + 1, Facing::EAST, $genDepth);
				break;
			case Facing::NORTH:
			default:
				self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0 + 1, $this->boundingBox->y0, $this->boundingBox->z0 - 1, Facing::NORTH, $genDepth);
				self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0 - 1, $this->boundingBox->y0, $this->boundingBox->z0 + 1, Facing::WEST, $genDepth);
				self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x1 + 1, $this->boundingBox->y0, $this->boundingBox->z0 + 1, Facing::EAST, $genDepth);
				break;
		}

		if (!$this->isTwoFloored) {
			return;
		}

		if ($random->nextBoolean()) {
			self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0 + 1, $this->boundingBox->y0 + 4, $this->boundingBox->z0 - 1, Facing::NORTH, $genDepth);
		}
		if ($random->nextBoolean()) {
			self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0 - 1, $this->boundingBox->y0 + 4, $this->boundingBox->z0 + 1, Facing::WEST, $genDepth);
		}
		if ($random->nextBoolean()) {
			self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x1 + 1, $this->boundingBox->y0 + 4, $this->boundingBox->z0 + 1, Facing::EAST, $genDepth);
		}
		if ($random->nextBoolean()) {
			self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0 + 1, $this->boundingBox->y0 + 4, $this->boundingBox->z1 + 1, Facing::SOUTH, $genDepth);
		}
	}

	public function postProcess(ChunkManager $world, Random $random, BoundingBox $clip) : bool
	{
		if ($this->edgesLiquid($world, $clip)) {
			return false;
		}

		$air = VanillaBlocks::AIR();
		$box = $this->boundingBox;

		if ($this->isTwoFloored) {
			$this->generateBox($world, $clip, $box->x0 + 1, $box->y0, $box->z0, $box->x1 - 1, $box->y0 + 2, $box->z1, $air, $air);
			$this->generateBox($world, $clip, $box->x0, $box->y0, $box->z0 + 1, $box->x1, $box->y0 + 2, $box->z1 - 1, $air, $air);
			$this->generateBox($world, $clip, $box->x0 + 1, $box->y1 - 2, $box->z0, $box->x1 - 1, $box->y1, $box->z1, $air, $air);
			$this->generateBox($world, $clip, $box->x0, $box->y1 - 2, $box->z0 + 1, $box->x1, $box->y1, $box->z1 - 1, $air, $air);
			$this->generateBox($world, $clip, $box->x0 + 1, $box->y0 + 3, $box->z0 + 1, $box->x1 - 1, $box->y0 + 3, $box->z1 - 1, $air, $air);
		} else {
			$this->generateBox($world, $clip, $box->x0 + 1, $box->y0, $box->z0, $box->x1 - 1, $box->y1, $box->z1, $air, $air);
			$this->generateBox($world, $clip, $box->x0, $box->y0, $box->z0 + 1, $box->x1, $box->y1, $box->z1 - 1, $air, $air);
		}

		$this->placeSupportPillar($world, $clip, $box->x0 + 1, $box->y0, $box->z0 + 1, $box->y1);
		$this->placeSupportPillar($world, $clip, $box->x0 + 1, $box->y0, $box->z1 - 1, $box->y1);
		$this->placeSupportPillar($world, $clip, $box->x1 - 1, $box->y0, $box->z0 + 1, $box->y1);
		$this->placeSupportPillar($world, $clip, $box->x1 - 1, $box->y0, $box->z1 - 1, $box->y1);

		$planks = $this->getPlanksBlock();
		for ($x = 0; $x < $box->getXSpan(); ++$x) {
			for ($z = 0; $z < $box->getZSpan(); ++$z) {
				$this->setPlanksBlock($world, $clip, $planks, $x, -1, $z);
			}
		}

		return true;
	}

	private function placeSupportPillar(ChunkManager $world, BoundingBox $clip, int $x, int $y0, int $z, int $y1) : void
	{
		if ($this->getBlock($world, $clip, $x, $y1 + 1, $z)->getTypeId() !== BlockTypeIds::AIR) {
			$this->generateBox($world, $clip, $x, $y0, $z, $x, $y1, $z, $this->getPlanksBlock(), VanillaBlocks::AIR());
		}
	}
}
