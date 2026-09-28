<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\inventory;

use behaviorpack\entity\BehaviorEntity;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\SimpleInventory;
use pocketmine\network\mcpe\protocol\ContainerOpenPacket;
use pocketmine\player\Player;

/**
 * The container of a custom entity, opened to players as an entity window.
 */
class EntityInventory extends SimpleInventory{

	private static ?\Closure $opener = null;

	public function __construct(
		int $size,
		private BehaviorEntity $entity,
		private int $windowType
	){
		parent::__construct($size);
	}

	public function getEntity() : BehaviorEntity{
		return $this->entity;
	}

	public function getWindowType() : int{
		return $this->windowType;
	}

	public function setWindowType(int $windowType) : void{
		$this->windowType = $windowType;
	}

	/**
	 * Registers on the network session of a player the callback sending the
	 * open packet of the entity windows.
	 */
	public static function registerOpener(Player $player) : void{
		$manager = $player->getNetworkSession()->getInvManager();
		if($manager === null){
			return;
		}
		self::$opener ??= static function(int $windowId, Inventory $inventory) : ?array{
			if($inventory instanceof TradeInventory){
				return $inventory->createOpenPackets($windowId);
			}
			if($inventory instanceof EntityInventory){
				return [ContainerOpenPacket::entityInv($windowId, $inventory->getWindowType(), $inventory->getEntity()->getId())];
			}
			return null;
		};
		$manager->getContainerOpenCallbacks()->add(self::$opener);
	}
}
