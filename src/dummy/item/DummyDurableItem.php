<?php

declare(strict_types=1);

namespace dummy\item;

use pocketmine\item\Durable;
use pocketmine\item\ItemIdentifier;

/**
 * A vanilla item with durability the server does not implement.
 */
class DummyDurableItem extends Durable{

	public function __construct(
		ItemIdentifier $identifier,
		string $name,
		private int $maxDurability
	){
		parent::__construct($identifier, $name);
	}

	public function getMaxDurability() : int{
		return $this->maxDurability;
	}

	public function getMaxStackSize() : int{
		return 1;
	}
}
