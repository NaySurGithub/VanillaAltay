<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\taming;

use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;

/**
 * minecraft:is_baby: the entity is a baby, its scale comes from the pack.
 */
final class IsBabySystem extends FlagSystem{

	protected function getFlag() : int{
		return EntityMetadataFlags::BABY;
	}
}
