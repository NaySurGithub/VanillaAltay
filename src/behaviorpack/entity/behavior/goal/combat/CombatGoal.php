<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\combat;

use behaviorpack\entity\behavior\combat\FollowRangeSystem;
use behaviorpack\entity\behavior\Goal;
use behaviorpack\entity\BehaviorEntity;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\math\VoxelRayTrace;
use pocketmine\player\Player;
use function array_is_list;
use function atan2;
use function abs;
use function is_array;
use function is_bool;
use function max;
use function sqrt;
use const M_PI;

/**
 * Shared helpers of the combat goals: time, sight, reach and target checks.
 */
abstract class CombatGoal extends Goal{

	protected function now() : int{
		return $this->entity->getWorld()->getServer()->getTick();
	}

	protected function float(string $key, float $default) : float{
		return BehaviorEntity::toFloat($this->config[$key] ?? null, $default);
	}

	protected function bool(string $key, bool $default) : bool{
		$value = $this->config[$key] ?? null;
		return is_bool($value) ? $value : $default;
	}

	protected function seconds(string $key, float $default) : int{
		return (int) max(0, BehaviorEntity::rangeValue($this->config[$key] ?? null, $default) * 20);
	}

	protected function followRange() : float{
		return FollowRangeSystem::of($this->entity);
	}

	protected function isSitting() : bool{
		return $this->entity->getData("sitting") === true;
	}

	/**
	 * Returns whether an entity can be attacked at all: alive, in the same
	 * world, not the entity itself and not a creative or spectator player.
	 */
	public static function isAttackable(BehaviorEntity $self, ?Entity $target) : bool{
		if(!$target instanceof Living || $target === $self || $target->isClosed() || !$target->isAlive()){
			return false;
		}
		if($target->getWorld() !== $self->getWorld()){
			return false;
		}
		if($target instanceof Player && ($target->isCreative() || $target->isSpectator())){
			return false;
		}
		return true;
	}

	/**
	 * Returns whether there are no solid blocks between the eyes of the
	 * entity and the eyes of the target.
	 */
	public static function canSee(Entity $self, Entity $target) : bool{
		$start = $self->getEyePos();
		$end = $target->getEyePos();
		if($start->distanceSquared($end) > 128 * 128){
			return false;
		}
		$world = $self->getWorld();
		foreach(VoxelRayTrace::betweenPoints($start, $end) as $position){
			$block = $world->getBlockAt((int) $position->x, (int) $position->y, (int) $position->z);
			if($block->isSolid() && !$block->isTransparent() && $block->calculateIntercept($start, $end) !== null){
				return false;
			}
		}
		return true;
	}

	/**
	 * Normalizes an "entity_types" value (one object or a list) to a list.
	 *
	 * @return list<array<mixed>>
	 */
	protected function entityTypes(mixed $value = null) : array{
		$value ??= $this->config["entity_types"] ?? null;
		if(!is_array($value)){
			return [];
		}
		if(!array_is_list($value)){
			return [$value];
		}
		$types = [];
		foreach($value as $type){
			if(is_array($type)){
				$types[] = $type;
			}
		}
		return $types;
	}

	/**
	 * Returns the first entity type whose filters accept the candidate, or
	 * null. An empty list accepts anything with an empty type.
	 *
	 * @param list<array<mixed>> $types
	 * @return array<mixed>|null
	 */
	protected function matchEntityType(array $types, Entity $candidate) : ?array{
		if($types === []){
			return [];
		}
		foreach($types as $type){
			$filters = $type["filters"] ?? null;
			if(!is_array($filters) || $this->entity->testFilter($filters, ["other" => $candidate, "target" => $candidate])){
				return $type;
			}
		}
		return null;
	}

	/**
	 * Returns the squared melee reach of the entity against a target.
	 */
	protected function meleeReachSquared(Entity $target, float $reachMultiplier) : float{
		$reach = $this->entity->getSize()->getWidth() * $reachMultiplier;
		return $reach * $reach + $target->getSize()->getWidth();
	}

	protected function isWithinFov(Entity $target, float $fov) : bool{
		if($fov >= 360.0){
			return true;
		}
		$location = $this->entity->getLocation();
		$targetPos = $target->getPosition();
		$yaw = atan2($targetPos->z - $location->z, $targetPos->x - $location->x) / M_PI * 180 - 90;
		$diff = self::wrapAngle($yaw - $location->yaw);
		return abs($diff) <= $fov / 2;
	}

	protected function horizontalDistance(Vector3 $target) : float{
		return sqrt($this->entity->horizontalDistanceSquared($target));
	}

	protected function lookAtEntity(Entity $target) : void{
		$this->entity->lookAt($target->getEyePos());
	}

	/**
	 * Wraps an angle in degrees to [-180, 180).
	 */
	public static function wrapAngle(float $angle) : float{
		while($angle >= 180.0){
			$angle -= 360.0;
		}
		while($angle < -180.0){
			$angle += 360.0;
		}
		return $angle;
	}
}
