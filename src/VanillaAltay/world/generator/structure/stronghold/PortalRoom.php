<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\stronghold;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use VanillaAltay\world\generator\structure\mineshaft\BoundingBox;

final class PortalRoom extends StrongholdPiece{

	public function __construct(int $genDepth, BoundingBox $boundingBox, ?int $orientation){
		parent::__construct($genDepth);

		$this->setOrientation($orientation);
		$this->boundingBox = $boundingBox;
	}

	/**
	 * @param StrongholdPiece[] $pieces
	 */
	public static function createPiece(array $pieces, Random $random, int $x, int $y, int $z, ?int $orientation, int $genDepth) : ?self{
		$box = self::orientBox($x, $y, $z, -4, -1, 0, 11, 8, 16, $orientation);

		return self::isOkBox($box) && self::findCollisionPiece($pieces, $box) === null ? new self($genDepth, $box, $orientation) : null;
	}

	public function addChildren(PieceGenerator $generator, Random $random) : void{
		$generator->portalRoom = $this;
	}

	public function postProcess(ChunkManager $world, Random $random, BoundingBox $clip) : bool{
		$this->generateBoxSelector($world, $clip, $random, 0, 0, 0, 10, 7, 15);
		$this->generateSmallDoor($world, $clip, self::DOOR_GRATES, 4, 1, 0);

		$this->generateBoxSelector($world, $clip, $random, 1, 6, 1, 1, 6, 14);
		$this->generateBoxSelector($world, $clip, $random, 9, 6, 1, 9, 6, 14);
		$this->generateBoxSelector($world, $clip, $random, 2, 6, 1, 8, 6, 2);
		$this->generateBoxSelector($world, $clip, $random, 2, 6, 14, 8, 6, 14);
		$this->generateBoxSelector($world, $clip, $random, 1, 1, 1, 2, 1, 4);
		$this->generateBoxSelector($world, $clip, $random, 8, 1, 1, 9, 1, 4);

		$lava = VanillaBlocks::LAVA();
		$this->generateBox($world, $clip, 1, 1, 1, 1, 1, 3, $lava, $lava);
		$this->generateBox($world, $clip, 9, 1, 1, 9, 1, 3, $lava, $lava);

		$this->generateBoxSelector($world, $clip, $random, 3, 1, 8, 7, 1, 12);

		$this->generateBox($world, $clip, 4, 1, 9, 6, 1, 11, $lava, $lava);

		$bars = VanillaBlocks::IRON_BARS();
		for($z = 3; $z < 14; $z += 2){
			$this->generateBox($world, $clip, 0, 3, $z, 0, 4, $z, $bars, $bars);
			$this->generateBox($world, $clip, 10, 3, $z, 10, 4, $z, $bars, $bars);
		}
		for($x = 2; $x < 9; $x += 2){
			$this->generateBox($world, $clip, $x, 3, 15, $x, 4, 15, $bars, $bars);
		}

		$this->generateBoxSelector($world, $clip, $random, 4, 1, 5, 6, 1, 7);
		$this->generateBoxSelector($world, $clip, $random, 4, 2, 6, 6, 2, 7);
		$this->generateBoxSelector($world, $clip, $random, 4, 3, 7, 6, 3, 7);

		$stairs = VanillaBlocks::STONE_BRICK_STAIRS()->setFacing(Facing::NORTH);
		for($x = 4; $x <= 6; ++$x){
			$this->placeBlock($world, $clip, $stairs, $x, 1, 4);
			$this->placeBlock($world, $clip, $stairs, $x, 2, 5);
			$this->placeBlock($world, $clip, $stairs, $x, 3, 6);
		}

		$hasEye = [];
		for($i = 0; $i < 12; ++$i){
			$hasEye[$i] = $random->nextBoundedInt(100) > 90;
		}

		$this->placeFrame($world, $clip, $hasEye[0], Facing::NORTH, 4, 3, 8);
		$this->placeFrame($world, $clip, $hasEye[1], Facing::NORTH, 5, 3, 8);
		$this->placeFrame($world, $clip, $hasEye[2], Facing::NORTH, 6, 3, 8);
		$this->placeFrame($world, $clip, $hasEye[3], Facing::SOUTH, 4, 3, 12);
		$this->placeFrame($world, $clip, $hasEye[4], Facing::SOUTH, 5, 3, 12);
		$this->placeFrame($world, $clip, $hasEye[5], Facing::SOUTH, 6, 3, 12);
		$this->placeFrame($world, $clip, $hasEye[6], Facing::EAST, 3, 3, 9);
		$this->placeFrame($world, $clip, $hasEye[7], Facing::EAST, 3, 3, 10);
		$this->placeFrame($world, $clip, $hasEye[8], Facing::EAST, 3, 3, 11);
		$this->placeFrame($world, $clip, $hasEye[9], Facing::WEST, 7, 3, 9);
		$this->placeFrame($world, $clip, $hasEye[10], Facing::WEST, 7, 3, 10);
		$this->placeFrame($world, $clip, $hasEye[11], Facing::WEST, 7, 3, 11);

		$this->placeBlock($world, $clip, VanillaBlocks::MONSTER_SPAWNER(), 5, 3, 6);

		return true;
	}

	private function placeFrame(ChunkManager $world, BoundingBox $clip, bool $eye, int $facing, int $x, int $y, int $z) : void{
		$this->placeBlock($world, $clip, VanillaBlocks::END_PORTAL_FRAME()->setFacing($facing)->setEye($eye), $x, $y, $z);
	}
}
