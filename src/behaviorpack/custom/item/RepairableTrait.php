<?php

declare(strict_types=1);

namespace behaviorpack\custom\item;

use pocketmine\item\Durable;
use pocketmine\item\Item;

/**
 * Anvil repair of a durable behavior pack item from its minecraft:repairable
 * component. Combining with an item of the same type keeps the anvil path.
 */
trait RepairableTrait{

	public function isValidRepairMaterial(Item $material) : bool{
		$repairable = $this->getRepairable();
		if($repairable === null || $material->getTypeId() === $this->getTypeId()){
			return parent::isValidRepairMaterial($material);
		}
		return $repairable->accepts($material);
	}

	public function getRepairAmount(Item $material) : int{
		return $this->getRepairable()?->evaluate($this, $material) ?? parent::getRepairAmount($material);
	}

	public function getCombineRepairAmount(Durable $material) : int{
		return $this->getRepairable()?->evaluate($this, $material) ?? parent::getCombineRepairAmount($material);
	}
}
