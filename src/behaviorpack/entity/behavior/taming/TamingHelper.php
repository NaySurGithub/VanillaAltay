<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\taming;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\loot\LootItems;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\player\Player;
use pocketmine\utils\Utils;
use pocketmine\world\particle\Particle;
use function array_values;
use function class_exists;
use function count;
use function explode;
use function implode;
use function is_array;
use function is_numeric;
use function is_string;
use function md5;

/**
 * Item matching, item consumption, particles and entity creation shared by
 * the taming, breeding and riding systems.
 */
final class TamingHelper{

	private const GENERATED_NAMESPACE = "behaviorpack\\entity\\generated";

	private function __construct(){
	}

	/**
	 * Normalizes an item name of a definition: "minecraft:" is added when no
	 * namespace is given and an ":aux" suffix is removed.
	 */
	public static function normalizeItemName(string $name) : string{
		$parts = explode(":", LootItems::normalize($name));
		if(count($parts) >= 3 && is_numeric($parts[count($parts) - 1])){
			unset($parts[count($parts) - 1]);
		}
		return implode(":", $parts);
	}

	/**
	 * Returns the entry of a list of item names or {item: ...} objects that
	 * matches the item, or null.
	 *
	 * @return array<mixed>|null
	 */
	public static function findItem(Item $item, mixed $list) : ?array{
		if($item->isNull()){
			return null;
		}
		$identifier = LootItems::identifierOf($item);
		if($identifier === null){
			return null;
		}
		if(is_string($list)){
			$list = [$list];
		}
		if(!is_array($list)){
			return null;
		}
		if(isset($list["item"])){
			$list = [$list];
		}
		foreach($list as $entry){
			$name = is_string($entry) ? $entry : (is_array($entry) && is_string($entry["item"] ?? null) ? $entry["item"] : null);
			if($name !== null && self::normalizeItemName($name) === $identifier){
				return is_array($entry) ? $entry : ["item" => $entry];
			}
		}
		return null;
	}

	public static function matches(Item $item, mixed $list) : bool{
		return self::findItem($item, $list) !== null;
	}

	public static function isItem(Item $item, string $name) : bool{
		return !$item->isNull() && LootItems::identifierOf($item) === self::normalizeItemName($name);
	}

	/**
	 * Takes one item from the hand of the player, unless it is in creative.
	 */
	public static function consumeHeldItem(Player $player) : void{
		if(!$player->hasFiniteResources()){
			return;
		}
		$item = $player->getInventory()->getItemInHand();
		$item->pop();
		$player->getInventory()->setItemInHand($item);
	}

	/**
	 * Gives an item to the player, dropping what does not fit.
	 */
	public static function giveItem(Player $player, Item $item) : void{
		foreach($player->getInventory()->addItem($item) as $left){
			$player->getWorld()->dropItem($player->getPosition(), $left);
		}
	}

	/**
	 * Gives the item the held item turns into, replacing the hand when it
	 * was used up.
	 */
	public static function transformHeldItem(Player $player, string $name) : void{
		if(!$player->hasFiniteResources()){
			return;
		}
		$parts = explode(":", LootItems::normalize($name));
		$meta = 0;
		if(count($parts) >= 3 && is_numeric($parts[count($parts) - 1])){
			$meta = (int) $parts[count($parts) - 1];
			unset($parts[count($parts) - 1]);
		}
		$result = LootItems::resolve(implode(":", $parts), $meta);
		if($result === null){
			return;
		}
		$held = $player->getInventory()->getItemInHand();
		if($held->isNull()){
			$player->getInventory()->setItemInHand($result);
			return;
		}
		self::giveItem($player, $result);
	}

	public static function particles(Entity $entity, Particle $particle, int $count) : void{
		$world = $entity->getWorld();
		$box = $entity->getBoundingBox();
		$width = $box->maxX - $box->minX;
		$height = $box->maxY - $box->minY;
		for($i = 0; $i < $count; ++$i){
			$world->addParticle(new Vector3(
				$box->minX + Utils::getRandomFloat() * $width,
				$box->minY + 0.5 + Utils::getRandomFloat() * $height,
				$box->minZ + Utils::getRandomFloat() * $width
			), $particle);
		}
	}

	/**
	 * Combines the configuration lists of a component that may hold a single
	 * object or a list of them.
	 *
	 * @return list<array<mixed>>
	 */
	public static function objectList(mixed $value) : array{
		if(!is_array($value)){
			return [];
		}
		if(!isset($value[0]) && count($value) > 0){
			return [$value];
		}
		$result = [];
		foreach(array_values($value) as $entry){
			if(is_array($entry)){
				$result[] = $entry;
			}
		}
		return $result;
	}

	/**
	 * Whether an item is used by one of the feeding, taming or leashing
	 * components of the entity, in which case riding and sitting ignore the
	 * interaction.
	 */
	public static function isUsedByComponents(BehaviorEntity $entity, Item $item) : bool{
		if($item->isNull()){
			return false;
		}
		$lists = [];
		$breedable = $entity->getComponent("minecraft:breedable");
		if($breedable !== null){
			$lists[] = $breedable["breed_items"] ?? [];
		}
		$ageable = $entity->getComponent("minecraft:ageable");
		if($ageable !== null){
			$lists[] = $ageable["feed_items"] ?? [];
		}
		$tameable = $entity->getComponent("minecraft:tameable");
		if($tameable !== null){
			$lists[] = $tameable["tame_items"] ?? [];
		}
		$healable = $entity->getComponent("minecraft:healable");
		if($healable !== null){
			$lists[] = $healable["items"] ?? [];
		}
		if($entity->hasComponent("minecraft:leashable")){
			$lists[] = ["minecraft:lead"];
		}
		foreach($lists as $list){
			if(self::matches($item, $list)){
				return true;
			}
		}
		return false;
	}

	/**
	 * Creates an entity of a behavior pack or of the server from its
	 * identifier. A behavior pack entity is created as already spawned, so
	 * that the caller runs the event it is born with.
	 */
	public static function createEntity(string $identifier, Location $location) : ?Entity{
		$class = self::GENERATED_NAMESPACE . "\\Entity" . md5($identifier);
		if(class_exists($class, false)){
			$nbt = CompoundTag::create()->setByte("BehaviorSpawned", 1);
			$entity = new $class($location, $nbt);
			return $entity instanceof Entity ? $entity : null;
		}
		$nbt = CompoundTag::create()
			->setTag("identifier", new StringTag($identifier))
			->setTag("Pos", new ListTag([new DoubleTag($location->x), new DoubleTag($location->y), new DoubleTag($location->z)]))
			->setTag("Motion", new ListTag([new DoubleTag(0.0), new DoubleTag(0.0), new DoubleTag(0.0)]))
			->setTag("Rotation", new ListTag([new FloatTag($location->yaw), new FloatTag($location->pitch)]));
		try{
			return EntityFactory::getInstance()->createFromData($location->getWorld(), $nbt);
		}catch(\Throwable){
			return null;
		}
	}
}
