<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\ChunkManager;

/**
 * Builds a structure from coordinates relative to its origin, so a shape can be written the way it is laid out
 * on paper rather than in world coordinates.
 */
final class StructureBuilder{

	public function __construct(
		private ChunkManager $world,
		private int $originX,
		private int $originY,
		private int $originZ
	){}

	public function set(int $x, int $y, int $z, Block $block) : void{
		$this->world->setBlockAt($this->originX + $x, $this->originY + $y, $this->originZ + $z, $block);
	}

	public function get(int $x, int $y, int $z) : Block{
		return $this->world->getBlockAt($this->originX + $x, $this->originY + $y, $this->originZ + $z);
	}

	public function fill(int $x1, int $y1, int $z1, int $x2, int $y2, int $z2, Block $block) : void{
		for($x = $x1; $x <= $x2; ++$x){
			for($y = $y1; $y <= $y2; ++$y){
				for($z = $z1; $z <= $z2; ++$z){
					$this->set($x, $y, $z, $block);
				}
			}
		}
	}

	/**
	 * Fills the shell of a box and leaves its inside untouched.
	 */
	public function fillHollow(int $x1, int $y1, int $z1, int $x2, int $y2, int $z2, Block $block) : void{
		for($x = $x1; $x <= $x2; ++$x){
			for($y = $y1; $y <= $y2; ++$y){
				for($z = $z1; $z <= $z2; ++$z){
					if($x === $x1 || $x === $x2 || $y === $y1 || $y === $y2 || $z === $z1 || $z === $z2){
						$this->set($x, $y, $z, $block);
					}
				}
			}
		}
	}

	/**
	 * Drops a pillar from the given position until it reaches solid ground, which is what keeps a hut standing
	 * over uneven terrain instead of floating.
	 */
	public function fillDownward(int $x, int $y, int $z, Block $block, int $limit = 16) : void{
		for($i = 0; $i < $limit; ++$i){
			$current = $this->get($x, $y - $i, $z);
			if($current->getTypeId() !== BlockTypeIds::AIR && $current->getTypeId() !== BlockTypeIds::WATER && $current->isSolid()){
				return;
			}

			$this->set($x, $y - $i, $z, $block);
		}
	}

	public function clear(int $x1, int $y1, int $z1, int $x2, int $y2, int $z2) : void{
		$this->fill($x1, $y1, $z1, $x2, $y2, $z2, VanillaBlocks::AIR());
	}
}
