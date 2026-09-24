<?php

declare(strict_types=1);

namespace behaviorpack\loot;

use pocketmine\item\Item;

/**
 * A pool entry: an item, a nested loot table or nothing.
 */
final class LootEntry{

	public const TYPE_ITEM = "item";
	public const TYPE_LOOT_TABLE = "loot_table";
	public const TYPE_EMPTY = "empty";

	/**
	 * @param list<LootCondition> $conditions
	 * @param list<LootFunction>  $functions
	 */
	public function __construct(
		private string $type,
		private string $name,
		private int $weight,
		private int $quality,
		private array $conditions,
		private array $functions
	){
	}

	public function getType() : string{
		return $this->type;
	}

	public function getName() : string{
		return $this->name;
	}

	public function getWeight() : int{
		return $this->weight;
	}

	public function getQuality() : int{
		return $this->quality;
	}

	public function canBeChosen(LootContext $context) : bool{
		foreach($this->conditions as $condition){
			if(!$condition->test($context)){
				return false;
			}
		}
		return true;
	}

	/**
	 * @return list<Item>
	 */
	public function generate(LootContext $context, int $depth) : array{
		if($this->type === self::TYPE_ITEM){
			$item = LootItems::resolve($this->name);
			return $item === null ? [] : $this->applyFunctions([$item], $context);
		}
		if($this->type === self::TYPE_LOOT_TABLE){
			$table = LootTableRegistry::get($this->name);
			if($table === null || $depth >= LootTable::MAX_DEPTH){
				return [];
			}
			return $this->applyFunctions($table->rollAtDepth($context, $depth + 1), $context);
		}
		return [];
	}

	/**
	 * @param list<Item> $items
	 *
	 * @return list<Item>
	 */
	private function applyFunctions(array $items, LootContext $context) : array{
		$result = [];
		foreach($items as $item){
			foreach($this->functions as $function){
				$item = $function->apply($item, $context);
			}
			if(!$item->isNull() && $item->getCount() > 0){
				$result[] = $item;
			}
		}
		return $result;
	}
}
