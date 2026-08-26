<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\stronghold;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use VanillaAltay\world\generator\structure\mineshaft\BoundingBox;

final class FillerCorridor extends StrongholdPiece
{
	private int $steps;

	public function __construct(int $genDepth, BoundingBox $boundingBox, ?int $orientation)
	{
		parent::__construct($genDepth);

		$this->setOrientation($orientation);
		$this->boundingBox = $boundingBox;
		$this->steps = $orientation !== Facing::NORTH && $orientation !== Facing::SOUTH ? $boundingBox->getXSpan() : $boundingBox->getZSpan();
	}

	/**
	 * @param StrongholdPiece[] $pieces
	 */
	public static function findPieceBox(array $pieces, int $x, int $y, int $z, ?int $orientation) : ?BoundingBox
	{
		$box = self::orientBox($x, $y, $z, -1, -1, 0, 5, 5, 4, $orientation);
		$piece = self::findCollisionPiece($pieces, $box);
		if ($piece === null || $piece->boundingBox->y0 !== $box->y0) {
			return null;
		}

		for ($length = 3; $length >= 1; --$length) {
			$box = self::orientBox($x, $y, $z, -1, -1, 0, 5, 5, $length - 1, $orientation);
			if (!$piece->boundingBox->intersects($box)) {
				return self::orientBox($x, $y, $z, -1, -1, 0, 5, 5, $length, $orientation);
			}
		}

		return null;
	}

	public function postProcess(ChunkManager $world, Random $random, BoundingBox $clip) : bool
	{
		$bricks = VanillaBlocks::STONE_BRICKS();
		$air = VanillaBlocks::AIR();

		for ($z = 0; $z < $this->steps; ++$z) {
			for ($x = 0; $x <= 4; ++$x) {
				$this->placeBlock($world, $clip, $bricks, $x, 0, $z);
				$this->placeBlock($world, $clip, $bricks, $x, 4, $z);
			}

			for ($y = 1; $y <= 3; ++$y) {
				$this->placeBlock($world, $clip, $bricks, 0, $y, $z);
				$this->placeBlock($world, $clip, $air, 1, $y, $z);
				$this->placeBlock($world, $clip, $air, 2, $y, $z);
				$this->placeBlock($world, $clip, $air, 3, $y, $z);
				$this->placeBlock($world, $clip, $bricks, 4, $y, $z);
			}
		}

		return true;
	}
}
