<?php

declare(strict_types=1);

namespace redstone\block;

use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use redstone\block\power\Powerable;
use redstone\block\power\PowerableOpenableTrait;

final class Trapdoor extends \pocketmine\block\Trapdoor implements Powerable{
	use OptimizedBlockTrait;
	use PowerableOpenableTrait;

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		return false;
	}
}
