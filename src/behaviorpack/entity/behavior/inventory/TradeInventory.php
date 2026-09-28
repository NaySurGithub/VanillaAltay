<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\inventory;

use pocketmine\inventory\SimpleInventory;
use pocketmine\network\mcpe\ComplexInventoryMapEntry;
use pocketmine\network\mcpe\InventoryManager;
use pocketmine\network\mcpe\InventoryManagerEntry;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UIInventorySlotOffset;
use pocketmine\player\Player;
use function spl_object_id;

/**
 * The two input slots of the trade screen opened on a trader entity.
 */
final class TradeInventory extends SimpleInventory{

	public const SLOT_A = 0;
	public const SLOT_B = 1;

	public function __construct(
		private TradeTableSystem $system,
		private Player $player
	){
		parent::__construct(2);
	}

	public function getSystem() : TradeTableSystem{
		return $this->system;
	}

	public function getPlayer() : Player{
		return $this->player;
	}

	/**
	 * Maps the trade input slots of the UI container to this inventory and
	 * returns the packet opening the trade screen.
	 *
	 * @return list<ClientboundPacket>
	 */
	public function createOpenPackets(int $windowId) : array{
		$manager = $this->player->getNetworkSession()->getInvManager();
		if($manager !== null){
			self::mapTradeSlots($manager, $this);
		}
		return [$this->system->createTradePacket($this->player, $windowId)];
	}

	private static function mapTradeSlots(InventoryManager $manager, TradeInventory $inventory) : void{
		$slotMap = UIInventorySlotOffset::TRADE2_INGREDIENT;
		(function() use ($inventory, $slotMap) : void{
			$complex = new ComplexInventoryMapEntry($inventory, $slotMap);
			$this->inventories[spl_object_id($inventory)] = new InventoryManagerEntry($inventory, $complex);
			foreach($slotMap as $netSlot => $coreSlot){
				$this->complexSlotToInventoryMap[$netSlot] = $complex;
			}
		})->call($manager);
	}

	public function onClose(Player $who) : void{
		parent::onClose($who);
		$contents = $this->getContents();
		$this->clearAll();
		foreach($contents as $item){
			foreach($who->getInventory()->addItem($item) as $left){
				$who->getWorld()->dropItem($who->getLocation(), $left);
			}
		}
		$this->system->onTradeWindowClosed($who);
	}
}
