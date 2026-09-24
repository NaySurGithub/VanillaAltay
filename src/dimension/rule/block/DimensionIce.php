<?php

declare(strict_types=1);

namespace dimension\rule\block;

use dimension\rule\DimensionLookup;
use pocketmine\block\Ice;
use pocketmine\block\utils\BlockEventHelper;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\player\Player;

/**
 * Ice that leaves no water behind in the nether, whether it is broken or
 * melts.
 */
class DimensionIce extends Ice{

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		if(DimensionLookup::isInNether($this->position)){
			$this->position->getWorld()->setBlock($this->position, VanillaBlocks::AIR());
			return true;
		}
		return parent::onBreak($item, $player, $returnedItems);
	}

	public function onRandomTick() : void{
		if(!DimensionLookup::isInNether($this->position)){
			parent::onRandomTick();
			return;
		}
		$world = $this->position->getWorld();
		if($world->getHighestAdjacentBlockLight($this->position->x, $this->position->y, $this->position->z) >= 12){
			BlockEventHelper::melt($this, VanillaBlocks::AIR());
		}
	}
}
