<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\taming;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use behaviorpack\entity\behavior\PlayerInputTracker;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use function cos;
use function deg2rad;
use function is_array;
use function sin;
use function sqrt;

/**
 * Moves the entity from the input of the player in its controlling seat.
 */
abstract class ControlledMovementSystem extends EntitySystem{

	private bool $controlled = false;

	abstract protected function getControlFlag() : int;

	/**
	 * Returns the horizontal motion and the yaw of the entity for the input
	 * of the rider, or null when the entity does not move.
	 *
	 * @return array{Vector3, float}|null
	 */
	abstract protected function steer(Player $rider, Vector2 $input, float $cameraYaw, float $speed) : ?array;

	public function onRemove() : void{
		$this->setControlled(false);
	}

	protected function getRider() : ?Player{
		$seat = 0;
		$rideable = $this->entity->getComponent("minecraft:rideable");
		if($rideable !== null){
			$seat = (int) BehaviorEntity::toFloat($rideable["controlling_seat"] ?? null, 0.0);
		}
		$rider = $this->entity->getPassengers()[$seat] ?? null;
		return $rider instanceof Player ? $rider : null;
	}

	private function setControlled(bool $controlled) : void{
		if($this->controlled === $controlled){
			return;
		}
		$this->controlled = $controlled;
		$this->entity->setFlag($this->getControlFlag(), $controlled);
	}

	protected function getJumpPower() : float{
		$static = $this->entity->getComponent("minecraft:jump.static");
		if($static !== null){
			return BehaviorEntity::toFloat($static["jump_power"] ?? null, 0.42);
		}
		$strength = $this->entity->getComponent("minecraft:horse.jump_strength");
		if($strength !== null){
			$value = $strength["value"] ?? null;
			return is_array($value) ? BehaviorEntity::toFloat($value["range_max"] ?? $value["max"] ?? null, 0.7) : BehaviorEntity::toFloat($value, 0.7);
		}
		return 0.0;
	}

	public function tick(int $tickDiff) : void{
		$rider = $this->getRider();
		if($rider === null || SittableSystem::isSitting($this->entity)){
			$this->setControlled(false);
			return;
		}
		$this->setControlled(true);
		$this->entity->getNavigator()->stop();
		$input = PlayerInputTracker::getMoveVector($rider);
		$cameraYaw = PlayerInputTracker::getYaw($rider);
		$speed = $this->entity->getMovementSpeed();
		$motion = $this->entity->getMotion();
		$steer = $this->steer($rider, $input, $cameraYaw, $speed);
		$y = $motion->y;
		if(PlayerInputTracker::isJumping($rider) && $this->entity->isOnGround()){
			$power = $this->getJumpPower();
			if($power > 0){
				$y = $power;
			}
		}
		if($steer === null){
			$this->entity->setMotion(new Vector3($motion->x * 0.5, $y, $motion->z * 0.5));
			$this->entity->setRotation($cameraYaw, $this->entity->getLocation()->pitch);
			return;
		}
		[$horizontal, $yaw] = $steer;
		$this->entity->setRotation($yaw, $this->entity->getLocation()->pitch);
		$this->entity->setMotion(new Vector3($horizontal->x, $y, $horizontal->z));
		if($this->entity->isCollidedHorizontally && $this->entity->isOnGround() && $this->getJumpPower() <= 0){
			$this->entity->jump();
		}
	}

	/**
	 * Converts a movement relative to a yaw into a world direction: strafe is
	 * positive to the left, forward along the yaw.
	 */
	protected static function relative(float $yaw, float $strafe, float $forward) : Vector3{
		$radians = deg2rad($yaw);
		$sin = sin($radians);
		$cos = cos($radians);
		return new Vector3(-$sin * $forward + $cos * $strafe, 0, $cos * $forward + $sin * $strafe);
	}

	protected static function length(Vector2 $input) : float{
		return sqrt($input->x * $input->x + $input->y * $input->y);
	}
}
