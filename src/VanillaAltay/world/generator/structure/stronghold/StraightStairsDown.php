<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\stronghold;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use VanillaAltay\world\generator\structure\mineshaft\BoundingBox;

final class StraightStairsDown extends StrongholdPiece
{
	public function __construct(int $genDepth, Random $random, BoundingBox $boundingBox, ?int $orientation)
	{
		parent::__construct($genDepth);

		$this->setOrientation($orientation);
		$this->entryDoor = self::randomSmallDoor($random);
		$this->boundingBox = $boundingBox;
	}

	/**
	 * @param StrongholdPiece[] $pieces
	 */
	public static function createPiece(array $pieces, Random $random, int $x, int $y, int $z, ?int $orientation, int $genDepth) : ?self
	{
		$box = self::orientBox($x, $y, $z, -1, -7, 0, 5, 11, 8, $orientation);

		return self::isOkBox($box) && self::findCollisionPiece($pieces, $box) === null ? new self($genDepth, $random, $box, $orientation) : null;
	}

	public function addChildren(PieceGenerator $generator, Random $random) : void
	{
		$this->generateSmallDoorChildForward($generator, $random, 1, 1);
	}

	public function postProcess(ChunkManager $world, Random $random, BoundingBox $clip) : bool
	{
		$this->generateBoxSelector($world, $clip, $random, 0, 0, 0, 4, 10, 7);
		$this->generateSmallDoor($world, $clip, $this->entryDoor, 1, 7, 0);
		$this->generateSmallDoor($world, $clip, self::DOOR_OPENING, 1, 1, 7);

		$stairs = VanillaBlocks::STONE_STAIRS()->setFacing(Facing::SOUTH);
		$bricks = VanillaBlocks::STONE_BRICKS();

		for ($i = 0; $i < 6; ++$i) {
			$this->placeBlock($world, $clip, $stairs, 1, 6 - $i, 1 + $i);
			$this->placeBlock($world, $clip, $stairs, 2, 6 - $i, 1 + $i);
			$this->placeBlock($world, $clip, $stairs, 3, 6 - $i, 1 + $i);

			if ($i < 5) {
				$this->placeBlock($world, $clip, $bricks, 1, 5 - $i, 1 + $i);
				$this->placeBlock($world, $clip, $bricks, 2, 5 - $i, 1 + $i);
				$this->placeBlock($world, $clip, $bricks, 3, 5 - $i, 1 + $i);
			}
		}

		return true;
	}
}
