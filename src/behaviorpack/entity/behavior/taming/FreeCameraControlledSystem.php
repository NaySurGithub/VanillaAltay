<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\taming;

use behaviorpack\entity\BehaviorEntity;
use pocketmine\math\Vector2;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use function atan2;
use function max;
use function min;
use function sqrt;
use const M_PI;

/**
 * minecraft:free_camera_controlled: the rider input moves the entity
 * relative to the camera, and the entity turns toward where it moves.
 * Moving backward and sideways is slowed by the modifiers.
 */
final class FreeCameraControlledSystem extends ControlledMovementSystem{

	protected function getControlFlag() : int{
		return EntityMetadataFlags::WASD_FREE_CAMERA_CONTROLLED;
	}

	protected function steer(Player $rider, Vector2 $input, float $cameraYaw, float $speed) : ?array{
		if(self::length($input) < 0.01){
			return null;
		}
		$forward = max(-1.0, min(1.0, $input->y));
		if($forward < 0){
			$forward *= BehaviorEntity::toFloat($this->config["backwards_movement_modifier"] ?? null, 0.5);
		}
		$strafe = max(-1.0, min(1.0, $input->x)) * BehaviorEntity::toFloat($this->config["strafe_speed_modifier"] ?? null, 0.4);
		$direction = self::relative($cameraYaw, $strafe, $forward);
		$length = sqrt($direction->x ** 2 + $direction->z ** 2);
		if($length < 0.0001){
			return null;
		}
		$scale = min(1.0, $length) * $speed / $length;
		$yaw = atan2($direction->z, $direction->x) / M_PI * 180 - 90;
		return [$direction->multiply($scale), $yaw < 0 ? $yaw + 360.0 : $yaw];
	}
}
