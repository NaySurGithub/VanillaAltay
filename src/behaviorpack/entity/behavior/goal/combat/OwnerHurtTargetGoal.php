<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\combat;

use pocketmine\entity\Entity;

/**
 * minecraft:behavior.owner_hurt_target: targets the entity the owner last
 * attacked.
 */
class OwnerHurtTargetGoal extends TargetGoal{

	private int $handledTick = -1;

	/**
	 * @return array{int, int}|null
	 */
	protected function record(Entity $owner) : ?array{
		return OwnerCombatTracker::getLastAttacked($owner->getId());
	}

	public function canUse() : bool{
		if($this->isSitting()){
			return false;
		}
		$owner = $this->entity->getOwner();
		if($owner === null || $owner->getWorld() !== $this->entity->getWorld()){
			return false;
		}
		$record = $this->record($owner);
		if($record === null || $record[1] === $this->handledTick){
			return false;
		}
		$this->handledTick = $record[1];
		$candidate = $this->entity->getWorld()->getEntity($record[0]);
		if($candidate === null || !$this->isLegalTarget($candidate)){
			return false;
		}
		if($this->matchEntityType($this->entityTypes(), $candidate) === null){
			return false;
		}
		$this->chosen = $candidate;
		return true;
	}

	public function start() : void{
		$this->mustSee = false;
		parent::start();
	}
}
