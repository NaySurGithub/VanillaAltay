<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\inventory;

use behaviorpack\entity\BehaviorEntity;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\player\Player;
use pocketmine\utils\Utils;
use function is_array;
use function str_contains;
use function strtolower;

/**
 * The equipment worn by a custom entity: the items in its hands, its armor
 * and the items of its equippable slots (saddle, horse armor, carpet...).
 * Shared by the equipment and equippable systems, kept in the data store.
 */
final class MobEquipment{

	public const SLOT_MAINHAND = "slot.weapon.mainhand";
	public const SLOT_OFFHAND = "slot.weapon.offhand";
	public const SLOT_HEAD = "slot.armor.head";
	public const SLOT_CHEST = "slot.armor.chest";
	public const SLOT_LEGS = "slot.armor.legs";
	public const SLOT_FEET = "slot.armor.feet";

	private const ARMOR_SLOTS = [
		self::SLOT_HEAD => 0,
		self::SLOT_CHEST => 1,
		self::SLOT_LEGS => 2,
		self::SLOT_FEET => 3
	];

	private const DATA_KEY = "equipment";

	/** @var \WeakMap<BehaviorEntity, MobEquipment>|null */
	private static ?\WeakMap $instances = null;

	private Item $mainHand;
	private Item $offHand;

	/** @var array<int, Item> */
	private array $slots = [];

	private bool $loading = false;

	private function __construct(
		private BehaviorEntity $entity
	){
		$this->mainHand = VanillaItems::AIR();
		$this->offHand = VanillaItems::AIR();
		$this->load();
		$entity->getArmorInventory()->getListeners()->add(CallbackInventoryListener::onAnyChange(function() : void{
			$this->save();
		}));
	}

	public static function of(BehaviorEntity $entity) : self{
		self::$instances ??= new \WeakMap();
		return self::$instances[$entity] ??= new self($entity);
	}

	private function load() : void{
		$data = $this->entity->getData(self::DATA_KEY);
		if(!is_array($data)){
			return;
		}
		$this->loading = true;
		$this->mainHand = ItemCodec::decode($data["mainhand"] ?? null);
		$this->offHand = ItemCodec::decode($data["offhand"] ?? null);
		foreach(ItemCodec::decodeList($data["armor"] ?? null) as $slot => $item){
			if($slot >= 0 && $slot < 4){
				$this->entity->getArmorInventory()->setItem($slot, $item);
			}
		}
		$this->slots = ItemCodec::decodeList($data["slots"] ?? null);
		$this->loading = false;
		$this->updateFlags();
	}

	private function save() : void{
		if($this->loading){
			return;
		}
		$data = [];
		if(!$this->mainHand->isNull()){
			$data["mainhand"] = ItemCodec::encode($this->mainHand);
		}
		if(!$this->offHand->isNull()){
			$data["offhand"] = ItemCodec::encode($this->offHand);
		}
		$armor = ItemCodec::encodeList($this->entity->getArmorInventory()->getContents());
		if($armor !== []){
			$data["armor"] = $armor;
		}
		$slots = ItemCodec::encodeList($this->slots);
		if($slots !== []){
			$data["slots"] = $slots;
		}
		$this->entity->setData(self::DATA_KEY, $data === [] ? null : $data);
	}

	public function getMainHand() : Item{
		return clone $this->mainHand;
	}

	public function setMainHand(Item $item) : void{
		$this->mainHand = clone $item;
		$this->save();
		$this->sendHands($this->entity->getViewers());
	}

	public function getOffHand() : Item{
		return clone $this->offHand;
	}

	public function setOffHand(Item $item) : void{
		$this->offHand = clone $item;
		$this->save();
		$this->sendHands($this->entity->getViewers());
	}

	public function getArmor(int $slot) : Item{
		return $this->entity->getArmorInventory()->getItem($slot);
	}

	public function setArmor(int $slot, Item $item) : void{
		$this->entity->getArmorInventory()->setItem($slot, $item);
	}

	/**
	 * Returns the item of an equippable slot.
	 */
	public function getSlot(int $slot) : Item{
		return isset($this->slots[$slot]) ? clone $this->slots[$slot] : VanillaItems::AIR();
	}

	public function setSlot(int $slot, Item $item) : void{
		$previous = $this->slots[$slot] ?? null;
		if($item->isNull()){
			unset($this->slots[$slot]);
		}else{
			$this->slots[$slot] = clone $item;
		}
		if(self::isHorseArmor($item)){
			$this->entity->getArmorInventory()->setChestplate($item);
		}elseif($previous !== null && self::isHorseArmor($previous)){
			$this->entity->getArmorInventory()->setChestplate(VanillaItems::AIR());
		}
		$this->updateFlags();
		$this->save();
	}

	/**
	 * @return array<int, Item>
	 */
	public function getSlots() : array{
		return $this->slots;
	}

