<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\movement;

use pocketmine\math\Vector3;
use function count;
use function is_array;
use function is_int;
use function is_float;

/**
 * Returns to the home position, running on_home once there and on_failed
 * when the way is lost.
 */
final class GoHomeGoal extends MovementGoal{

	private int $stuckTicks = 0;
	private ?Vector3 $lastPosition = null;

	private function home() : ?Vector3{
		$home = $this->entity->getData("home");
		if(!is_array($home) || count($home) < 3){
			return null;
		}
		foreach([0, 1, 2] as $i){
			if(!is_int($home[$i] ?? null) && !is_float($home[$i] ?? null)){
				return null;
			}
		}
		return new Vector3((float) $home[0] + 0.5, (float) $home[1], (float) $home[2] + 0.5);
	}

	private function atHome(Vector3 $home) : bool{
		$radius = $this->num("goal_radius", 0.5);
		return $this->entity->getPosition()->distanceSquared($home) <= $radius * $radius;
	}

	public function canUse() : bool{
		if($this->isSitting() || !$this->chance($this->int("interval", 120))){
			return false;
		}
		$home = $this->home();
		return $home !== null && !$this->atHome($home);
	}

	public function canContinue() : bool{
		$home = $this->home();
		return $home !== null && !$this->isSitting() && !$this->atHome($home) && $this->stuckTicks < 100;
	}

	public function start() : void{
		$home = $this->home();
		$this->stuckTicks = 0;
		$this->lastPosition = null;
		if($home === null || !$this->entity->getNavigator()->moveTo($home, $this->speed(), $this->num("goal_radius", 0.5))){
			$this->runTriggers("on_failed");
		}
	}

	public function stop() : void{
		$home = $this->home();
		$this->entity->getNavigator()->stop();
		if($home !== null && $this->atHome($home)){
			$this->runTriggers("on_home");
		}elseif($this->stuckTicks >= 100){
			$this->runTriggers("on_failed");
		}
		$this->stuckTicks = 0;
	}

	private function runTriggers(string $key) : void{
		$triggers = $this->config[$key] ?? null;
		if($triggers === null){
			return;
		}
		if(is_array($triggers) && isset($triggers[0])){
			foreach($triggers as $trigger){
				$this->entity->runTrigger($trigger);
			}
			return;
		}
		$this->entity->runTrigger($triggers);
	}

	public function tick(int $tickDiff) : void{
		$home = $this->home();
		if($home === null){
			return;
		}
		$position = $this->entity->getPosition()->asVector3();
		if($this->lastPosition !== null && $this->lastPosition->distanceSquared($position) < 0.0025){
			$this->stuckTicks += $tickDiff;
		}else{
			$this->stuckTicks = 0;
		}
		$this->lastPosition = $position;
		if($this->entity->getNavigator()->isDone()){
			$this->entity->getNavigator()->moveTo($home, $this->speed(), $this->num("goal_radius", 0.5));
		}
	}
}
