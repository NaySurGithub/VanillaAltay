<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\combat;

use behaviorpack\entity\BehaviorEntity;
use pocketmine\entity\Entity;
use function mt_rand;

/**
 * minecraft:behavior.melee_attack: chases the target and hits it once it is
 * within reach, respecting a cooldown between hits.
 */
class MeleeAttackGoal extends CombatGoal{

	protected int $pathDelay = 0;
	protected int $nextAttackTick = 0;
	protected int $attacks = 0;
	protected int $lastSeenTick = 0;
	protected bool $stoppedRandomly = false;

	public function getControls() : int{
		return self::MOVE | self::LOOK;
	}

	public function canUse() : bool{
		if($this->isSitting() || ($this->bool("attack_once", false) && $this->attacks > 0)){
			return false;
		}
		$target = $this->entity->getTargetEntity();
		if(!CombatGoal::isAttackable($this->entity, $target)){
			return false;
		}
		return $this->entity->getPosition()->distanceSquared($target->getPosition()) <= $this->followRange() ** 2;
	}

	public function canContinue() : bool{
		if(!$this->canUse()){
			return false;
		}
		$target = $this->entity->getTargetEntity();
		if(!$this->bool("track_target", false) && $target !== null && !CombatGoal::canSee($this->entity, $target)){
			return $this->now() - $this->lastSeenTick < 60;
		}
		return true;
	}

	public function start() : void{
		$this->pathDelay = 0;
		$this->lastSeenTick = $this->now();
		$this->stoppedRandomly = false;
	}

	public function stop() : void{
		$this->entity->getNavigator()->stop();
		if($this->entity->isSprinting()){
			$this->entity->setSprinting(false);
		}
	}

	protected function speedMultiplier() : float{
		return $this->float("speed_multiplier", 1.0) * BehaviorEntity::toFloat($this->entity->getData("attack_speed_multiplier"), 1.0);
	}

	protected function cooldownTicks() : int{
		return $this->seconds("cooldown_time", 1.0);
	}

	public function tick(int $tickDiff) : void{
		$target = $this->entity->getTargetEntity();
		if($target === null){
			return;
		}
		$seen = CombatGoal::canSee($this->entity, $target);
		if($seen){
			$this->lastSeenTick = $this->now();
		}
		$this->lookAtEntity($target);
		$this->updatePath($target, $seen, $tickDiff);
		$this->tryAttack($target);
	}

	protected function updatePath(Entity $target, bool $seen, int $tickDiff) : void{
		$navigator = $this->entity->getNavigator();
		$interval = (int) BehaviorEntity::toFloat($this->config["random_stop_interval"] ?? null, 0.0);
		if($interval > 0 && mt_rand(0, $interval) === 0){
			$this->stoppedRandomly = !$this->stoppedRandomly;
		}
		if($this->stoppedRandomly){
			$navigator->stop();
			return;
		}
		$this->pathDelay -= $tickDiff;
		if($this->pathDelay > 0 && !$navigator->isDone()){
			return;
		}
		if(!$seen && !$this->bool("track_target", false)){
			return;
		}
		$distance = $this->entity->getPosition()->distanceSquared($target->getPosition());
		$this->pathDelay = 4 + mt_rand(0, 7);
		if($distance > 1024){
			$this->pathDelay += 10;
		}elseif($distance > 256){
			$this->pathDelay += 5;
		}
		$reached = $navigator->moveTo($target->getPosition(), $this->speedMultiplier(), 0.1);
		if(!$reached && $this->bool("require_complete_path", false)){
			$navigator->stop();
			$this->pathDelay += 15;
		}
	}

	protected function isInReach(Entity $target) : bool{
		return $this->entity->getPosition()->distanceSquared($target->getPosition()) <= $this->meleeReachSquared($target, $this->float("reach_multiplier", 2.0));
	}

	protected function tryAttack(Entity $target) : void{
		if($this->now() < $this->nextAttackTick || !$this->isInReach($target)){
			return;
		}
		if(!$this->isWithinFov($target, $this->float("melee_fov", 90.0))){
			return;
		}
		$this->nextAttackTick = $this->now() + $this->cooldownTicks();
		$this->hit($target);
	}

	protected function hit(Entity $target) : void{
		if(isset($this->config["on_attack"])){
			$this->entity->runTrigger($this->config["on_attack"], ["other" => $target, "target" => $target]);
		}
		if($this->entity->performMeleeAttack($target)){
			$this->attacks++;
		}
		if($this->bool("attack_once", false)){
			$this->entity->setTargetEntity(null);
		}
	}
}
