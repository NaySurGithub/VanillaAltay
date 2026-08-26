<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure;

/**
 * A structure that is buried rather than built on the ground, and therefore picks its own height.
 */
interface UndergroundStructure extends Structure
{
	public function getMinY() : int;

	public function getMaxY() : int;

	/**
	 * How many heights are tried before giving up on this chunk.
	 */
	public function getAttempts() : int;
}
