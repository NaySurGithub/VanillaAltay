<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\movement;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\block\Liquid;
use pocketmine\block\PowderSnow;
use pocketmine\player\Player;
use function floor;

/**
 * "minecraft:variable_max_auto_step": the height the entity steps up without
 * jumping, depending on whether a rider controls it or its jump is prevented.
 */
final class VariableMaxAutoStepSystem extends EntitySystem{

	public const DEFAULT_STEP = 0.5625;

	private ?float $previousStep = null;

	public function onAdd() : void{
		$this->previousStep ??= $this->entity->getStepHeight();
		$this->apply();
	}

	public function onRemove() : void{
		$this->entity->setStepHeight($this->previousStep ?? 0.0);
		$this->entity->setData("max_auto_step", null);
	}

	public function tick(int $tickDiff) : void{
		$this->apply();
	}

	private function apply() : void{
		$key = "base_value";
		if($this->isControlled()){
			$key = "controlled_value";
		}elseif($this->isJumpPrevented()){
			$key = "jump_prevented_value";
		}
		$step = BehaviorEntity::toFloat($this->config[$key] ?? null, self::DEFAULT_STEP);
		if($this->entity->getStepHeight() !== $step){
			$this->entity->setStepHeight($step);
		}
		$this->entity->setData("max_auto_step", $step);
	}

	private function isControlled() : bool{
		foreach($this->entity->getPassengers() as $passenger){
			if($passenger instanceof Player){
				return true;
			}
		}
		return false;
	}

	private function isJumpPrevented() : bool{
		$location = $this->entity->getLocation();
		$block = $this->entity->getWorld()->getBlockAt((int) floor($location->x), (int) floor($location->y), (int) floor($location->z));
		return $block instanceof Liquid || $block instanceof PowderSnow;
	}
}
