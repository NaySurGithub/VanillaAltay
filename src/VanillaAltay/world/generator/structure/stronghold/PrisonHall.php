<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\stronghold;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use VanillaAltay\world\generator\structure\mineshaft\BoundingBox;

final class PrisonHall extends StrongholdPiece{

	public function __construct(int $genDepth, Random $random, BoundingBox $boundingBox, ?int $orientation){
		parent::__construct($genDepth);

		$this->setOrientation($orientation);
		$this->entryDoor = self::randomSmallDoor($random);
		$this->boundingBox = $boundingBox;
	}

	/**
	 * @param StrongholdPiece[] $pieces
	 */
	public static function createPiece(array $pieces, Random $random, int $x, int $y, int $z, ?int $orientation, int $genDepth) : ?self{
		$box = self::orientBox($x, $y, $z, -1, -1, 0, 9, 5, 11, $orientation);

		return self::isOkBox($box) && self::findCollisionPiece($pieces, $box) === null ? new self($genDepth, $random, $box, $orientation) : null;
	}

	public function addChildren(PieceGenerator $generator, Random $random) : void{
		$this->generateSmallDoorChildForward($generator, $random, 1, 1);
	}

	public function postProcess(ChunkManager $world, Random $random, BoundingBox $clip) : bool{
		$this->generateBoxSelector($world, $clip, $random, 0, 0, 0, 8, 4, 10);
		$this->generateSmallDoor($world, $clip, $this->entryDoor, 1, 1, 0);

		$air = VanillaBlocks::AIR();
		$this->generateBox($world, $clip, 1, 1, 10, 3, 3, 10, $air, $air);

		$this->generateBoxSelector($world, $clip, $random, 4, 1, 1, 4, 3, 1);
		$this->generateBoxSelector($world, $clip, $random, 4, 1, 3, 4, 3, 3);
		$this->generateBoxSelector($world, $clip, $random, 4, 1, 7, 4, 3, 7);
		$this->generateBoxSelector($world, $clip, $random, 4, 1, 9, 4, 3, 9);

		$bars = VanillaBlocks::IRON_BARS();
		for($y = 1; $y <= 3; ++$y){
			$this->placeBlock($world, $clip, $bars, 4, $y, 4);
			$this->placeBlock($world, $clip, $bars, 4, $y, 5);
			$this->placeBlock($world, $clip, $bars, 4, $y, 6);
			$this->placeBlock($world, $clip, $bars, 5, $y, 5);
			$this->placeBlock($world, $clip, $bars, 6, $y, 5);
			$this->placeBlock($world, $clip, $bars, 7, $y, 5);
		}

		$this->placeBlock($world, $clip, $bars, 4, 3, 2);
		$this->placeBlock($world, $clip, $bars, 4, 3, 8);

		$door = VanillaBlocks::IRON_DOOR()->setFacing(Facing::WEST);
		$doorTop = VanillaBlocks::IRON_DOOR()->setFacing(Facing::WEST)->setTop(true);

		$this->placeBlock($world, $clip, $door, 4, 1, 2);
		$this->placeBlock($world, $clip, $doorTop, 4, 2, 2);
		$this->placeBlock($world, $clip, $door, 4, 1, 8);
		$this->placeBlock($world, $clip, $doorTop, 4, 2, 8);

		return true;
	}
}
