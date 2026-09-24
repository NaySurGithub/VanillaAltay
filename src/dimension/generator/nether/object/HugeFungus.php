<?php

declare(strict_types=1);

namespace dimension\generator\nether\object;

use dimension\generator\object\BlockManager;
use dimension\generator\random\Xoroshiro128;
use function abs;
use function in_array;

/**
 * Crimson or warped fungus tree: a stem, a wart block cap with shroomlights
 * and wart blocks hanging from its rim.
 */
final class HugeFungus{

	private const SHROOMLIGHT = "minecraft:shroomlight";

	private const OVERRIDABLE = [
		"minecraft:air",
		"minecraft:acacia_leaves",
		"minecraft:azalea_leaves",
		"minecraft:birch_leaves",
		"minecraft:azalea_leaves_flowered",
		"minecraft:cherry_leaves",
		"minecraft:dark_oak_leaves",
		"minecraft:jungle_leaves",
		"minecraft:mangrove_leaves",
		"minecraft:oak_leaves",
		"minecraft:spruce_leaves",
		"minecraft:snow_layer",
		"minecraft:acacia_sapling",
		"minecraft:cherry_sapling",
		"minecraft:spruce_sapling",
		"minecraft:bamboo_sapling",
		"minecraft:oak_sapling",
		"minecraft:jungle_sapling",
		"minecraft:dark_oak_sapling",
		"minecraft:leaf_litter",
		"minecraft:wildflowers",
		"minecraft:pink_petals",
		"minecraft:tall_grass",
		"minecraft:birch_sapling",
		"minecraft:short_grass",
		"minecraft:dandelion",
		"minecraft:lily_of_the_valley",
		"minecraft:lilac",
		"minecraft:peony",
		"minecraft:rose_bush",
		"minecraft:large_fern",
		"minecraft:fern",
	];

	public function __construct(
		private string $trunk,
		private string $leaf,
		private int $treeHeight
	){}

	public static function crimson(int $treeHeight) : self{
		return new self("minecraft:crimson_stem", "minecraft:nether_wart_block", $treeHeight);
	}

	public static function warped(int $treeHeight) : self{
		return new self("minecraft:warped_stem", "minecraft:warped_wart_block", $treeHeight);
	}

	private function checkY(int $y) : bool{
		return $y > 126;
	}

	private function isSolid(BlockManager $level, int $x, int $y, int $z) : bool{
		return $level->getBlockAt($x, $y, $z)->isSolid();
	}

	public function placeObject(BlockManager $level, int $x, int $y, int $z, Xoroshiro128 $random) : void{
		if($this->checkY($y)){
			return;
		}
		$this->placeTrunk($level, $x, $y, $z, $this->treeHeight);
		$mid = 2;
		$treeHeight = $this->treeHeight;
		for($yy = $y - 3 + $treeHeight; $yy <= $y + $treeHeight - 1; ++$yy){
			if($this->checkY($yy)){
				continue;
			}
			for($xx = $x - $mid; $xx <= $x + $mid; $xx++){
				$xOff = abs($xx - $x);
				for($zz = $z - $mid; $zz <= $z + $mid; $zz += $mid * 2){
					$zOff = abs($zz - $z);
					if($xOff === $mid && $zOff === $mid && $random->nextInt(2) === 0){
						continue;
					}
					if(!$this->isSolid($level, $xx, $yy, $zz)){
						if($random->nextInt(20) === 0){
							$level->setBlockStateAt($xx, $yy, $zz, self::SHROOMLIGHT);
						}else{
							$level->setBlockStateAt($xx, $yy, $zz, $this->leaf);
						}
					}
				}
			}
			for($zz = $z - $mid; $zz <= $z + $mid; $zz++){
				$zOff = abs($zz - $z);
				for($xx = $x - $mid; $xx <= $x + $mid; $xx += $mid * 2){
					$xOff = abs($xx - $x);
					if($xOff === $mid && $zOff === $mid && $random->nextInt(2) === 0){
						continue;
					}
					if(!$this->isSolid($level, $xx, $yy, $zz)){
						if($random->nextInt(20) === 0){
							$level->setBlockStateAt($xx, $yy, $zz, self::SHROOMLIGHT);
						}else{
							$level->setBlockStateAt($xx, $yy, $zz, $this->leaf);
						}
					}
				}
			}
		}

		for($yy = $y - 4 + $treeHeight; $yy <= $y + $treeHeight - 3; ++$yy){
			if($this->checkY($yy)){
				continue;
			}
			for($xx = $x - $mid; $xx <= $x + $mid; $xx++){
				for($zz = $z - $mid; $zz <= $z + $mid; $zz += $mid * 2){
					if(!$this->isSolid($level, $xx, $yy, $zz)){
						if($random->nextInt(3) === 0){
							for($i = 0; $i < $random->nextInt(5); $i++){
								if(!$this->isSolid($level, $xx, $yy - $i, $zz)){
									$level->setBlockStateAt($xx, $yy - $i, $zz, $this->leaf);
								}
							}
						}
					}
				}
			}
			for($zz = $z - $mid; $zz <= $z + $mid; $zz++){
				for($xx = $x - $mid; $xx <= $x + $mid; $xx += $mid * 2){
					if(!$this->isSolid($level, $xx, $yy, $zz)){
						if($random->nextInt(3) === 0){
							for($i = 0; $i < $random->nextInt(4); $i++){
								if(!$this->isSolid($level, $xx, $yy - $i, $zz)){
									$level->setBlockStateAt($xx, $yy - $i, $zz, $this->leaf);
								}
							}
						}
					}
				}
			}
		}

		for($xCanopy = $x - $mid + 1; $xCanopy <= $x + $mid - 1; $xCanopy++){
			for($zCanopy = $z - $mid + 1; $zCanopy <= $z + $mid - 1; $zCanopy++){
				if(!$this->isSolid($level, $xCanopy, $y + $treeHeight, $zCanopy)){
					$level->setBlockStateAt($xCanopy, $y + $treeHeight, $zCanopy, $this->leaf);
				}
			}
		}
	}

	private function placeTrunk(BlockManager $level, int $x, int $y, int $z, int $trunkHeight) : void{
		$level->setBlockStateAt($x, $y, $z, $this->trunk);
		for($yy = 0; $yy < $trunkHeight; ++$yy){
			if($this->checkY($y + $yy)){
				continue;
			}
			if(in_array($level->getBlockIdAt($x, $y + $yy, $z), self::OVERRIDABLE, true)){
				$level->setBlockStateAt($x, $y + $yy, $z, $this->trunk);
			}
		}
	}
}
