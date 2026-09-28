<?php

declare(strict_types=1);

namespace behaviorpack\custom\block;

use customiesdevs\customies\block\component\BlockComponent;
use pocketmine\nbt\tag\CompoundTag;

/**
 * A block component whose network value is built ahead of time.
 */
final class TagComponent implements BlockComponent{

	public function __construct(
		private string $name,
		private CompoundTag $value
	){
	}

	public function getName() : string{
		return $this->name;
	}

	public function getValue() : CompoundTag{
		return clone $this->value;
	}
}
