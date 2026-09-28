<?php

declare(strict_types=1);

namespace behaviorpack\custom\catalog;

/**
 * Placement of one item in the creative inventory, as declared by an
 * item_catalog file.
 */
final class CatalogEntry{

	public function __construct(
		public readonly string $category,
		public readonly ?string $group,
		public readonly ?string $icon,
		public readonly int $order
	){
	}

	/**
	 * Returns a key identifying the creative group of this entry within its
	 * category.
	 */
	public function groupKey() : string{
		return $this->category . "\0" . ($this->group ?? "");
	}
}
