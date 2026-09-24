<?php

declare(strict_types=1);

namespace dimension\generator\end\object;

use dimension\generator\Blocks;
use dimension\generator\object\BlockManager;
use dimension\generator\random\Xoroshiro128;
use pocketmine\block\Block;
use function abs;

/**
 * Fully grown chorus plant: branching chorus stems capped by dead chorus
 * flowers after four levels of branching.
 */
final class ChorusPlant{

	private const AIR = "minecraft:air";
	private const PLANT = "minecraft:chorus_plant";

	/** Horizontal faces in north, east, south, west order, with the index of their opposite. */
	private const HORIZONTALS = [[0, -1], [1, 0], [0, 1], [-1, 0]];
	private const OPPOSITE = [2, 3, 0, 1];

	private ?Block $deadFlower = null;

	private function deadFlower() : Block{
		return $this->deadFlower ??= Blocks::get("minecraft:chorus_flower", ["age" => 5]);
	}

	public function generate(BlockManager $level, Xoroshiro128 $random, int $x, int $y, int $z, int $maxSize) : void{
		$level->setBlockStateAt($x, $y, $z, self::PLANT);
		$this->growImmediately($level, $random, $x, $y, $z, $maxSize, 0);
	}

	private function growImmediately(BlockManager $level, Xoroshiro128 $random, int $x, int $y, int $z, int $maxSize, int $age) : void{
		$height = 1 + $random->nextInt(4);
		if($age === 0){
			$height++;
		}

		for($dy = 1; $dy <= $height; $dy++){
			if(!$this->isHorizontalAir($level, $x, $y + $dy, $z, -1)){
				return;
			}
			$level->setBlockStateAt($x, $y + $dy, $z, self::PLANT);
		}

		if($age < 4){
			$attempt = $random->nextInt(4);
			if($age === 0){
				$attempt++;
			}
			for($i = 0; $i < $attempt; $i++){
				$face = $random->nextInt(4);
				$checkX = $x + self::HORIZONTALS[$face][0];
				$checkY = $y + $height;
				$checkZ = $z + self::HORIZONTALS[$face][1];
				if($level->getBlockIdAt($checkX, $checkY, $checkZ) === self::AIR && $level->getBlockIdAt($checkX, $checkY - 1, $checkZ) === self::AIR){
					if(abs($checkX - $x) < $maxSize && abs($checkZ - $z) < $maxSize && $this->isHorizontalAir($level, $checkX, $checkY, $checkZ, self::OPPOSITE[$face])){
						$level->setBlockStateAt($checkX, $checkY, $checkZ, self::PLANT);
						$this->growImmediately($level, $random, $checkX, $checkY, $checkZ, $maxSize, $age + 1);
					}
				}
			}
		}else{
			$level->setBlockStateAt($x, $y + $height, $z, $this->deadFlower());
		}
	}

	private function isHorizontalAir(BlockManager $level, int $x, int $y, int $z, int $except) : bool{
		foreach(self::HORIZONTALS as $index => [$dx, $dz]){
			if($index !== $except && $level->getBlockIdAt($x + $dx, $y, $z + $dz) !== self::AIR){
				return false;
			}
		}
		return true;
	}
}
