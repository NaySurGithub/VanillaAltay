<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\stronghold;

use pocketmine\block\utils\SlabType;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use VanillaAltay\world\generator\structure\mineshaft\BoundingBox;

final class FiveCrossing extends StrongholdPiece
{
	private bool $leftLow;

	private bool $leftHigh;

	private bool $rightLow;

	private bool $rightHigh;

	public function __construct(int $genDepth, Random $random, BoundingBox $boundingBox, ?int $orientation)
	{
		parent::__construct($genDepth);

		$this->setOrientation($orientation);
		$this->entryDoor = self::randomSmallDoor($random);
		$this->boundingBox = $boundingBox;
		$this->leftLow = $random->nextBoolean();
		$this->leftHigh = $random->nextBoolean();
		$this->rightLow = $random->nextBoolean();
		$this->rightHigh = $random->nextBoundedInt(3) > 0;
	}

	/**
	 * @param StrongholdPiece[] $pieces
	 */
	public static function createPiece(array $pieces, Random $random, int $x, int $y, int $z, ?int $orientation, int $genDepth) : ?self
	{
		$box = self::orientBox($x, $y, $z, -4, -3, 0, 10, 9, 11, $orientation);

		return self::isOkBox($box) && self::findCollisionPiece($pieces, $box) === null ? new self($genDepth, $random, $box, $orientation) : null;
	}

	public function addChildren(PieceGenerator $generator, Random $random) : void
	{
		$lowX = 3;
		$highX = 5;

		if ($this->orientation === Facing::WEST || $this->orientation === Facing::NORTH) {
			$lowX = 8 - $lowX;
			$highX = 8 - $highX;
		}

		$this->generateSmallDoorChildForward($generator, $random, 5, 1);

		if ($this->leftLow) {
			$this->generateSmallDoorChildLeft($generator, $random, $lowX, 1);
		}
		if ($this->leftHigh) {
			$this->generateSmallDoorChildLeft($generator, $random, $highX, 7);
		}
		if ($this->rightLow) {
			$this->generateSmallDoorChildRight($generator, $random, $lowX, 1);
		}
		if ($this->rightHigh) {
			$this->generateSmallDoorChildRight($generator, $random, $highX, 7);
		}
	}

	public function postProcess(ChunkManager $world, Random $random, BoundingBox $clip) : bool
	{
		$this->generateBoxSelector($world, $clip, $random, 0, 0, 0, 9, 8, 10);
		$this->generateSmallDoor($world, $clip, $this->entryDoor, 4, 3, 0);

		$air = VanillaBlocks::AIR();
		if ($this->leftLow) {
			$this->generateBox($world, $clip, 0, 3, 1, 0, 5, 3, $air, $air);
		}
		if ($this->rightLow) {
			$this->generateBox($world, $clip, 9, 3, 1, 9, 5, 3, $air, $air);
		}
		if ($this->leftHigh) {
			$this->generateBox($world, $clip, 0, 5, 7, 0, 7, 9, $air, $air);
		}
		if ($this->rightHigh) {
			$this->generateBox($world, $clip, 9, 5, 7, 9, 7, 9, $air, $air);
		}

		$this->generateBox($world, $clip, 5, 1, 10, 7, 3, 10, $air, $air);

		$this->generateBoxSelector($world, $clip, $random, 1, 2, 1, 8, 2, 6);
		$this->generateBoxSelector($world, $clip, $random, 4, 1, 5, 4, 4, 9);
		$this->generateBoxSelector($world, $clip, $random, 8, 1, 5, 8, 4, 9);
		$this->generateBoxSelector($world, $clip, $random, 1, 4, 7, 3, 4, 9);
		$this->generateBoxSelector($world, $clip, $random, 1, 3, 5, 3, 3, 6);

		$slab = VanillaBlocks::STONE_BRICK_SLAB();
		$this->generateBox($world, $clip, 1, 3, 4, 3, 3, 4, $slab, $slab);
		$this->generateBox($world, $clip, 1, 4, 6, 3, 4, 6, $slab, $slab);

		$this->generateBoxSelector($world, $clip, $random, 5, 1, 7, 7, 1, 8);

		$this->generateBox($world, $clip, 5, 1, 9, 7, 1, 9, $slab, $slab);
		$this->generateBox($world, $clip, 5, 2, 7, 7, 2, 7, $slab, $slab);
		$this->generateBox($world, $clip, 4, 5, 7, 4, 5, 9, $slab, $slab);
		$this->generateBox($world, $clip, 8, 5, 7, 8, 5, 9, $slab, $slab);

		$double = VanillaBlocks::SMOOTH_STONE_SLAB()->setSlabType(SlabType::DOUBLE);
		$this->generateBox($world, $clip, 5, 5, 7, 7, 5, 9, $double, $double);

		$this->placeBlock($world, $clip, VanillaBlocks::TORCH()->setFacing(Facing::SOUTH), 6, 5, 6);

		return true;
	}
}
