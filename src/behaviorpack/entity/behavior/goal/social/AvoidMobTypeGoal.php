<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\social;

use behaviorpack\entity\behavior\Goal;
use behaviorpack\entity\BehaviorEntity;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\Utils;
use function array_is_list;
use function cos;
use function is_array;
use function max;
use function sin;
use function sqrt;

/**
 * "minecraft:behavior.avoid_mob_type": flees away from the nearest mob that
 * matches one of the entity types.
 */
class AvoidMobTypeGoal extends Goal{

	private ?Entity $threat = null;

	/** @var array<mixed> */
	private array $type = [];

	private ?Vector3 $fleeTarget = null;

	/** @var array<int, int> */
	private array $cooldowns = [];

	private int $currentTick = 0;

	public function getControls() : int{
		return self::MOVE;
	}

	/**
	 * @return list<array<mixed>>
	 */
	private function entityTypes() : array{
		$types = $this->config["entity_types"] ?? [];
		if(!is_array($types)){
			return [];
		}
		if(!array_is_list($types)){
			$types = [$types];
		}
		$result = [];
		foreach($types as $type){
			if(is_array($type)){
				$result[] = $type;
			}
		}
		return $result;
	}

	private function maxDist(array $type) : float{
		return BehaviorEntity::toFloat($type["max_dist"] ?? $this->config["max_dist"] ?? null, 3.0);
	}

	public function canUse() : bool{
		++$this->currentTick;
		if($this->entity->hasComponent("minecraft:is_tamed") && $this->entity->getData("sitting", false) === true){
			return false;
		}
		$best = null;
		$bestType = [];
		$bestDistance = null;
		foreach($this->entityTypes() as $index => $type){
			if(($this->cooldowns[$index] ?? 0) > $this->currentTick){
				continue;
			}
			$range = $this->maxDist($type);
			foreach($this->entity->getWorld()->getNearbyEntities($this->entity->getBoundingBox()->expandedCopy($range, 3, $range), $this->entity) as $other){
				if(!$other instanceof Living || !$other->isAlive() || ($other instanceof Player && ($other->isSpectator() || $other->isCreative()))){
					continue;
				}
				$distance = $other->getPosition()->distanceSquared($this->entity->getPosition());
				if($distance > $range * $range || ($bestDistance !== null && $distance >= $bestDistance)){
					continue;
				}
				if(is_array($type["filters"] ?? null) && !$this->entity->testFilter($type["filters"], ["other" => $other])){
					continue;
				}
				if(($type["check_if_outnumbered"] ?? false) === true && !$this->isOutnumbered($other, $range)){
					continue;
				}
				$best = $other;
				$bestType = $type + ["__index" => $index];
				$bestDistance = $distance;
			}
		}
		if($best === null){
			return false;
		}
		$probability = BehaviorEntity::toFloat($this->config["probability_per_strength"] ?? null, 1.0);
		if($probability < 1.0 && Utils::getRandomFloat() > $probability){
			return false;
		}
		$target = $this->findFleeTarget($best);
		if($target === null){
			return false;
		}
		$this->threat = $best;
		$this->type = $bestType;
		$this->fleeTarget = $target;
		return true;
	}

	private function isOutnumbered(Entity $other, float $range) : bool{
		$allies = 0;
		$enemies = 0;
		foreach($this->entity->getWorld()->getNearbyEntities($this->entity->getBoundingBox()->expandedCopy($range, 3, $range)) as $near){
			if($near instanceof BehaviorEntity && $near->getIdentifier() === $this->entity->getIdentifier()){
				++$allies;
			}elseif($near::class === $other::class){
				++$enemies;
			}
		}
		return $enemies > $allies;
	}

	private function findFleeTarget(Entity $threat) : ?Vector3{
		$position = $this->entity->getPosition();
		$dx = $position->x - $threat->getPosition()->x;
		$dz = $position->z - $threat->getPosition()->z;
		$length = max(sqrt($dx * $dx + $dz * $dz), 0.01);
		$flee = BehaviorEntity::toFloat($this->config["max_flee"] ?? null, 10.0);
		for($attempt = 0; $attempt < 10; ++$attempt){
			$distance = $flee * (0.5 + Utils::getRandomFloat() * 0.5);
			$angle = (Utils::getRandomFloat() - 0.5) * 1.2;
			$x = $dx / $length;
			$z = $dz / $length;
			$rx = $x * cos($angle) - $z * sin($angle);
			$rz = $x * sin($angle) + $z * cos($angle);
			$target = new Vector3($position->x + $rx * $distance, $position->y, $position->z + $rz * $distance);
			if($target->distanceSquared($threat->getPosition()) > $position->distanceSquared($threat->getPosition())){
				return $target;
			}
		}
		return null;
	}

	public function canContinue() : bool{
		$threat = $this->threat;
		if(!SocialHelper::isValid($threat, $this->entity) || $this->entity->getNavigator()->isDone()){
			return false;
		}
		$range = $this->maxDist($this->type) + BehaviorEntity::toFloat($this->config["max_flee"] ?? null, 10.0);
		return $threat->getPosition()->distanceSquared($this->entity->getPosition()) < $range * $range;
	}

	public function start() : void{
		if($this->fleeTarget !== null){
			$this->entity->getNavigator()->moveTo($this->fleeTarget, BehaviorEntity::toFloat($this->type["walk_speed_multiplier"] ?? null, 1.0), 1.0);
		}
		if(($this->config["remove_target"] ?? false) === true && $this->threat !== null && $this->entity->getTargetEntity() === $this->threat){
			$this->entity->setTargetEntity(null);
		}
	}

	public function stop() : void{
		$threat = $this->threat;
		$escaped = $threat === null || !SocialHelper::isValid($threat, $this->entity)
			|| $threat->getPosition()->distanceSquared($this->entity->getPosition()) >= $this->maxDist($this->type) ** 2;
		if($escaped){
			$context = $threat === null ? [] : ["other" => $threat];
			$onEscape = $this->config["on_escape_event"] ?? $this->config["on_escape"] ?? null;
			if(is_array($onEscape) && array_is_list($onEscape)){
				foreach($onEscape as $trigger){
					$this->entity->runTrigger($trigger, $context);
				}
			}elseif($onEscape !== null){
				$this->entity->runTrigger($onEscape, $context);
			}
		}
		$index = $this->type["__index"] ?? null;
		if($index !== null){
			$this->cooldowns[$index] = $this->currentTick + (int) (BehaviorEntity::toFloat($this->type["cooldown"] ?? null, 0.0) * 20);
		}
		$this->threat = null;
		$this->fleeTarget = null;
		$this->type = [];
		$this->entity->getNavigator()->stop();
	}

	public function tick(int $tickDiff) : void{
		$this->currentTick += $tickDiff;
		$threat = $this->threat;
		$target = $this->fleeTarget;
		if($threat === null || $target === null){
			return;
		}
		$near = $threat->getPosition()->distanceSquared($this->entity->getPosition()) < 49;
		$speed = BehaviorEntity::toFloat($this->type[$near ? "sprint_speed_multiplier" : "walk_speed_multiplier"] ?? null, 1.0);
		$this->entity->getNavigator()->moveTo($target, $speed, 1.0);
	}
}
