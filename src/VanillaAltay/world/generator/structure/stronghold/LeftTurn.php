<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\stronghold;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use VanillaAltay\world\generator\structure\mineshaft\BoundingBox;

final class LeftTurn extends StrongholdPiece
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
		$box = self::orientBox($x, $y, $z, -1, -1, 0, 5, 5, 5, $orientation);

		return self::isOkBox($box) && self::findCollisionPiece($pieces, $box) === null ? new self($genDepth, $random, $box, $orientation) : null;
	}

	public function addChildren(PieceGenerator $generator, Random $random) : void
	{
		if ($this->orientation !== Facing::NORTH && $this->orientation !== Facing::EAST) {
			$this->generateSmallDoorChildRight($generator, $random, 1, 1);
		} else {
			$this->generateSmallDoorChildLeft($generator, $random, 1, 1);
		}
	}

	public function postProcess(ChunkManager $world, Random $random, BoundingBox $clip) : bool
	{
		$this->generateBoxSelector($world, $clip, $random, 0, 0, 0, 4, 4, 4);
		$this->generateSmallDoor($world, $clip, $this->entryDoor, 1, 1, 0);

		$air = VanillaBlocks::AIR();
		if ($this->orientation !== Facing::NORTH && $this->orientation !== Facing::EAST) {
			$this->generateBox($world, $clip, 4, 1, 1, 4, 3, 3, $air, $air);
		} else {
			$this->generateBox($world, $clip, 0, 1, 1, 0, 3, 3, $air, $air);
		}

		return true;
	}
}
