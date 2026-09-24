<?php

declare(strict_types=1);

namespace redstone\block;

use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use redstone\block\power\PowerableDoorTrait;
use redstone\block\power\Powerable;

final class Door extends \pocketmine\block\Door implements Powerable{
	use PowerableDoorTrait;

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		return false;
	}
}
