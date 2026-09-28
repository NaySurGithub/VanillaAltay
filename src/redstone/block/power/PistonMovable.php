<?php

declare(strict_types=1);

namespace redstone\block\power;

/**
 * A block that tells pistons how it reacts to being moved.
 */
interface PistonMovable extends Movable{

	/**
	 * Returns whether the block breaks and drops instead of moving.
	 */
	public function breaksWhenMoved() : bool;

	/**
	 * Returns whether a retracting sticky piston can pull the block.
	 */
	public function canBePulled() : bool;

	/**
	 * Returns whether the block drags the adjacent blocks of the same type
	 * along when it moves.
	 */
	public function sticksToSameType() : bool;
}
