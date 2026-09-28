<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\state;

use behaviorpack\entity\behavior\EntitySystem;
use function max;

/**
 * minecraft:experience_reward: the experience dropped on death and when the
 * entity is bred, both Molang expressions.
 */
final class ExperienceRewardSystem extends EntitySystem{

	public function onAdd() : void{
		$this->entity->setData("experience_reward", [
			"on_death" => $this->config["on_death"] ?? 0,
			"on_bred" => $this->config["on_bred"] ?? 0
		]);
	}

	public function onRemove() : void{
		$this->entity->setData("experience_reward", null);
		$this->entity->setXpReward(null);
	}

	public function getDeathReward() : int{
		return max(0, (int) $this->entity->molang($this->config["on_death"] ?? 0));
	}

	/**
	 * Experience dropped when this entity is bred.
	 */
	public function getBredReward() : int{
		return max(0, (int) $this->entity->molang($this->config["on_bred"] ?? 0));
	}

	public function onDeath() : void{
		$this->entity->setXpReward($this->getDeathReward());
	}
}
