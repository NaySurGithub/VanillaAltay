<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\taming;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use behaviorpack\entity\behavior\PlayerInputTracker;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use function array_intersect;
use function array_values;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function max;
use function spl_object_id;
use function strtolower;

/**
 * minecraft:rideable: players mount the entity by interacting with it, mobs
 * of the allowed families are pulled into free seats, and sneaking riders
 * get off. The seats tell the riders where they sit and how far they may
 * turn.
 */
final class RideableSystem extends EntitySystem{

	private int $pullCooldown = 0;

	/** @var array<int, true> */
	private array $configured = [];

	public function onAdd() : void{
		$text = $this->config["interact_text"] ?? null;
		$this->entity->setMetadata(EntityMetadataProperties::INTERACTIVE_TAG, "string", is_string($text) ? $text : "");
		$this->entity->setMetadata(EntityMetadataProperties::CONTROLLING_RIDER_SEAT_NUMBER, "byte", $this->getControllingSeat());
		$this->configured = [];
	}

	public function onRemove() : void{
		$this->entity->setMetadata(EntityMetadataProperties::INTERACTIVE_TAG, "string", "");
		foreach($this->entity->getPassengers() as $passenger){
			$this->dismount($passenger);
		}
	}

	public function onDeath() : void{
		foreach($this->entity->getPassengers() as $passenger){
			$this->dismount($passenger);
		}
	}

	public function getSeatCount() : int{
		return max(1, (int) BehaviorEntity::toFloat($this->config["seat_count"] ?? null, 1.0));
	}

	public function getControllingSeat() : int{
		return max(0, (int) BehaviorEntity::toFloat($this->config["controlling_seat"] ?? null, 0.0));
	}

	/**
	 * Returns the rider in the controlling seat, if any.
	 */
	public function getControllingRider() : ?Entity{
		return $this->entity->getPassengers()[$this->getControllingSeat()] ?? null;
	}

	/**
	 * @return list<array<mixed>>
	 */
	private function getSeats() : array{
		return TamingHelper::objectList($this->config["seats"] ?? null);
	}

	/**
	 * Returns the seat a passenger uses for the current rider count: the
	 * seat at its index whose rider count range contains the count.
	 *
	 * @return array<mixed>|null
	 */
	private function getSeatFor(int $index) : ?array{
		$riders = count($this->entity->getPassengers());
		$candidates = [];
		foreach($this->getSeats() as $seat){
			$min = (int) BehaviorEntity::toFloat($seat["min_rider_count"] ?? null, 0.0);
			$max = (int) BehaviorEntity::toFloat($seat["max_rider_count"] ?? null, (float) $this->getSeatCount());
			if($riders >= $min && $riders <= $max){
				$candidates[] = $seat;
			}
		}
		return $candidates[$index] ?? ($this->getSeats()[$index] ?? null);
	}

	public function canCarry(Entity $entity) : bool{
		if($entity === $this->entity || $entity->isRiding() || count($this->entity->getPassengers()) >= $this->getSeatCount()){
			return false;
		}
		$families = $this->config["family_types"] ?? [];
		if(!is_array($families) || count($families) === 0){
			return true;
		}
		$allowed = [];
		foreach($families as $family){
			if(is_string($family)){
				$allowed[] = strtolower($family);
			}
		}
		return count(array_intersect($allowed, BehaviorEntity::familiesOf($entity))) > 0;
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if($player->isRiding() || $this->entity->isRiding()){
			return false;
		}
		if(($this->config["crouching_skip_interact"] ?? true) !== false && $player->isSneaking()){
			return false;
		}
		if(SittableSystem::isSitting($this->entity) || $this->entity->getData("leash_holder") !== null){
			return false;
		}
		if(TamingHelper::isUsedByComponents($this->entity, $player->getInventory()->getItemInHand())){
			return false;
		}
		if(!$this->canCarry($player)){
			return false;
		}
		return $this->mount($player);
	}

