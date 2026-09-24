<?php

declare(strict_types=1);

namespace dimension\generator\structure\fortress;

use dimension\generator\math\Int64;
use dimension\generator\object\BlockManager;
use dimension\generator\random\RandomSource;
use dimension\generator\random\Xoroshiro128;
use dimension\generator\structure\BoundingBox;
use dimension\generator\structure\StructurePiece;
use function array_splice;
use function array_values;
use function count;

/**
 * The pieces of one nether fortress, laid out from its start chunk and the
 * world seed only, then lifted to a random height between 48 and 70.
 */
final class FortressLayout{

	/** @var list<StructurePiece> */
	private array $pieces = [];
	private BoundingBox $boundingBox;

	public function __construct(int $seed, int $chunkX, int $chunkZ){
		$random = new Xoroshiro128($seed);
		$first = Int64::mul($chunkX, $random->nextInt());
		$second = Int64::mul($chunkZ, $random->nextInt());
		$random->setSeed($first ^ $second ^ $seed);
		$this->boundingBox = BoundingBox::unknown();
		$this->generatePieces($random, $chunkX, $chunkZ);
	}

	private function generatePieces(RandomSource $random, int $chunkX, int $chunkZ) : void{
		$start = new FortressStartPiece($random, ($chunkX << 4) + 2, ($chunkZ << 4) + 2);
		$pieces = [$start];
		$start->addChildren($start, $pieces, $random);

		while(count($start->pendingChildren) > 0){
			$index = $random->nextBoundedInt(count($start->pendingChildren) - 1);
			$piece = $start->pendingChildren[$index];
			array_splice($start->pendingChildren, $index, 1);
			if($piece instanceof FortressPiece){
				$piece->addChildren($start, $pieces, $random);
			}
		}

		$this->pieces = $pieces;
		$this->calculateBoundingBox();
		$this->moveInsideHeights($random, 48, 70);
	}

	public function isValid() : bool{
		return count($this->pieces) > 0;
	}

	public function getBoundingBox() : BoundingBox{
		return $this->boundingBox;
	}

	/**
	 * @return list<StructurePiece>
	 */
	public function getPieces() : array{
		return $this->pieces;
	}

	/**
	 * Writes the pieces crossing $boundingBox, dropping the ones that ask to
	 * be removed.
	 */
	public function postProcess(BlockManager $level, RandomSource $random, BoundingBox $boundingBox, int $chunkX, int $chunkZ) : void{
		$kept = [];
		foreach($this->pieces as $piece){
			if($piece->getBoundingBox()->intersects($boundingBox) && !$piece->postProcess($level, $random, $boundingBox, $chunkX, $chunkZ)){
				continue;
			}
			$kept[] = $piece;
		}
		if(count($kept) !== count($this->pieces)){
			$this->pieces = array_values($kept);
			$this->calculateBoundingBox();
		}
	}

	private function calculateBoundingBox() : void{
		$this->boundingBox = BoundingBox::unknown();
		foreach($this->pieces as $piece){
			$this->boundingBox->expand($piece->getBoundingBox());
		}
	}

	private function moveInsideHeights(RandomSource $random, int $min, int $max) : void{
		$range = $max - $min + 1 - $this->boundingBox->getYSpan();
		if($range > 1){
			$y = $min + $random->nextBoundedInt($range);
		}else{
			$y = $min;
		}
		$offset = $y - $this->boundingBox->y0;
		$this->boundingBox->move(0, $offset, 0);
		foreach($this->pieces as $piece){
			$piece->move(0, $offset, 0);
		}
	}
}
