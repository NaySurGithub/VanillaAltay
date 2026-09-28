<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\taming;

use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;

/**
 * minecraft:is_saddled: the entity wears a saddle.
 */
final class IsSaddledSystem extends FlagSystem{

	protected function getFlag() : int{
		return EntityMetadataFlags::SADDLED;
	}
}
