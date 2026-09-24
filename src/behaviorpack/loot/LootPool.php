<?php

declare(strict_types=1);

namespace behaviorpack\loot;

use pocketmine\item\Item;
use function count;
use function max;
use function min;
use function mt_rand;

/**
 * A loot pool: rolls weighted entries a number of times, or picks one entry
 * by tier when "tiers" is set.
 */
final class LootPool{

	/**
	 * @param list<LootEntry>     $entries
	 * @param list<LootCondition> $conditions
	 * @param list<LootFunction>  $functions
	 * @param array<mixed>|null   $tiers
	 */
	public function __construct(
		private mixed $rolls,
		private float $bonusRolls,
		private ?array $tiers,
		private array $entries,
		private array $conditions,
		private array $functions
	){
	}

	/**
	 * @return list<LootEntry>
	 */
	public function getEntries() : array{
		return $this->entries;
	}

	/**
	 * @return list<Item>
	 */
	public function roll(LootContext $context, int $depth) : array{
		foreach($this->conditions as $condition){
			if(!$condition->test($context)){
				return [];
			}
		}
		if(count($this->entries) === 0){
			return [];
		}

		$items = [];
		if($this->tiers !== null){
			$entry = $this->pickTier();
			if($entry->canBeChosen($context)){
				$items = $entry->generate($context, $depth);
			}
		}else{
			$rolls = max(0, LootRange::rollInt($this->rolls, 1));
			for($i = 0; $i < $rolls; ++$i){
				$entry = $this->pickWeighted($context);
				if($entry !== null){
					foreach($entry->generate($context, $depth) as $item){
						$items[] = $item;
					}
				}
			}
		}

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

	public function getBonusRolls() : float{
		return $this->bonusRolls;
	}

	private function pickTier() : LootEntry{
		$initialRange = max(1, LootRange::rollInt($this->tiers["initial_range"] ?? null, 1));
		$bonusRolls = max(0, LootRange::rollInt($this->tiers["bonus_rolls"] ?? null, 0));
		$bonusChance = LootRange::number($this->tiers["bonus_chance"] ?? null, 0.0);
		$tier = mt_rand(1, $initialRange);
		for($i = 0; $i < $bonusRolls; ++$i){
			if(LootRange::chance() < $bonusChance){
				++$tier;
			}
		}
		return $this->entries[min($tier, count($this->entries)) - 1];
	}

	private function pickWeighted(LootContext $context) : ?LootEntry{
		$candidates = [];
		$total = 0;
		foreach($this->entries as $entry){
			if($entry->getWeight() > 0 && $entry->canBeChosen($context)){
				$candidates[] = $entry;
				$total += $entry->getWeight();
			}
		}
		if($total <= 0){
			return null;
		}
		$pick = mt_rand(1, $total);
		foreach($candidates as $entry){
			$pick -= $entry->getWeight();
			if($pick <= 0){
				return $entry;
			}
		}
		return null;
	}
}
