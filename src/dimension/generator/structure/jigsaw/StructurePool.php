<?php

declare(strict_types=1);

namespace dimension\generator\structure\jigsaw;

use dimension\generator\random\RandomSource;

/**
 * Weighted list of templates a jigsaw block can pull a piece from.
 */
final class StructurePool{

	/**
	 * @param list<PoolEntry> $entries
	 */
	public function __construct(
		public readonly string $name,
		public readonly array $entries,
		public readonly ?string $fallback = null
	){}

	public function getRandomEntry(RandomSource $random) : PoolEntry{
		$totalWeight = 0;
		foreach($this->entries as $entry){
			$totalWeight += $entry->weight;
		}
		$target = $random->nextBoundedInt($totalWeight - 1);
		foreach($this->entries as $entry){
			if($target < $entry->weight){
				return $entry;
			}
			$target -= $entry->weight;
		}
		throw new \RuntimeException("Failed to select structure from pool '$this->name'");
	}
}
