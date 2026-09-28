<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior;

use behaviorpack\entity\BehaviorEntity;
use function is_int;
use function is_numeric;

/**
 * A "minecraft:behavior.*" component. The goal selector runs the goals by
 * priority (lower first); two goals that use the same controls cannot run
 * together, and a goal of higher priority interrupts a lower one.
 */
abstract class Goal{

	public const MOVE = 1;
	public const LOOK = 2;
	public const TARGET = 4;
	public const JUMP = 8;

	/**
	 * @param array<mixed> $config
	 */
	public function __construct(
		protected BehaviorEntity $entity,
		protected string $component,
		protected array $config
	){}

	public function getComponent() : string{
		return $this->component;
	}

	public function getPriority() : int{
		$priority = $this->config["priority"] ?? 0;
		return is_int($priority) ? $priority : (is_numeric($priority) ? (int) $priority : 0);
	}

	/**
	 * Returns the controls the goal needs, a combination of MOVE, LOOK,
	 * TARGET and JUMP.
	 */
	public function getControls() : int{
		return self::MOVE;
	}

	abstract public function canUse() : bool;

	public function canContinue() : bool{
		return $this->canUse();
	}

	public function isInterruptable() : bool{
		return true;
	}

	public function start() : void{
	}

	public function stop() : void{
	}

	public function tick(int $tickDiff) : void{
	}

	/**
	 * @param array<mixed> $config
	 */
	public function updateConfig(array $config) : void{
		$this->config = $config;
	}

	/**
	 * @return array<mixed>
	 */
	public function getConfig() : array{
		return $this->config;
	}
}
