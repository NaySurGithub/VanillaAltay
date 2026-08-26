<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\mineshaft;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;

final class MineshaftStairs extends MineshaftPiece
{
	public function __construct(int $genDepth, BoundingBox $boundingBox, ?int $orientation, bool $mesa)
	{
		parent::__construct($genDepth, $mesa);

		$this->setOrientation($orientation);
		$this->boundingBox = $boundingBox;
	}

	/**
	 * @param MineshaftPiece[] $pieces
	 */
	public static function findStairs(array $pieces, int $x, int $y, int $z, ?int $orientation) : ?BoundingBox
	{
		$box = new BoundingBox($x, $y - 5, $z, $x, $y + 2, $z);

		switch ($orientation) {
			case Facing::SOUTH:
				$box->x1 = $x + 2;
				$box->z1 = $z + 8;
				break;
			case Facing::WEST:
				$box->x0 = $x - 8;
				$box->z1 = $z + 2;
				break;
			case Facing::EAST:
				$box->x1 = $x + 8;
				$box->z1 = $z + 2;
				break;
			case Facing::NORTH:
			default:
				$box->x1 = $x + 2;
				$box->z0 = $z - 8;
				break;
		}

		return self::findCollisionPiece($pieces, $box) === null ? $box : null;
	}

	public function addChildren(MineshaftPiece $start, array &$pieces, Random $random) : void
	{
		$genDepth = $this->genDepth;

		switch ($this->orientation) {
			case null:
				return;
			case Facing::SOUTH:
				self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0, $this->boundingBox->y0, $this->boundingBox->z1 + 1, Facing::SOUTH, $genDepth);
				break;
			case Facing::WEST:
				self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0 - 1, $this->boundingBox->y0, $this->boundingBox->z0, Facing::WEST, $genDepth);
				break;
			case Facing::EAST:
				self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x1 + 1, $this->boundingBox->y0, $this->boundingBox->z0, Facing::EAST, $genDepth);
				break;
			case Facing::NORTH:
			default:
				self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0, $this->boundingBox->y0, $this->boundingBox->z0 - 1, Facing::NORTH, $genDepth);
				break;
		}
	}

	public function postProcess(ChunkManager $world, Random $random, BoundingBox $clip) : bool
	{
		if ($this->edgesLiquid($world, $clip)) {
			return false;
		}

		$air = VanillaBlocks::AIR();

		$this->generateBox($world, $clip, 0, 5, 0, 2, 7, 1, $air, $air);
		$this->generateBox($world, $clip, 0, 0, 7, 2, 2, 8, $air, $air);

		for ($i = 0; $i < 5; ++$i) {
			$this->generateBox($world, $clip, 0, 5 - $i - ($i < 4 ? 1 : 0), 2 + $i, 2, 7 - $i, 2 + $i, $air, $air);
		}

		return true;
	}
}
