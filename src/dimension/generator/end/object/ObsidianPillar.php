<?php

declare(strict_types=1);

namespace dimension\generator\end\object;

use dimension\generator\Blocks;
use dimension\generator\math\Int64;
use dimension\generator\object\BlockManager;
use function abs;
use function cos;
use function intdiv;
use function sin;
use const M_PI;

/**
 * One of the ten obsidian spikes around the end main island. Its size comes
 * from its index and the low 32 bits of the world seed; spikes 1 and 2 of the
 * size order are caged in iron bars. The top holds infiniburn bedrock and fire.
 */
final class ObsidianPillar{

	public const COUNT = 10;

	public function __construct(
		private int $index,
		private int $seed
	){}

	/**
	 * Centre of pillar $index, in block coordinates.
	 *
	 * @return array{int, int}
	 */
	public static function position(int $index) : array{
		$angle = 2.0 * (-M_PI + (M_PI / 10.0) * $index);
		return [(int) (42.0 * cos($angle)), (int) (42.0 * sin($angle))];
	}

	public function getPillar() : int{
		return abs(Int64::toInt32($this->index * 73 + Int64::toInt32($this->seed)) % 10);
	}

	public function getRadius() : int{
		return 2 + intdiv($this->getPillar(), 3);
	}

	public function getHeight() : int{
		return 76 + $this->getPillar() * 3;
	}

	public function isGuarded() : bool{
		$pillar = $this->getPillar();
		return $pillar === 1 || $pillar === 2;
	}

	public function generate(BlockManager $level, int $x, int $z) : void{
		$height = $this->getHeight();
		$radius = $this->getRadius();
		for($i = 0; $i < $height; $i++){
			for($j = -$radius; $j <= $radius; $j++){
				for($k = -$radius; $k <= $radius; $k++){
					if($j * $j + $k * $k <= $radius * $radius + 1){
						$level->setBlockStateAt($x + $j, $i, $z + $k, "minecraft:obsidian");
					}
				}
			}
		}
		if($this->isGuarded()){
			for($i = -2; $i <= 2; ++$i){
				for($j = -2; $j <= 2; ++$j){
					if(abs($i) === 2 || abs($j) === 2){
						for($k = 0; $k < 3; ++$k){
							$level->setBlockStateAt($x + $i, $height + $k, $z + $j, "minecraft:iron_bars");
						}
					}
					$level->setBlockStateAt($x + $i, $height + 3, $z + $j, "minecraft:iron_bars");
				}
			}
		}
		$level->setBlockStateAt($x, $height, $z, Blocks::get("minecraft:bedrock", ["infiniburn_bit" => true]));
		$level->setBlockStateAt($x, $height + 1, $z, "minecraft:fire");
	}
}