	/**
	 * Reads a slot name of the definitions ("slot.weapon.mainhand",
	 * "slot.armor.head"...).
	 */
	public function getByName(string $slot) : Item{
		$slot = strtolower($slot);
		if($slot === self::SLOT_MAINHAND){
			return $this->getMainHand();
		}
		if($slot === self::SLOT_OFFHAND){
			return $this->getOffHand();
		}
		if(isset(self::ARMOR_SLOTS[$slot])){
			return $this->getArmor(self::ARMOR_SLOTS[$slot]);
		}
		return VanillaItems::AIR();
	}

	public function setByName(string $slot, Item $item) : bool{
		$slot = strtolower($slot);
		if($slot === self::SLOT_MAINHAND){
			$this->setMainHand($item);
			return true;
		}
		if($slot === self::SLOT_OFFHAND){
			$this->setOffHand($item);
			return true;
		}
		if(isset(self::ARMOR_SLOTS[$slot])){
			$this->setArmor(self::ARMOR_SLOTS[$slot], $item);
			return true;
		}
		return false;
	}

	/**
	 * Puts an item in the slot it belongs to: armor on its armor slot,
	 * anything else in the main hand.
	 */
	public function equip(Item $item) : void{
		$armorSlot = self::armorSlotOf($item);
		if($armorSlot !== null){
			$this->setArmor($armorSlot, $item);
			return;
		}
		$this->setMainHand($item);
	}

	public static function armorSlotOf(Item $item) : ?int{
		if($item instanceof \pocketmine\item\Armor){
			return $item->getArmorSlot();
		}
		return null;
	}

	private static function isHorseArmor(Item $item) : bool{
		return !$item->isNull() && str_contains(strtolower($item->getVanillaName()), "horse armor");
	}

	private function updateFlags() : void{
		$saddled = false;
		$chested = false;
		foreach($this->slots as $item){
			$name = strtolower($item->getVanillaName());
			if($name === "saddle"){
				$saddled = true;
			}
			if($name === "chest"){
				$chested = true;
			}
		}
		$this->entity->setFlag(EntityMetadataFlags::SADDLED, $saddled);
		if($chested || $this->entity->getFlag(EntityMetadataFlags::CHESTED)){
			$this->entity->setFlag(EntityMetadataFlags::CHESTED, $chested);
		}
	}

	/**
	 * @param Player[] $players
	 */
	public function sendHands(array $players) : void{
		foreach($players as $player){
			$session = $player->getNetworkSession();
			$converter = $session->getTypeConverter();
			$session->sendDataPacket(MobEquipmentPacket::create(
				$this->entity->getId(),
				TypeConverter::legacyItemStackWrapper($converter->coreItemStackToNet($this->mainHand)),
				0,
				0,
				ContainerIds::INVENTORY
			));
			$session->sendDataPacket(MobEquipmentPacket::create(
				$this->entity->getId(),
				TypeConverter::legacyItemStackWrapper($converter->coreItemStackToNet($this->offHand)),
				0,
				0,
				ContainerIds::OFFHAND
			));
		}
	}

	public function sendTo(Player $player) : void{
		$this->sendHands([$player]);
		$session = $player->getNetworkSession();
		$session->getEntityEventBroadcaster()->onMobArmorChange([$session], $this->entity);
	}

	/**
	 * Drops and empties the equippable slots.
	 */
	public function dropSlots() : void{
		if($this->slots === []){
			return;
		}
		$world = $this->entity->getWorld();
		foreach($this->slots as $slot => $item){
			$world->dropItem($this->entity->getLocation(), $item);
			$this->setSlot($slot, VanillaItems::AIR());
		}
	}

	/**
	 * Drops the equipment: each slot with its drop chance (1.0 when not
	 * listed, as for picked up items), then empties the slots.
	 *
	 * @param array<string, float> $dropChances
	 */
	public function dropAll(array $dropChances, float $defaultChance) : void{
		$world = $this->entity->getWorld();
		$position = $this->entity->getLocation();
		$named = [self::SLOT_MAINHAND, self::SLOT_OFFHAND, self::SLOT_HEAD, self::SLOT_CHEST, self::SLOT_LEGS, self::SLOT_FEET];
		foreach($named as $slot){
			$item = $this->getByName($slot);
			if($item->isNull() || ($slot === self::SLOT_CHEST && self::isHorseArmor($item))){
				continue;
			}
			$chance = $dropChances[$slot] ?? $defaultChance;
			if($chance > 0 && Utils::getRandomFloat() < $chance){
				$world->dropItem($position, $item);
			}
		}
		foreach($this->slots as $item){
			$world->dropItem($position, $item);
		}
		$this->loading = true;
		$this->mainHand = VanillaItems::AIR();
		$this->offHand = VanillaItems::AIR();
		$this->slots = [];
		$this->entity->getArmorInventory()->clearAll();
		$this->loading = false;
		$this->save();
	}
}