	public function mount(Entity $entity) : bool{
		if(!$this->entity->addPassenger($entity)){
			return false;
		}
		$this->entity->getNavigator()->stop();
		$this->configureRiders();
		return true;
	}

	public function dismount(Entity $entity) : void{
		unset($this->configured[spl_object_id($entity)]);
		if($entity instanceof Player){
			$properties = $entity->getNetworkProperties();
			$properties->setByte(EntityMetadataProperties::RIDER_ROTATION_LOCKED, 0);
			$properties->setGenericFlag(EntityMetadataFlags::RIDING, false);
		}
		$this->entity->removePassenger($entity);
		if($entity instanceof Player){
			$entity->sendData($this->viewersOf($entity));
		}
	}

	/**
	 * @return list<Player>
	 */
	private function viewersOf(Entity $entity) : array{
		$targets = array_values($entity->getViewers());
		if($entity instanceof Player && !in_array($entity, $targets, true)){
			$targets[] = $entity;
		}
		return $targets;
	}

	/**
	 * Sends each rider the rotation limits of its seat.
	 */
	private function configureRiders() : void{
		$current = [];
		foreach($this->entity->getPassengers() as $index => $passenger){
			$id = spl_object_id($passenger);
			$current[$id] = true;
			$seat = $this->getSeatFor($index);
			$properties = $passenger->getNetworkProperties();
			$lock = is_array($seat) ? ($seat["lock_rider_rotation"] ?? null) : null;
			$rotateBy = is_array($seat) ? BehaviorEntity::toFloat($seat["rotate_rider_by"] ?? null, 0.0) : 0.0;
			if($lock !== null){
				$limit = BehaviorEntity::toFloat($lock, 181.0);
				$properties->setByte(EntityMetadataProperties::RIDER_ROTATION_LOCKED, 1);
				$properties->setFloat(EntityMetadataProperties::RIDER_MAX_ROTATION, $limit);
				$properties->setFloat(EntityMetadataProperties::RIDER_MIN_ROTATION, -$limit);
			}else{
				$properties->setByte(EntityMetadataProperties::RIDER_ROTATION_LOCKED, 0);
			}
			$properties->setFloat(EntityMetadataProperties::RIDER_SEAT_ROTATION_OFFSET, $rotateBy);
			$properties->setGenericFlag(EntityMetadataFlags::RIDING, true);
			$passenger->sendData($this->viewersOf($passenger));
		}
		$this->configured = $current;
	}

	public function tick(int $tickDiff) : void{
		$passengers = $this->entity->getPassengers();
		foreach($passengers as $passenger){
			if($passenger instanceof Player && PlayerInputTracker::isSneaking($passenger)){
				$this->dismount($passenger);
			}elseif($passenger->isClosed() || !$passenger->isAlive()){
				$this->dismount($passenger);
			}
		}
		$passengers = $this->entity->getPassengers();
		$ids = [];
		foreach($passengers as $passenger){
			$ids[spl_object_id($passenger)] = true;
		}
		if($ids != $this->configured){
			$this->configureRiders();
		}
		if(($this->config["pull_in_entities"] ?? false) !== true || count($passengers) >= $this->getSeatCount()){
			return;
		}
		$this->pullCooldown -= $tickDiff;
		if($this->pullCooldown > 0){
			return;
		}
		$this->pullCooldown = 10;
		foreach($this->entity->getWorld()->getNearbyEntities($this->entity->getBoundingBox()->expandedCopy(0.2, 0.0, 0.2), $this->entity) as $entity){
			if(!$entity instanceof Living || $entity instanceof Player || !$entity->isAlive() || $entity->getVehicle() !== null || count($entity->getPassengers()) > 0){
				continue;
			}
			if($this->canCarry($entity)){
				$this->mount($entity);
				if(count($this->entity->getPassengers()) >= $this->getSeatCount()){
					return;
				}
			}
		}
	}
}
