<?php

declare(strict_types=1);

namespace redstone\block;

use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use redstone\vanilla\ExtraVanillaItems;

class RedstoneOre extends \pocketmine\block\RedstoneOre{

	public function getDropsForCompatibleTool(Item $item) : array{
		$items = parent::getDropsForCompatibleTool($item);
		foreach($items as $index => $i){
			if($i->getTypeId() === ItemTypeIds::REDSTONE_DUST){
				$items[$index] = ExtraVanillaItems::REDSTONE_DUST()->setCount($i->getCount());
			}
		}
		return $items;
	}
}