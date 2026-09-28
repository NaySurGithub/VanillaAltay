<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\combat;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use behaviorpack\entity\behavior\PlayerInputTracker;
use pocketmine\player\Player;
use function class_exists;
use function cos;
use function deg2rad;
use function is_int;
use function sin;

/**
 * minecraft:dash_action: the entity dashes forward when its rider presses
 * jump. The remaining cooldown ticks are stored as "dash_cooldown".
 */
final class DashActionSystem extends EntitySystem{

	private const KEY = "dash_cooldown";

	private bool $wasJumping = false;

	public function onRemove() : void{
		$this->entity->setData(self::KEY, null);
	}

	public function tick(int $tickDiff) : void{
		$cooldown = $this->entity->getData(self::KEY);
		if(is_int($cooldown)){
			$cooldown -= $tickDiff;
			$this->entity->setData(self::KEY, $cooldown > 0 ? $cooldown : null);
		}
		$rider = null;
		foreach($this->entity->getPassengers() as $passenger){
			if($passenger instanceof Player){
				$rider = $passenger;
				break;
			}
		}
		if($rider === null || !class_exists(PlayerInputTracker::class)){
			$this->wasJumping = false;
			return;
		}
		$jumping = PlayerInputTracker::isJumping($rider);
		$pressed = $jumping && !$this->wasJumping;
		$this->wasJumping = $jumping;
		if(!$pressed || $this->entity->getData(self::KEY) !== null){
			return;
		}
		if($this->entity->isInWater() && ($this->config["can_dash_underwater"] ?? false) !== true){
			return;
		}
		$this->dash($rider);
	}

	private function dash(Player $rider) : void{
		$horizontal = BehaviorEntity::toFloat($this->config["horizontal_momentum"] ?? null, 1.0);
		$vertical = BehaviorEntity::toFloat($this->config["vertical_momentum"] ?? null, 0.1);
		$yaw = deg2rad($rider->getLocation()->getYaw());
		$this->entity->setMotion($this->entity->getMotion()->add(-sin($yaw) * $horizontal, $vertical, cos($yaw) * $horizontal));
		$this->entity->setData(self::KEY, (int) (BehaviorEntity::toFloat($this->config["cooldown_time"] ?? null, 1.0) * 20));
	}
}
