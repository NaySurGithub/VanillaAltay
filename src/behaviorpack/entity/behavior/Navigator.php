<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior;

use behaviorpack\entity\BehaviorEntity;
use pocketmine\math\Vector3;
use function atan2;
use function sqrt;
use const M_PI;

/**
 * Moves an entity toward a destination. This default navigator walks in a
 * straight line and jumps over obstacles; the "minecraft:navigation.*"
 * components replace it with their own.
 */
class Navigator{

	protected ?Vector3 $destination = null;
	protected float $speedMultiplier = 1.0;
	protected float $reachDistance = 0.5;

	public function __construct(
		protected BehaviorEntity $entity
	){}

	/**
	 * Starts moving toward a position. Returns false when it cannot be reached.
	 */
	public function moveTo(Vector3 $destination, float $speedMultiplier = 1.0, float $reachDistance = 0.5) : bool{
		$this->destination = $destination;
		$this->speedMultiplier = $speedMultiplier;
		$this->reachDistance = $reachDistance;
		return true;
	}

	public function getDestination() : ?Vector3{
		return $this->destination;
	}

	public function stop() : void{
		$this->destination = null;
	}

	public function isDone() : bool{
		return $this->destination === null;
	}

	public function tick() : void{
		if($this->destination === null){
			return;
		}
		$location = $this->entity->getLocation();
		$dx = $this->destination->x - $location->x;
		$dz = $this->destination->z - $location->z;
		$distance = sqrt($dx * $dx + $dz * $dz);
		if($distance <= $this->reachDistance){
			$this->destination = null;
			return;
		}
		$speed = $this->entity->getMovementSpeed() * $this->speedMultiplier;
		$motion = $this->entity->getMotion();
		$this->entity->setMotion(new Vector3($dx / $distance * $speed, $motion->y, $dz / $distance * $speed));
		$yaw = atan2($dz, $dx) / M_PI * 180 - 90;
		$this->entity->setRotation($yaw < 0 ? $yaw + 360.0 : $yaw, $location->pitch);
		if($this->entity->isCollidedHorizontally && $this->entity->isOnGround()){
			$this->entity->jump();
		}
	}
}
