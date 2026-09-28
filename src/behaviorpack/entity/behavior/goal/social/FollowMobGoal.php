<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\social;

use behaviorpack\entity\behavior\Goal;
use behaviorpack\entity\BehaviorEntity;
use pocketmine\entity\Living;
use pocketmine\player\Player;

/**
 * "minecraft:behavior.follow_mob": follows the nearest mob of another type.
 */
class FollowMobGoal extends Goal{

	private ?Living $followed = null;
	private int $repathTicks = 0;

	public function getControls() : int{
		return self::MOVE | self::LOOK;
	}

	private function searchRange() : float{
		return BehaviorEntity::toFloat($this->config["search_range"] ?? null, 0.0) > 0 ? BehaviorEntity::toFloat($this->config["search_range"], 0.0) : 6.0;
	}

	private function stopDistance() : float{
		return BehaviorEntity::toFloat($this->config["stop_distance"] ?? null, 2.0);
	}

	public function canUse() : bool{
		$range = $this->searchRange();
		$best = null;
		$bestDistance = $range * $range;
		foreach($this->entity->getWorld()->getNearbyEntities($this->entity->getBoundingBox()->expandedCopy($range, $range, $range), $this->entity) as $other){
			if(!$other instanceof Living || $other instanceof Player || !$other->isAlive()){
				continue;
			}
			if($other instanceof BehaviorEntity && $other->getIdentifier() === $this->entity->getIdentifier()){
				continue;
			}
			$distance = $other->getPosition()->distanceSquared($this->entity->getPosition());
			if($distance < $bestDistance){
				$best = $other;
				$bestDistance = $distance;
			}
		}
		$this->followed = $best;
		return $best !== null;
	}

	public function canContinue() : bool{
		$followed = $this->followed;
		if(!SocialHelper::isValid($followed, $this->entity)){
			return false;
		}
		return $followed->getPosition()->distanceSquared($this->entity->getPosition()) <= ($this->searchRange() * 1.5) ** 2;
	}

	public function start() : void{
		$this->repathTicks = 0;
	}

	public function stop() : void{
		$this->followed = null;
		$this->entity->getNavigator()->stop();
	}

	public function tick(int $tickDiff) : void{
		$followed = $this->followed;
		if($followed === null){
			return;
		}
		$this->entity->lookAt($followed->getEyePos());
		$this->repathTicks -= $tickDiff;
		if($this->repathTicks > 0){
			return;
		}
		$this->repathTicks = 10;
		$navigator = $this->entity->getNavigator();
		if($followed->getPosition()->distanceSquared($this->entity->getPosition()) <= $this->stopDistance() ** 2){
			$navigator->stop();
			return;
		}
		$navigator->moveTo($followed->getPosition(), BehaviorEntity::toFloat($this->config["speed_multiplier"] ?? null, 1.0), $this->stopDistance());
	}
}
