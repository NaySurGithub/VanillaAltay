<?php

declare(strict_types=1);

namespace dummy\item;

use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;

/**
 * A vanilla item the server does not implement. It can be held, stored and
 * saved, but has no behaviour of its own.
 */
class DummyItem extends Item{

	public function __construct(
		ItemIdentifier $identifier,
		string $name,
		private int $maxStackSize
	){
		parent::__construct($identifier, $name);
	}

	public function getMaxStackSize() : int{
		return $this->maxStackSize;
	}
}
