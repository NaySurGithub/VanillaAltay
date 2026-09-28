<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\taming;

use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;

/**
 * minecraft:is_chested: the entity carries a chest.
 */
final class IsChestedSystem extends FlagSystem{

	protected function getFlag() : int{
		return EntityMetadataFlags::CHESTED;
	}
}
