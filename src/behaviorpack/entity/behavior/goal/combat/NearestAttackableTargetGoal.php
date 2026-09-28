<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\combat;

use behaviorpack\entity\BehaviorEntity;
use pocketmine\entity\Entity;
use pocketmine\math\AxisAlignedBB;
use function abs;
use function max;
use function mt_rand;

/**
 * minecraft:behavior.nearest_attackable_target: picks the nearest entity
 * accepted by one of the "entity_types" filters as the attack target.
 */
class NearestAttackableTargetGoal extends TargetGoal{

	private int $nextScanTick = 0;

	/** @var array<mixed> */
	protected array $chosenType = [];

	protected function scanInterval() : int{
		$interval = $this->config["attack_interval"] ?? null;
		if($interval !== null){
			$ticks = (int) (BehaviorEntity::rangeValue($interval, 0.0) * 20);
			if($ticks > 0){
				return $ticks;
			}
		}
		return max(1, (int) $this->float("scan_interval", 10.0));
	}

	public function canUse() : bool{
		if($this->isSitting()){
			return false;
		}
		$now = $this->now();
		if($now < $this->nextScanTick){
			return false;
		}
		$this->nextScanTick = $now + $this->scanInterval();
		$current = $this->entity->getTargetEntity();
		if($current !== null && !$this->bool("reselect_targets", false)){
			return false;
		}
		$found = $this->findTarget();
		if($found === null){
			return false;
		}
		[$this->chosen, $this->chosenType] = $found;
		return true;
	}

	public function start() : void{
		$this->mustSee = (bool) ($this->chosenType["must_see"] ?? $this->bool("must_see", false));
		$this->mustSeeForget = BehaviorEntity::toFloat($this->chosenType["must_see_forget_duration"] ?? $this->config["must_see_forget_duration"] ?? null, 3.0);
		parent::start();
		$this->applySpeed();
	}

	public function tick(int $tickDiff) : void{
		if(!$this->bool("reselect_targets", false)){
			return;
		}
		$now = $this->now();
		if($now < $this->nextScanTick){
			return;
		}
		$this->nextScanTick = $now + $this->scanInterval();
		$found = $this->findTarget();
		if($found === null || $found[0] === $this->chosen){
			return;
		}
		[$this->chosen, $this->chosenType] = $found;
		$this->lastSeenTick = $now;
		$this->lostSinceTick = -1;
		$this->entity->setTargetEntity($this->chosen);
		$this->applySpeed();
	}

	protected function applySpeed() : void{
		$sprint = BehaviorEntity::toFloat($this->chosenType["sprint_speed_multiplier"] ?? null, 1.0);
		$walk = BehaviorEntity::toFloat($this->chosenType["walk_speed_multiplier"] ?? null, 1.0);
		$this->entity->setData("attack_speed_multiplier", $sprint !== 1.0 ? $sprint : $walk);
	}

	/**
	 * Scores a candidate; lower wins. The base goal prefers the nearest one.
	 *
	 * @param array<mixed> $type
	 */
	protected function score(Entity $candidate, array $type, float $distanceSquared) : float{
		return $distanceSquared;
	}

	/**
	 * @return array{Entity, array<mixed>}|null
	 */
	protected function findTarget() : ?array{
		$types = $this->entityTypes();
		$withinRadius = $this->float("within_radius", 0.0);
		$maxRange = $withinRadius > 0 ? $withinRadius : $this->followRange();
		foreach($types as $type){
			$maxRange = max($maxRange, BehaviorEntity::toFloat($type["max_dist"] ?? null, 0.0));
		}
		$height = $this->float("target_search_height", -1.0);
		$vertical = $height > 0 ? $height : $maxRange;
		$position = $this->entity->getPosition();
		$box = new AxisAlignedBB(
			$position->x - $maxRange,
			$position->y - $vertical,
			$position->z - $maxRange,
			$position->x + $maxRange,
			$position->y + $vertical,
			$position->z + $maxRange
		);
		$globalMustSee = $this->bool("must_see", false);
		$mustReach = $this->bool("must_reach", false);
		$best = null;
		$bestScore = 0.0;
		foreach($this->entity->getWorld()->getNearbyEntities($box, $this->entity) as $candidate){
			if(!$this->isLegalTarget($candidate)){
				continue;
			}
			$type = $this->matchEntityType($types, $candidate);
			if($type === null){
				continue;
			}
			$distance = $withinRadius > 0 ? $withinRadius : BehaviorEntity::toFloat($type["max_dist"] ?? null, $this->followRange());
			$distanceSquared = $position->distanceSquared($candidate->getPosition());
			if($distanceSquared > $distance * $distance){
				continue;
			}
			if($mustReach && abs($candidate->getPosition()->y - $position->y) > 3.0){
				continue;
			}
			if((bool) ($type["must_see"] ?? $globalMustSee) && !CombatGoal::canSee($this->entity, $candidate)){
				continue;
			}
			$score = $this->score($candidate, $type, $distanceSquared);
			if($best === null || $score < $bestScore){
				$best = [$candidate, $type];
				$bestScore = $score;
			}
		}
		if($best === null && $this->scanInterval() > 1){
			$this->nextScanTick += mt_rand(0, 5);
		}
		return $best;
	}
}
