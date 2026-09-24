<?php

declare(strict_types=1);

namespace behaviorpack\loot;

use pocketmine\item\Item;

/**
 * A behavior pack loot table.
 */
final class LootTable{

	public const MAX_DEPTH = 8;

	/**
	 * @param list<LootPool> $pools
	 */
	public function __construct(
		private string $path,
		private array $pools
	){
	}

	public function getPath() : string{
		return $this->path;
	}

	/**
	 * @return list<LootPool>
	 */
	public function getPools() : array{
		return $this->pools;
	}

	/**
	 * @return list<Item>
	 */
	public function roll(LootContext $context) : array{
		return $this->rollAtDepth($context, 0);
	}

	/**
	 * Rolls the table as a nested reference of another table.
	 *
	 * @return list<Item>
	 */
	public function rollAtDepth(LootContext $context, int $depth) : array{
		$items = [];
		foreach($this->pools as $pool){
			foreach($pool->roll($context, $depth) as $item){
				$items[] = $item;
			}
		}
		return $items;
	}
}
