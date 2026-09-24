<?php

declare(strict_types=1);

namespace redstone\block\tile\dispenser;

use pocketmine\block\tile\Container;
use pocketmine\block\tile\ContainerTrait;
use pocketmine\block\tile\Nameable;
use pocketmine\block\tile\NameableTrait;
use pocketmine\block\tile\Spawnable;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\player\Player;
use pocketmine\world\World;
use redstone\inventory\DispenserInventory;

class Dispenser extends Spawnable implements Container, Nameable{
	use ContainerTrait;
	use NameableTrait {
		addAdditionalSpawnData as addNameSpawnData;
	}

	private ?DispenserInventory $inventory;

	public function __construct(World $world, Vector3 $pos){
		parent::__construct($world, $pos);
		$this->inventory = $this->createInventory();
	}

	protected function createInventory() : DispenserInventory{
		return new DispenserInventory($this->position);
	}

	public function readSaveData(CompoundTag $nbt) : void{
		$this->loadName($nbt);
		$this->loadItems($nbt);
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$this->saveName($nbt);
		$this->saveItems($nbt);
	}

	public function close() : void{
		if(!$this->closed){
			$this->inventory->removeAllViewers();
			$this->inventory = null;
			parent::close();
		}
	}

	public function getInventory() : DispenserInventory{
		return $this->inventory;
	}

	public function getRealInventory() : DispenserInventory{
		return $this->inventory;
	}

	public function getDefaultName() : string{
		return "Dispenser";
	}

	public function onPower(int $facing, ?Player $player = null) : void{
		static $faces = [
			0 => 40,
			1 => 13,
			2 => 19,
			3 => 7,
			4 => 21,
			5 => 5,
		];

		$contents = $this->inventory->getContents();
		if(count($contents) === 0){
			$this->position->world->broadcastPacketToViewers($this->position, LevelEventPacket::create(LevelEvent::SOUND_CLICK_FAIL, 0, $this->position));
			return;
		}

		/** @var int $slot */
		$slot = array_rand($contents);
		if(!$this->dispenseSlot($slot, $facing, $player)){
			$this->position->world->broadcastPacketToViewers($this->position, LevelEventPacket::create(LevelEvent::SOUND_CLICK_FAIL, 0, $this->position));
			return;
		}

		$pos = $this->position->getSide($facing)->add(0.5, $facing === 0 ? -1 : ($facing === 1 ? 1 : 0.5), 0.5);
		$this->position->world->broadcastPacketToViewers($this->position, LevelEventPacket::create(LevelEvent::SOUND_CLICK, 0, $this->position));
		$this->position->world->broadcastPacketToViewers($pos, LevelEventPacket::create(LevelEvent::PARTICLE_SHOOT, $faces[$facing], $pos));
	}

	protected function dispenseSlot(int $slot, int $facing, ?Player $player) : bool{
		return DispensableItemManager::get($this->inventory->getItem($slot))->dispense($this->position, $this->inventory, $slot, $this->position->getSide($facing), $facing, $player);
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt) : void{
		$this->addNameSpawnData($nbt);
	}
}