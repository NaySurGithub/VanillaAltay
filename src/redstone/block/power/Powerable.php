<?php

declare(strict_types=1);

namespace redstone\block\power;

/**
 * Blocks that change their state when powered.
 * RULE: Powerable blocks should NEVER recalculate power without receiving a
 * Powerable::power() call!
 */
interface Powerable extends Transmittable, Waitable{

	/**
	 * Delay in redstone ticks after which this block
	 * may activate or 0 to try activating instantly.
	 */
	public function getActivationDelay() : int;

	/**
	 * Delay in redstone ticks after which this block
	 * may deactivate or 0 to try deactivating instantly.
	 */
	public function getDeactivationDelay() : int;

	/**
	 * Returns whether this block ONLY accepts strong power.
	 */
	public function requiresStrongPower() : bool;

	/**
	 * Returns whether this block accepts power
	 * from a side.
	 *
	 * @param int $side
	 * @return bool
	 */
	public function acceptsPowerFromSide(int $side) : bool;

	/**
	 * Returns whether this block is powered/activated.
	 *
	 * @return bool
	 */
	public function isPowered() : bool;

	/**
	 * Calculates new power state and does the visual changes if
	 * there needs to be any.
	 */
	public function recalculatePowerState() : void;
}