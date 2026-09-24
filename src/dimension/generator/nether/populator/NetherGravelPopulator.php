<?php

declare(strict_types=1);

namespace dimension\generator\nether\populator;

use dimension\generator\nether\object\OreGeneratorFeature;
use dimension\generator\nether\object\WorldQuery;
use dimension\generator\object\BlockManager;
use dimension\generator\random\Xoroshiro128;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\world\ChunkManager;

class NetherGravelPopulator extends OreGeneratorFeature{

	public function name() : string{
		return "nether_gravel";
	}

	public function getState() : string{
		return "minecraft:gravel";
	}

	public function getClusterCount() : int{
		return 2;
	}

	public function getClusterSize() : int{
		return 33;
	}

	public function getMinHeight() : int{
		return 5;
	}

	public function getMaxHeight() : int{
		return 36;
	}

	protected function spawn(BlockManager $level, ChunkManager $world, Xoroshiro128 $rand, int $x, int $y, int $z) : void{
		if(WorldQuery::biomeId($world, $x, $y, $z) !== BiomeIds::BASALT_DELTAS){
			parent::spawn($level, $world, $rand, $x, $y, $z);
		}
	}
}
