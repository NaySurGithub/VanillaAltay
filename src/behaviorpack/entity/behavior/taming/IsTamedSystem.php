<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\taming;

use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;

/**
 * minecraft:is_tamed: the entity belongs to its owner.
 */
final class IsTamedSystem extends FlagSystem{

	protected function getFlag() : int{
		return EntityMetadataFlags::TAMED;
	}
}
