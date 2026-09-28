<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\taming;

use pocketmine\math\Vector2;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use function max;
use function min;

/**
 * minecraft:input_ground_controlled: the entity faces where its rider looks
 * and walks forward, backward and sideways from the rider input.
 */
final class InputGroundControlledSystem extends ControlledMovementSystem{

	private const BACKWARD_FACTOR = 0.25;
	private const STRAFE_FACTOR = 0.5;

	protected function getControlFlag() : int{
		return EntityMetadataFlags::WASD_CONTROLLED;
	}

	protected function steer(Player $rider, Vector2 $input, float $cameraYaw, float $speed) : ?array{
		if(self::length($input) < 0.01){
			return null;
		}
		$forward = max(-1.0, min(1.0, $input->y));
		if($forward < 0){
			$forward *= self::BACKWARD_FACTOR;
		}
		$strafe = max(-1.0, min(1.0, $input->x)) * self::STRAFE_FACTOR;
		return [self::relative($cameraYaw, $strafe * $speed, $forward * $speed), $cameraYaw];
	}
}
