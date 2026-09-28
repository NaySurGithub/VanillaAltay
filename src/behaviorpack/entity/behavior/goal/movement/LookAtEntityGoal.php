<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\movement;

use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\player\Player;
use pocketmine\utils\Utils;
use function abs;
use function atan2;
use function fmod;
use function is_array;
use function sqrt;
use const M_PI;

/**
 * Looks at a nearby entity matching the filters for a while.
 */
class LookAtEntityGoal extends MovementGoal{

	private ?int $lookTargetId = null;
	private int $remaining = 0;

	public function getControls() : int{
		return self::LOOK;
	}

	protected function acceptsEntity(Entity $entity) : bool{
		$filters = $this->config["filters"] ?? null;
		if(is_array($filters)){
			return $this->entity->testFilter($filters, ["other" => $entity]);
		}
		return $entity instanceof Living;
	}

	protected function findTarget() : ?Entity{
		$distance = $this->num("look_distance", 8.0);
		$best = null;
		$bestDistance = $distance * $distance;
		$position = $this->entity->getPosition();
		foreach($this->entity->getWorld()->getNearbyEntities($this->entity->getBoundingBox()->expandedCopy($distance, 3.0, $distance), $this->entity) as $entity){
			if(!$entity->isAlive() || ($entity instanceof Player && $entity->isSpectator())){
				continue;
			}
			$d = $entity->getPosition()->distanceSquared($position);
			if($d > $bestDistance || !$this->withinView($entity) || !$this->acceptsEntity($entity)){
				continue;
			}
			$best = $entity;
			$bestDistance = $d;
		}
		return $best;
	}

	private function withinView(Entity $entity) : bool{
		$horizontal = $this->num("angle_of_view_horizontal", 360.0);
		$vertical = $this->num("angle_of_view_vertical", 360.0);
		if($horizontal >= 360.0 && $vertical >= 360.0){
			return true;
		}
		$location = $this->entity->getLocation();
		$target = $entity->getPosition();
		$dx = $target->x - $location->x;
		$dz = $target->z - $location->z;
		$yaw = atan2($dz, $dx) / M_PI * 180 - 90;
		$diff = abs(fmod($yaw - $location->yaw + 540.0, 360.0) - 180.0);
		if($diff > $horizontal / 2){
			return false;
		}
		$dy = $target->y - ($location->y + $this->entity->getEyeHeight());
		$pitch = -atan2($dy, sqrt($dx * $dx + $dz * $dz)) / M_PI * 180;
		return abs($pitch - $location->pitch) <= $vertical / 2;
	}

	public function canUse() : bool{
		if(Utils::getRandomFloat() >= $this->num("probability", 0.02)){
			return false;
		}
		$target = $this->findTarget();
		if($target === null){
			return false;
		}
		$this->lookTargetId = $target->getId();
		return true;
	}

	private function lookTarget() : ?Entity{
		if($this->lookTargetId === null){
			return null;
		}
		$entity = $this->entity->getWorld()->getEntity($this->lookTargetId);
		return $entity === null || !$entity->isAlive() ? null : $entity;
	}

	public function canContinue() : bool{
		$target = $this->lookTarget();
		if($target === null || $this->remaining <= 0){
			return false;
		}
		$distance = $this->num("look_distance", 8.0);
		return $target->getPosition()->distanceSquared($this->entity->getPosition()) <= $distance * $distance;
	}

	public function start() : void{
		$this->remaining = isset($this->config["look_time"])
			? (int) ($this->range("look_time", 2.0) * 20)
			: 40 + (int) (Utils::getRandomFloat() * 40);
	}

	public function stop() : void{
		$this->lookTargetId = null;
		$this->remaining = 0;
	}

	public function tick(int $tickDiff) : void{
		$this->remaining -= $tickDiff;
		$target = $this->lookTarget();
		if($target !== null){
			$this->entity->lookAt($target->getEyePos());
		}
	}
}
