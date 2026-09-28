<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\social;

use behaviorpack\entity\behavior\Goal;
use behaviorpack\entity\BehaviorEntity;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use function floor;
use function mt_rand;

/**
 * "minecraft:behavior.follow_owner": walks toward the owner when it gets too
 * far and teleports next to it beyond the maximum distance.
 */
class FollowOwnerGoal extends Goal{

	private ?Entity $owner = null;
	private int $repathTicks = 0;

	public function getControls() : int{
		return self::MOVE | self::LOOK;
	}

	private function startDistance() : float{
		return BehaviorEntity::toFloat($this->config["start_distance"] ?? null, 10.0);
	}

	private function stopDistance() : float{
		return BehaviorEntity::toFloat($this->config["stop_distance"] ?? null, 2.0);
	}

	private function maxDistance() : float{
		return BehaviorEntity::toFloat($this->config["max_distance"] ?? null, 12.0);
	}

	public function canUse() : bool{
		if(!$this->entity->hasComponent("minecraft:is_tamed") || $this->entity->getData("sitting", false) === true || $this->entity->isRiding()){
			return false;
		}
		$owner = $this->entity->getOwner();
		if(!SocialHelper::isValid($owner, $this->entity)){
			return false;
		}
		if($owner->getPosition()->distanceSquared($this->entity->getPosition()) < $this->startDistance() ** 2){
			return false;
		}
		$this->owner = $owner;
		return true;
	}

	public function canContinue() : bool{
		$owner = $this->owner;
		if(!SocialHelper::isValid($owner, $this->entity) || $this->entity->getData("sitting", false) === true){
			return false;
		}
		return $owner->getPosition()->distanceSquared($this->entity->getPosition()) > $this->stopDistance() ** 2;
	}

	public function start() : void{
		$this->repathTicks = 0;
	}

	public function stop() : void{
		$this->owner = null;
		$this->entity->getNavigator()->stop();
	}

	public function tick(int $tickDiff) : void{
		$owner = $this->owner;
		if($owner === null){
			return;
		}
		$this->entity->lookAt($owner->getEyePos());
		$this->repathTicks -= $tickDiff;
		if($this->repathTicks > 0){
			return;
		}
		$this->repathTicks = 10;
		$distanceSquared = $owner->getPosition()->distanceSquared($this->entity->getPosition());
		if($distanceSquared >= $this->maxDistance() ** 2 && ($this->config["can_teleport"] ?? true) !== false && $this->teleportNear($owner)){
			$this->entity->getNavigator()->stop();
			return;
		}
		$speed = BehaviorEntity::toFloat($this->config["speed_multiplier"] ?? null, 1.0);
		$this->entity->getNavigator()->moveTo($owner->getPosition(), $speed, $this->stopDistance());
	}

	private function teleportNear(Entity $owner) : bool{
		$world = $owner->getWorld();
		$base = $owner->getPosition();
		for($attempt = 0; $attempt < 10; ++$attempt){
			$x = (int) floor($base->x) + mt_rand(-3, 3);
			$y = (int) floor($base->y) + mt_rand(-1, 1);
			$z = (int) floor($base->z) + mt_rand(-3, 3);
			if(($x - floor($base->x)) ** 2 + ($z - floor($base->z)) ** 2 < 4){
				continue;
			}
			if(!$world->getBlockAt($x, $y - 1, $z)->isSolid() || $world->getBlockAt($x, $y, $z)->isSolid() || $world->getBlockAt($x, $y + 1, $z)->isSolid()){
				continue;
			}
			$this->entity->teleport(new Vector3($x + 0.5, $y, $z + 0.5));
			return true;
		}
		return false;
	}
}
