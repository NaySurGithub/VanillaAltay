<?php

declare(strict_types=1);

namespace behaviorpack\custom\item;

/**
 * Combat data of a behavior pack item: swing sounds and piercing attack.
 */
interface CombatItem{

	public const SOUND_HIT = "attack_hit";
	public const SOUND_MISS = "attack_miss";
	public const SOUND_CRITICAL_HIT = "attack_critical_hit";

	public function getSwingSound(string $type) : ?string;

	public function getPiercingWeapon() : ?PiercingWeapon;
}
