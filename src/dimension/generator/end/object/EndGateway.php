<?php

declare(strict_types=1);

namespace dimension\generator\end\object;

use dimension\generator\Blocks;
use dimension\generator\object\BlockManager;
use pocketmine\block\Block;

/**
 * End gateway block wrapped in bedrock above and below. When the server has
 * no end gateway block the centre is left as air.
 */
final class EndGateway{

	private const FACES = [[0, -1, 0], [0, 1, 0], [0, 0, -1], [0, 0, 1], [-1, 0, 0], [1, 0, 0]];

	private ?Block $gateway = null;
	private bool $resolved = false;

	private function gatewayBlock() : ?Block{
		if(!$this->resolved){
			$this->resolved = true;
			try{
				$this->gateway = Blocks::get("minecraft:end_gateway");
			}catch(\InvalidArgumentException){
				$this->gateway = null;
			}
		}
		return $this->gateway;
	}

	public function generate(BlockManager $level, int $x, int $y, int $z) : void{
		foreach(self::FACES as [$dx, $dy, $dz]){
			$level->setBlockStateAt($x + $dx, $y + 1 + $dy, $z + $dz, "minecraft:bedrock");
		}
		foreach(self::FACES as [$dx, $dy, $dz]){
			$level->setBlockStateAt($x + $dx, $y - 1 + $dy, $z + $dz, "minecraft:bedrock");
		}
		$level->setBlockStateAt($x, $y, $z, $this->gatewayBlock() ?? "minecraft:air");
	}
}
