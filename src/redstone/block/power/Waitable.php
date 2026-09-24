<?php

declare(strict_types=1);

namespace redstone\block\power;

interface Waitable{

	/**
	 * Used as a callback method when {@see RedstoneWorld::scheduleWaitableUpdate()}
	 * gets called.
	 */
	public function onRedstoneTickReceive() : void;
}