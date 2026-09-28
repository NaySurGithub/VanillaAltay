<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\combat;

use behaviorpack\entity\BehaviorEntity;
use pocketmine\entity\Entity;

/**
 * Base of the goals that pick an attack target. The target is kept while it
 * stays attackable and within the follow range; with "must_see" it is
 * dropped after being out of sight for "must_see_forget_duration", and a
 * lost target is remembered for "persist_time".
 */
abstract class TargetGoal extends CombatGoal{

	protected ?Entity $chosen = null;
	protected int $lastSeenTick = 0;
	protected int $lostSinceTick = -1;
	protected float $mustSeeForget = 3.0;
	protected bool $mustSee = false;

	public function getControls() : int{
		return self::TARGET;
	}

	public function canContinue() : bool{
		$target = $this->entity->getTargetEntity();
		if($target === null || $this->chosen === null || $target !== $this->chosen){
			return false;
		}
		if(!CombatGoal::isAttackable($this->entity, $target) || $this->entity->isOwnedBy($target)){
			return false;
		}
		$now = $this->now();
		$valid = $this->entity->getPosition()->distanceSquared($target->getPosition()) <= $this->followRange() ** 2;
		if($valid && $this->mustSee){
			if(CombatGoal::canSee($this->entity, $target)){
				$this->lastSeenTick = $now;
			}elseif($now - $this->lastSeenTick > (int) ($this->mustSeeForget * 20)){
				$valid = false;
			}
		}
		if($valid){
			$this->lostSinceTick = -1;
			return true;
		}
		if($this->lostSinceTick < 0){
			$this->lostSinceTick = $now;
		}
		return $now - $this->lostSinceTick < (int) ($this->float("persist_time", 0.0) * 20);
	}

	public function start() : void{
		if($this->chosen === null){
			return;
		}
		$this->lastSeenTick = $this->now();
		$this->lostSinceTick = -1;
		$this->entity->setTargetEntity($this->chosen);
		if($this->bool("set_persistent", false)){
			$this->entity->setData("persistent", true);
		}
	}

	public function stop() : void{
		if($this->chosen !== null && $this->entity->getTargetEntity() === $this->chosen){
			$this->entity->setTargetEntity(null);
		}
		$this->entity->setData("attack_speed_multiplier", null);
		$this->chosen = null;
	}

	/**
	 * Returns whether a candidate is a legal target: attackable, not the
	 * owner, and not a fellow pet of the same owner.
	 */
	protected function isLegalTarget(?Entity $candidate) : bool{
		if(!CombatGoal::isAttackable($this->entity, $candidate) || $this->entity->isOwnedBy($candidate)){
			return false;
		}
		$owner = $this->entity->getOwner();
		if($owner !== null && $candidate instanceof BehaviorEntity && $candidate->isOwnedBy($owner)){
			return false;
		}
		return true;
	}
}
