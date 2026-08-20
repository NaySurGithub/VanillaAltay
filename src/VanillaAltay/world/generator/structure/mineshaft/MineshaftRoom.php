<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\mineshaft;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use function min;

final class MineshaftRoom extends MineshaftPiece{

	/** @var BoundingBox[] */
	private array $childEntranceBoxes = [];

	public function __construct(int $genDepth, Random $random, int $x, int $z, bool $mesa){
		parent::__construct($genDepth, $mesa);

		$this->boundingBox = new BoundingBox(
			$x, 50, $z,
			$x + 7 + $random->nextBoundedInt(6),
			54 + $random->nextBoundedInt(6),
			$z + 7 + $random->nextBoundedInt(6)
		);
	}

	public function move(int $x, int $y, int $z) : void{
		parent::move($x, $y, $z);

		foreach($this->childEntranceBoxes as $box){
			$box->move($x, $y, $z);
		}
	}

	public function addChildren(MineshaftPiece $start, array &$pieces, Random $random) : void{
		$genDepth = $this->genDepth;
		$yOffset = $this->boundingBox->getYSpan() - 3 - 1;
		if($yOffset <= 0){
			$yOffset = 1;
		}

		for($x = 0; $x < $this->boundingBox->getXSpan(); $x += 4){
			$x += $random->nextBoundedInt($this->boundingBox->getXSpan());
			if($x + 3 > $this->boundingBox->getXSpan()){
				break;
			}

			$next = self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0 + $x, $this->boundingBox->y0 + $random->nextBoundedInt($yOffset) + 1, $this->boundingBox->z0 - 1, Facing::NORTH, $genDepth);
			if($next !== null){
				$box = $next->boundingBox;
				$this->childEntranceBoxes[] = new BoundingBox($box->x0, $box->y0, $this->boundingBox->z0, $box->x1, $box->y1, $this->boundingBox->z0 + 1);
			}
		}

		for($x = 0; $x < $this->boundingBox->getXSpan(); $x += 4){
			$x += $random->nextBoundedInt($this->boundingBox->getXSpan());
			if($x + 3 > $this->boundingBox->getXSpan()){
				break;
			}

			$next = self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0 + $x, $this->boundingBox->y0 + $random->nextBoundedInt($yOffset) + 1, $this->boundingBox->z1 + 1, Facing::SOUTH, $genDepth);
			if($next !== null){
				$box = $next->boundingBox;
				$this->childEntranceBoxes[] = new BoundingBox($box->x0, $box->y0, $this->boundingBox->z1 - 1, $box->x1, $box->y1, $this->boundingBox->z1);
			}
		}

		for($z = 0; $z < $this->boundingBox->getZSpan(); $z += 4){
			$z += $random->nextBoundedInt($this->boundingBox->getZSpan());
			if($z + 3 > $this->boundingBox->getZSpan()){
				break;
			}

			$next = self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x0 - 1, $this->boundingBox->y0 + $random->nextBoundedInt($yOffset) + 1, $this->boundingBox->z0 + $z, Facing::WEST, $genDepth);
			if($next !== null){
				$box = $next->boundingBox;
				$this->childEntranceBoxes[] = new BoundingBox($this->boundingBox->x0, $box->y0, $box->z0, $this->boundingBox->x0 + 1, $box->y1, $box->z1);
			}
		}

		for($z = 0; $z < $this->boundingBox->getZSpan(); $z += 4){
			$z += $random->nextBoundedInt($this->boundingBox->getZSpan());
			if($z + 3 > $this->boundingBox->getZSpan()){
				break;
			}

			$next = self::generateAndAddPiece($start, $pieces, $random, $this->boundingBox->x1 + 1, $this->boundingBox->y0 + $random->nextBoundedInt($yOffset) + 1, $this->boundingBox->z0 + $z, Facing::EAST, $genDepth);
			if($next !== null){
				$box = $next->boundingBox;
				$this->childEntranceBoxes[] = new BoundingBox($this->boundingBox->x1 - 1, $box->y0, $box->z0, $this->boundingBox->x1, $box->y1, $box->z1);
			}
		}
	}

	public function postProcess(ChunkManager $world, Random $random, BoundingBox $clip) : bool{
		if($this->edgesLiquid($world, $clip)){
			return false;
		}

		$air = VanillaBlocks::AIR();

		$this->generateBox($world, $clip, $this->boundingBox->x0, $this->boundingBox->y0 + 1, $this->boundingBox->z0, $this->boundingBox->x1, min($this->boundingBox->y0 + 3, $this->boundingBox->y1), $this->boundingBox->z1, $air, $air);

		foreach($this->childEntranceBoxes as $box){
			$this->generateBox($world, $clip, $box->x0, $box->y1 - 2, $box->z0, $box->x1, $box->y1, $box->z1, $air, $air);
		}

		$this->generateUpperHalfSphere($world, $clip, $this->boundingBox->x0, $this->boundingBox->y0 + 4, $this->boundingBox->z0, $this->boundingBox->x1, $this->boundingBox->y1, $this->boundingBox->z1, $air);

		return true;
	}
}
