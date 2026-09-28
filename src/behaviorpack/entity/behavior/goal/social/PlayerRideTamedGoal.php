<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\social;

use behaviorpack\entity\behavior\Goal;
use pocketmine\player\Player;

/**
 * "minecraft:behavior.player_ride_tamed": while a player rides the tamed
 * entity, holds the movement so the rider input drives it.
 */
class PlayerRideTamedGoal extends Goal{

	public function getControls() : int{
		return self::MOVE | self::JUMP;
	}

	public function canUse() : bool{
		if(!$this->entity->hasComponent("minecraft:is_tamed")){
			return false;
		}
		$passengers = $this->entity->getPassengers();
		foreach($passengers as $passenger){
			return $passenger instanceof Player;
		}
		return false;
	}

	public function start() : void{
		$this->entity->getNavigator()->stop();
	}

	public function tick(int $tickDiff) : void{
		$navigator = $this->entity->getNavigator();
		if(!$navigator->isDone()){
			$navigator->stop();
		}
	}
}
