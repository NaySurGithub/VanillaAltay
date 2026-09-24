<?php

declare(strict_types=1);

namespace dimension\generator\end\object;

use dimension\generator\object\BlockManager;
use function abs;

/**
 * Bedrock frame and pillar of the end exit portal, centred on the given
 * position, with its inside cleared.
 */
final class ExitPortal{

	private const BEDROCK = "minecraft:bedrock";

	public function generate(BlockManager $level, int $x, int $y, int $z) : void{
		for($dx = -2; $dx <= 2; $dx++){
			for($dz = -2; $dz <= 2; $dz++){
				if(!(abs($dx) === 2 && abs($dz) === 2)){
					$level->setBlockStateAt($x + $dx, $y - 1, $z + $dz, self::BEDROCK);
					$level->setBlockStateAt($x + $dx, $y, $z + $dz, "minecraft:air");
					if($dx === -2){
						$level->setBlockStateAt($x - 3, $y, $z + $dz, self::BEDROCK);
					}
					if($dx === 2){
						$level->setBlockStateAt($x + 3, $y, $z + $dz, self::BEDROCK);
					}
					if($dz === -2){
						$level->setBlockStateAt($x + $dx, $y, $z - 3, self::BEDROCK);
					}
					if($dz === 2){
						$level->setBlockStateAt($x + $dx, $y, $z + 3, self::BEDROCK);
					}
				}else{
					$level->setBlockStateAt($x + $dx, $y, $z + $dz, self::BEDROCK);
				}
			}
		}
		for($i = 0; $i < 4; $i++){
			$level->setBlockStateAt($x, $y + $i, $z, self::BEDROCK);
		}
	}
}
