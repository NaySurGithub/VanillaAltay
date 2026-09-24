<?php

declare(strict_types=1);

namespace redstone\block\power;

use pocketmine\block\Door;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\DoorSound;

trait PowerableDoorTrait{
	use PowerableOpenableTrait;

	public function onNearbyBlockChange() : void{
		Door::onNearbyBlockChange();
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		$this->open = !$this->open;

		$other = $this->getSide($this->top ? Facing::DOWN : Facing::UP);
		$world = $this->position->getWorld();
		if($other instanceof Door && $other->hasSameTypeId($this)){
			$other->open = $this->open;
			$world->setBlock($other->position, $other, false);
		}

		$world->setBlock($this->position, $this, false);
		$world->addSound($this->position, new DoorSound());
		return true;
	}
}
