<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\state;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\utils\Utils;
use function array_is_list;
use function array_slice;
use function count;
use function is_array;
use function is_int;
use function is_string;
use function max;
use function str_contains;

/**
 * minecraft:spawn_entity: periodically spawns entities or drops items, such
 * as a chicken laying eggs. Each entry keeps its own timer.
 */
final class SpawnEntitySystem extends EntitySystem{

	private const DATA_TIMERS = "spawn_entity_timers";
	private const DATA_USED = "spawn_entity_used";
	private const MAX_CHILDREN = 64;

	/**
	 * @return list<array<mixed>>
	 */
	public function getEntries() : array{
		$entries = $this->config["entities"] ?? null;
		if($entries === null){
			return [$this->config];
		}
		if(!is_array($entries)){
			return [];
		}
		if(!array_is_list($entries)){
			return [$entries];
		}
		$result = [];
		foreach($entries as $entry){
			if(is_array($entry)){
				$result[] = $entry;
			}
		}
		return $result;
	}

	public function onAdd() : void{
		$timers = $this->timers();
		foreach($this->getEntries() as $index => $entry){
			if(!isset($timers[$index])){
				$timers[$index] = $this->waitTime($entry);
			}
		}
		$this->entity->setData(self::DATA_TIMERS, $timers);
	}

	public function onRemove() : void{
		$this->entity->setData(self::DATA_TIMERS, null);
		$this->entity->setData(self::DATA_USED, null);
	}

	/**
	 * @return array<int, int>
	 */
	private function timers() : array{
		$timers = $this->entity->getData(self::DATA_TIMERS, []);
		return is_array($timers) ? $timers : [];
	}

	/**
	 * @param array<mixed> $entry
	 */
	private function waitTime(array $entry) : int{
		$min = max(0.0, BehaviorEntity::toFloat($entry["min_wait_time"] ?? null, 300.0));
		$max = max($min, BehaviorEntity::toFloat($entry["max_wait_time"] ?? null, 600.0));
		return max(1, (int) (($min + Utils::getRandomFloat() * ($max - $min)) * 20));
	}

	public function tick(int $tickDiff) : void{
		$timers = $this->timers();
		$used = $this->entity->getData(self::DATA_USED, []);
		$used = is_array($used) ? $used : [];
		foreach($this->getEntries() as $index => $entry){
			if(isset($used[$index])){
				continue;
			}
			$remaining = (is_int($timers[$index] ?? null) ? $timers[$index] : $this->waitTime($entry)) - $tickDiff;
			if($remaining > 0){
				$timers[$index] = $remaining;
				continue;
			}
			$timers[$index] = $this->waitTime($entry);
			if(is_array($entry["filters"] ?? null) && !$this->entity->testFilter($entry["filters"])){
				continue;
			}
			$this->spawn($entry);
			if(($entry["single_use"] ?? false) === true){
				$used[$index] = true;
			}
			if($this->entity->isClosed()){
				return;
			}
		}
		$this->entity->setData(self::DATA_TIMERS, $timers);
		$this->entity->setData(self::DATA_USED, count($used) > 0 ? $used : null);
	}

	/**
	 * Spawns the entities or the items of an entry now.
	 *
	 * @param array<mixed> $entry
	 */
	public function spawn(array $entry) : void{
		$count = max(1, (int) BehaviorEntity::toFloat($entry["num_to_spawn"] ?? null, 1.0));
		$position = $this->entity->getPosition();
		$world = $this->entity->getWorld();
		$identifier = $entry["spawn_entity"] ?? null;
		$spawned = false;
		for($i = 0; $i < $count; ++$i){
			if(is_string($identifier) && $identifier !== ""){
				$child = $this->spawnChild($identifier, $entry);
				$spawned = $spawned || $child !== null;
				continue;
			}
			$itemName = is_string($entry["spawn_item"] ?? null) ? $entry["spawn_item"] : "egg";
			$item = StringToItemParser::getInstance()->parse($itemName);
			if($item === null){
				continue;
			}
			$world->dropItem($position, $item);
			$spawned = true;
		}
		if(!$spawned){
			return;
		}
		$sound = is_string($entry["spawn_sound"] ?? null) ? $entry["spawn_sound"] : "plop";
		if($sound !== ""){
			$world->broadcastPacketToViewers($position, LevelSoundEventPacket::create($sound, $position, -1, $this->entity->getIdentifier(), false, false, $this->entity->getId(), null));
		}
	}

	/**
	 * @param array<mixed> $entry
	 */
	private function spawnChild(string $identifier, array $entry) : ?Entity{
		if(!str_contains($identifier, ":")){
			$identifier = "minecraft:" . $identifier;
		}
		$location = $this->entity->getLocation();
		$nbt = CompoundTag::create()
			->setString(EntityFactory::TAG_IDENTIFIER, $identifier)
			->setTag(Entity::TAG_POS, new ListTag([new DoubleTag($location->x), new DoubleTag($location->y), new DoubleTag($location->z)]))
			->setTag(Entity::TAG_ROTATION, new ListTag([new FloatTag($location->yaw), new FloatTag(0.0)]));
		$child = EntityFactory::getInstance()->createFromData($this->entity->getWorld(), $nbt);
		if($child === null){
			return null;
		}
		$child->spawnToAll();
		if($child instanceof BehaviorEntity){
			$event = is_string($entry["spawn_event"] ?? null) ? $entry["spawn_event"] : "minecraft:entity_born";
			$method = is_string($entry["spawn_method"] ?? null) ? $entry["spawn_method"] : "born";
			$child->setData("spawn_method", $method);
			$child->triggerEvent($event, ["other" => $this->entity]);
			if(($entry["should_leash"] ?? false) === true){
				$child->setData("leash_holder", $this->entity->getId());
			}
		}
		$children = $this->entity->getData("child_entities", []);
		$children = is_array($children) ? $children : [];
		$children[] = $child->getId();
		if(count($children) > self::MAX_CHILDREN){
			$children = array_slice($children, -self::MAX_CHILDREN);
		}
		$this->entity->setData("child_entities", $children);
		return $child;
	}
}
