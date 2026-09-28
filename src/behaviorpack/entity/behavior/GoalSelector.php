<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior;

use function uasort;

/**
 * Runs the goals of an entity: stops those that can no longer continue,
 * starts the usable ones whose controls are free or held by goals of lower
 * priority, and ticks the running ones.
 */
final class GoalSelector{

	/** @var array<string, Goal> */
	private array $goals = [];

	/** @var array<string, true> */
	private array $running = [];

	/**
	 * @param array<string, Goal> $goals
	 */
	public function setGoals(array $goals) : void{
		foreach($this->running as $name => $unused){
			if(!isset($goals[$name]) || $goals[$name] !== $this->goals[$name]){
				$this->goals[$name]->stop();
				unset($this->running[$name]);
			}
		}
		uasort($goals, fn(Goal $a, Goal $b) : int => $a->getPriority() <=> $b->getPriority());
		$this->goals = $goals;
	}

	/**
	 * @return array<string, Goal>
	 */
	public function getGoals() : array{
		return $this->goals;
	}

	public function getGoal(string $component) : ?Goal{
		return $this->goals[$component] ?? null;
	}

	public function isRunning(string $component) : bool{
		return isset($this->running[$component]);
	}

	public function stopAll() : void{
		foreach($this->running as $name => $unused){
			$this->goals[$name]->stop();
		}
		$this->running = [];
	}

	public function tick(int $tickDiff) : void{
		foreach($this->running as $name => $unused){
			$goal = $this->goals[$name];
			if(!$goal->canContinue()){
				$goal->stop();
				unset($this->running[$name]);
			}
		}
		foreach($this->goals as $name => $goal){
			if(isset($this->running[$name])){
				continue;
			}
			$blocked = false;
			$interrupt = [];
			foreach($this->running as $runningName => $unused){
				$other = $this->goals[$runningName];
				if(($other->getControls() & $goal->getControls()) === 0){
					continue;
				}
				if($other->getPriority() <= $goal->getPriority() || !$other->isInterruptable()){
					$blocked = true;
					break;
				}
				$interrupt[] = $runningName;
			}
			if($blocked || !$goal->canUse()){
				continue;
			}
			foreach($interrupt as $runningName){
				$this->goals[$runningName]->stop();
				unset($this->running[$runningName]);
			}
			$this->running[$name] = true;
			$goal->start();
		}
		foreach($this->running as $name => $unused){
			if(isset($this->running[$name])){
				$this->goals[$name]->tick($tickDiff);
			}
		}
	}
}
