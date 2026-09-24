<?php

declare(strict_types=1);

namespace dimension\generator\end\object;

use dimension\generator\math\Float32;
use dimension\generator\math\GenerationMath;
use dimension\generator\object\BlockManager;
use dimension\generator\random\LegacyRandom;

/**
 * Small floating end stone island: stacked discs shrinking downward.
 */
final class EndIsland{

	public function generate(BlockManager $level, LegacyRandom $rand, int $posX, int $posY, int $posZ) : void{
		$n = (float) ($rand->nextInt(2) + 4);
		for($y = 0; $n > 0.5; $y--){
			$limit = Float32::of(($n + 1.0) * ($n + 1.0));
			for($x = GenerationMath::floorFloat(-$n); $x <= GenerationMath::ceilFloat($n); $x++){
				for($z = GenerationMath::floorFloat(-$n); $z <= GenerationMath::ceilFloat($n); $z++){
					if((float) ($x * $x + $z * $z) <= $limit){
						$level->setBlockStateAt($posX + $x, $posY + $y, $posZ + $z, "minecraft:end_stone");
					}
				}
			}
			$n = Float32::of($n - ($rand->nextInt(1) + 0.5));
		}
	}
}
