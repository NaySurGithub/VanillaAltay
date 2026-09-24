<?php

declare(strict_types=1);

namespace redstone\world;

use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\ChunkListener;
use pocketmine\world\ChunkListenerNoOpTrait;
use pocketmine\world\World;
use redstone\block\Observer;
use redstone\block\utils\HelperUtils;

final class ObserverChunkListener implements ChunkListener{
	use ChunkListenerNoOpTrait;

	public function __construct(
		readonly private World $world
	){}

	public function onBlockChanged(Vector3 $block) : void{
		foreach(HelperUtils::$side_offsets as $side => $offset){
			$observer = $this->world->getBlockAt((int) $block->x + $offset->x, (int) $block->y + $offset->y, (int) $block->z + $offset->z);
			if($observer instanceof Observer && $observer->getFacing() === Facing::opposite($side)){
				$observer->onObservedBlockChange();
			}
		}
	}
}
