<?php

declare(strict_types=1);

namespace behaviorpack\spawn;

/**
 * A parsed "minecraft:spawn_rules" definition.
 */
final class SpawnRule{

	/**
	 * @param list<SpawnCondition> $conditions
	 */
	public function __construct(
		public readonly string $identifier,
		public readonly string $category,
		public readonly array $conditions
	){
	}
}
