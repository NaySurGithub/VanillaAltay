<?php

declare(strict_types=1);

namespace VanillaAltay\entity\ai;

use VanillaAltay\entity\IntelligentMob;

interface Goal
{
	public function canStart(IntelligentMob $mob) : bool;

	public function shouldContinue(IntelligentMob $mob) : bool;

	public function start(IntelligentMob $mob) : void;

	public function tick(IntelligentMob $mob, int $tickDiff) : void;

	public function stop(IntelligentMob $mob) : void;
}
