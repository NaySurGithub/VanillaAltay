<?php

declare(strict_types=1);

namespace behaviorpack\loot;

use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\player\Player;

/**
 * What a loot table is rolled for: the dropping entity, its killer (or the
 * player breaking a block), the tool used and the looting level.
 */
final class LootContext{

	public function __construct(
		private ?Entity $entity = null,
		private ?Entity $killer = null,
		private ?Item $tool = null,
		private int $lootingLevel = 0
	){
	}

	public function getEntity() : ?Entity{
		return $this->entity;
	}

	public function getKiller() : ?Entity{
		return $this->killer;
	}

	public function getTool() : ?Item{
		return $this->tool;
	}

	public function getLootingLevel() : int{
		return $this->lootingLevel;
	}

	public function isKilledByPlayer() : bool{
		return $this->killer instanceof Player;
	}

	public function isKilledByPlayerOrPet() : bool{
		if($this->killer instanceof Player){
			return true;
		}
		return $this->killer !== null && $this->killer->getOwningEntity() instanceof Player;
	}
}
