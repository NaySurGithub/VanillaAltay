<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\combat;

use behaviorpack\entity\BehaviorEntity;
use pocketmine\entity\Entity;
use pocketmine\math\AxisAlignedBB;

/**
 * minecraft:behavior.hurt_by_target: targets the last attacker, and with
 * "alert_same_type" makes the nearby entities of the same type target it
 * too.
 */
class HurtByTargetGoal extends TargetGoal{

	private int $handledHurtTick = -1;

	public function canUse() : bool{
		$hurtTick = $this->entity->getLastHurtTick();
		if($hurtTick < 0 || $hurtTick === $this->handledHurtTick){
			return false;
		}
		$attacker = $this->entity->getLastHurtBy();
		if($attacker === null){
			return false;
		}
		$this->handledHurtTick = $hurtTick;
		if($this->entity->isOwnedBy($attacker) && !$this->bool("hurt_owner", false)){
			return false;
		}
		if(!CombatGoal::isAttackable($this->entity, $attacker)){
			return false;
		}
		if($this->matchEntityType($this->entityTypes(), $attacker) === null){
			return false;
		}
		$this->chosen = $attacker;
		return true;
	}

	protected function isLegalTarget(?Entity $candidate) : bool{
		return CombatGoal::isAttackable($this->entity, $candidate);
	}

	public function canContinue() : bool{
		if($this->bool("hurt_owner", false) && $this->chosen !== null && $this->entity->isOwnedBy($this->chosen)){
			return $this->entity->getTargetEntity() === $this->chosen && CombatGoal::isAttackable($this->entity, $this->chosen);
		}
		return parent::canContinue();
	}

	public function start() : void{
		$this->mustSee = false;
		parent::start();
		if($this->chosen !== null && $this->bool("alert_same_type", false)){
			$this->alertOthers($this->chosen);
		}
	}

	public function tick(int $tickDiff) : void{
		$hurtTick = $this->entity->getLastHurtTick();
		if($hurtTick === $this->handledHurtTick){
			return;
		}
		$this->handledHurtTick = $hurtTick;
		$attacker = $this->entity->getLastHurtBy();
		if($attacker === null || $attacker === $this->chosen || !CombatGoal::isAttackable($this->entity, $attacker)){
			return;
		}
		if($this->entity->isOwnedBy($attacker) && !$this->bool("hurt_owner", false)){
			return;
		}
		if($this->matchEntityType($this->entityTypes(), $attacker) === null){
			return;
		}
		$this->chosen = $attacker;
		$this->lastSeenTick = $this->now();
		$this->lostSinceTick = -1;
		$this->entity->setTargetEntity($attacker);
		if($this->bool("alert_same_type", false)){
			$this->alertOthers($attacker);
		}
	}

	private function alertOthers(Entity $attacker) : void{
		$range = $this->followRange();
		$position = $this->entity->getPosition();
		$box = new AxisAlignedBB($position->x - $range, $position->y - 10, $position->z - $range, $position->x + $range, $position->y + 10, $position->z + $range);
		foreach($this->entity->getWorld()->getNearbyEntities($box, $this->entity) as $other){
			if(!$other instanceof BehaviorEntity || $other === $attacker || !$other->isAlive()){
				continue;
			}
			if($other->getIdentifier() !== $this->entity->getIdentifier() || $other->getTargetEntity() !== null || $other->isOwnedBy($attacker)){
				continue;
			}
			$other->setTargetEntity($attacker);
		}
	}
}
