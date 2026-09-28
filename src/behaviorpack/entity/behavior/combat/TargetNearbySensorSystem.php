<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\combat;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\entity\Entity;
use pocketmine\math\VoxelRayTrace;

/**
 * minecraft:target_nearby_sensor: runs events when the target enters the
 * inside range, leaves the outside range, or is lost from sight inside the
 * range. The last state is stored as "target_nearby_state".
 */
final class TargetNearbySensorSystem extends EntitySystem{

	private const STATE_KEY = "target_nearby_state";

	public function onRemove() : void{
		$this->entity->setData(self::STATE_KEY, null);
	}

	public function onTargetChanged(?Entity $previous, ?Entity $target) : void{
		$this->entity->setData(self::STATE_KEY, null);
	}

	public function tick(int $tickDiff) : void{
		$target = $this->entity->getTargetEntity();
		if($target === null){
			return;
		}
		$inside = BehaviorEntity::toFloat($this->config["inside_range"] ?? null, 1.0);
		$outside = BehaviorEntity::toFloat($this->config["outside_range"] ?? null, 5.0);
		$mustSee = ($this->config["must_see"] ?? false) === true;
		$distance = $this->entity->getPosition()->distance($target->getPosition());
		$state = $this->entity->getData(self::STATE_KEY);
		$context = ["other" => $target, "target" => $target];
		if($distance <= $inside){
			$visible = !$mustSee || self::canSee($this->entity, $target);
			if($visible && $state !== "inside"){
				$this->entity->setData(self::STATE_KEY, "inside");
				$this->fire("on_inside_range", $context);
			}elseif(!$visible && $state === "inside"){
				$this->entity->setData(self::STATE_KEY, "lost");
				$this->fire("on_vision_lost_inside_range", $context);
			}
			return;
		}
		if($distance > $outside && $state !== "outside"){
			$this->entity->setData(self::STATE_KEY, "outside");
			$this->fire("on_outside_range", $context);
		}
	}

	/**
	 * @param array<string, mixed> $context
	 */
	private function fire(string $key, array $context) : void{
		$trigger = $this->config[$key] ?? null;
		if($trigger !== null){
			$this->entity->runTrigger($trigger, $context);
		}
	}

	/**
	 * Returns whether no solid block stands between the eyes of both entities.
	 */
	public static function canSee(Entity $from, Entity $to) : bool{
		$start = $from->getEyePos();
		$end = $to->getEyePos();
		if($start->distanceSquared($end) < 0.0001){
			return true;
		}
		$world = $from->getWorld();
		foreach(VoxelRayTrace::betweenPoints($start, $end) as $position){
			$block = $world->getBlockAt((int) $position->x, (int) $position->y, (int) $position->z);
			if($block->isSolid() && $block->calculateIntercept($start, $end) !== null){
				return false;
			}
		}
		return true;
	}
}
