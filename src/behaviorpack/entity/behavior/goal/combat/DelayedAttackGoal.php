<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\combat;

use pocketmine\entity\Entity;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;

/**
 * minecraft:behavior.delayed_attack: melee attack with a wind-up; the hit
 * lands at "hit_delay_pct" of "attack_duration" if the target is still in
 * reach.
 */
class DelayedAttackGoal extends MeleeAttackGoal{

	private int $attackStartTick = -1;
	private bool $hitDone = false;

	public function stop() : void{
		parent::stop();
		$this->endAttack();
	}

	public function isInterruptable() : bool{
		return $this->attackStartTick < 0;
	}

	public function tick(int $tickDiff) : void{
		$target = $this->entity->getTargetEntity();
		if($target === null){
			return;
		}
		if($this->attackStartTick < 0){
			parent::tick($tickDiff);
			return;
		}
		$this->lookAtEntity($target);
		$this->entity->getNavigator()->stop();
		$elapsed = $this->now() - $this->attackStartTick;
		$duration = (int) ($this->float("attack_duration", 0.75) * 20);
		if(!$this->hitDone && $elapsed >= (int) ($duration * $this->float("hit_delay_pct", 0.5))){
			$this->hitDone = true;
			if($this->isInReach($target)){
				$this->hit($target);
			}
		}
		if($elapsed >= $duration){
			$this->endAttack();
		}
	}

	protected function tryAttack(Entity $target) : void{
		if($this->now() < $this->nextAttackTick || !$this->isInReach($target)){
			return;
		}
		$this->attackStartTick = $this->now();
		$this->hitDone = false;
		$this->nextAttackTick = $this->now() + (int) ($this->float("attack_duration", 0.75) * 20) + $this->cooldownTicks();
		$this->entity->setFlag(EntityMetadataFlags::DELAYED_ATTACKING, true);
		$this->entity->getNavigator()->stop();
	}

	private function endAttack() : void{
		if($this->attackStartTick >= 0){
			$this->entity->setFlag(EntityMetadataFlags::DELAYED_ATTACKING, false);
		}
		$this->attackStartTick = -1;
		$this->hitDone = false;
	}
}
