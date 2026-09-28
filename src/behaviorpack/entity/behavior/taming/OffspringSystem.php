<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\taming;

use behaviorpack\entity\behavior\EntitySystem;
use function is_array;
use function is_string;

/**
 * minecraft:offspring: the baby type bred with each mate type, used by
 * breeding when the breedable component declares no breeds_with list.
 */
final class OffspringSystem extends EntitySystem{

	/**
	 * Returns the baby identifier for a mate, or null when the mate is not
	 * listed.
	 */
	public function babyFor(string $mateIdentifier) : ?string{
		$pairs = $this->config["offspring_pairs"] ?? null;
		if(!is_array($pairs)){
			return null;
		}
		$baby = $pairs[$mateIdentifier] ?? null;
		return is_string($baby) ? $baby : null;
	}

	public function hasPairs() : bool{
		return is_array($this->config["offspring_pairs"] ?? null) && $this->config["offspring_pairs"] !== [];
	}
}
