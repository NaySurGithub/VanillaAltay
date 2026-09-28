<?php

declare(strict_types=1);

namespace behaviorpack\entity\animation;

/**
 * The entity an AnimationRunner drives: its definition, its Molang context,
 * its events, its variables and how its commands run.
 */
interface AnimationHost{

	/**
	 * Returns the "minecraft:entity" definition holding the description.
	 *
	 * @return array<mixed>
	 */
	public function getAnimationDefinition() : array;

	public function evaluateMolang(mixed $value) : float;

	public function triggerAnimationEvent(string $event) : void;

	/**
	 * @return array<string, float|int|bool>
	 */
	public function getMolangVariables() : array;

	public function setMolangVariable(string $name, float $value) : void;

	public function isAnimationInitialized() : bool;

	public function markAnimationInitialized() : void;

	public function runAnimationCommand(string $command) : void;

	public function isAnimationHostClosed() : bool;
}
