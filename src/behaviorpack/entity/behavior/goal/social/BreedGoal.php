<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\social;

use behaviorpack\entity\behavior\Goal;
use behaviorpack\entity\behavior\taming\BreedableSystem;
use behaviorpack\entity\BehaviorEntity;

/**
 * "minecraft:behavior.breed": an entity in love walks to a partner in love
 * and breeds with it after three seconds together.
 */
class BreedGoal extends Goal{

	private const BREED_TICKS = 60;
	private const SEARCH_RANGE = 8.0;

	private ?BehaviorEntity $partner = null;
	private int $loveTicks = 0;

	public function getControls() : int{
		return self::MOVE | self::LOOK;
	}

	private function breedable(BehaviorEntity $entity) : ?BreedableSystem{
		$system = $entity->getSystem("minecraft:breedable");
		return $system instanceof BreedableSystem ? $system : null;
	}

	public function canUse() : bool{
		$system = $this->breedable($this->entity);
		if($system === null || !$system->isInLove()){
			return false;
		}
		$this->partner = SocialHelper::nearestSameType($this->entity, self::SEARCH_RANGE, function(BehaviorEntity $other) use ($system) : bool{
			$otherSystem = $this->breedable($other);
			return $otherSystem !== null && $otherSystem->isInLove() && $system->canBreedWith($other);
		});
		return $this->partner !== null;
	}

	public function canContinue() : bool{
		$partner = $this->partner;
		if(!SocialHelper::isValid($partner, $this->entity)){
			return false;
		}
		$system = $this->breedable($this->entity);
		$otherSystem = $this->breedable($partner);
		return $system !== null && $otherSystem !== null && $system->isInLove() && $otherSystem->isInLove() && $this->loveTicks < self::BREED_TICKS;
	}

	public function start() : void{
		$this->loveTicks = 0;
	}

	public function stop() : void{
		$this->partner = null;
		$this->loveTicks = 0;
		$this->entity->getNavigator()->stop();
	}

	public function tick(int $tickDiff) : void{
		$partner = $this->partner;
		if($partner === null){
			return;
		}
		$this->entity->lookAt($partner->getEyePos());
		$this->entity->getNavigator()->moveTo($partner->getPosition(), BehaviorEntity::toFloat($this->config["speed_multiplier"] ?? null, 1.0), 1.0);
		if($partner->getPosition()->distanceSquared($this->entity->getPosition()) > 9){
			return;
		}
		$this->loveTicks += $tickDiff;
		if($this->loveTicks >= self::BREED_TICKS && $this->entity->getId() < $partner->getId()){
			$this->breedable($this->entity)?->breedWith($partner);
		}
	}
}
