<?php

declare(strict_types=1);

namespace behaviorpack\loot;

use pocketmine\crafting\FurnaceType;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\data\bedrock\EnchantmentIds;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\AvailableEnchantmentRegistry;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\Server;
use function array_filter;
use function array_rand;
use function array_values;
use function ceil;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function max;
use function min;
use function mt_rand;
use function round;

/**
 * A loot table item function, applied when its own conditions pass.
 * Unknown functions leave the item unchanged.
 */
final class LootFunction{

	private const TREASURE_IDS = [
		EnchantmentIds::FROST_WALKER,
		EnchantmentIds::MENDING,
		EnchantmentIds::BINDING,
		EnchantmentIds::VANISHING,
		EnchantmentIds::SOUL_SPEED,
		EnchantmentIds::SWIFT_SNEAK
	];

	/**
	 * @param array<mixed>        $data
	 * @param list<LootCondition> $conditions
	 */
	public function __construct(
		private string $type,
		private array $data,
		private array $conditions
	){
	}

	public function getType() : string{
		return $this->type;
	}

	public function apply(Item $item, LootContext $context) : Item{
		foreach($this->conditions as $condition){
			if(!$condition->test($context)){
				return $item;
			}
		}

		switch($this->type){
			case "set_count":
				return $item->setCount(max(0, LootRange::rollInt($this->data["count"] ?? null, 1)));
			case "set_damage":
				return $this->setDamage($item);
			case "set_data":
				return $this->setData($item);
			case "set_name":
				$name = $this->data["name"] ?? null;
				return is_string($name) ? $item->setCustomName($name) : $item;
			case "set_lore":
				return $this->setLore($item);
			case "enchant_randomly":
				return $this->enchantRandomly($item, ($this->data["treasure"] ?? false) === true, 1, 0);
			case "enchant_with_levels":
				$levels = max(1, LootRange::rollInt($this->data["levels"] ?? null, 30));
				$amount = 1 + ($levels >= 15 ? 1 : 0) + ($levels >= 30 ? 1 : 0);
				return $this->enchantRandomly($item, ($this->data["treasure"] ?? false) === true, $amount, $levels);
			case "specific_enchants":
				return $this->specificEnchants($item);
			case "looting_enchant":
				return $this->lootingEnchant($item, $context->getLootingLevel());
			case "furnace_smelt":
				return $this->smelt($item);
			default:
				return $item;
		}
	}

	private function setDamage(Item $item) : Item{
		if(!$item instanceof Durable){
			return $item;
		}
		$fraction = min(1.0, max(0.0, LootRange::rollFloat($this->data["damage"] ?? null, 1.0)));
		$maxDurability = $item->getMaxDurability();
		return $item->setDamage(min($maxDurability, max(0, (int) round($maxDurability * (1 - $fraction)))));
	}

	private function setData(Item $item) : Item{
		$identifier = LootItems::identifierOf($item);
		if($identifier === null){
			return $item;
		}
		$result = LootItems::resolve($identifier, LootRange::rollInt($this->data["data"] ?? null, 0));
		return $result === null ? $item : $result->setCount($item->getCount());
	}

	private function setLore(Item $item) : Item{
		$lore = $this->data["lore"] ?? null;
		if(!is_array($lore)){
			return $item;
		}
		$lines = [];
		foreach($lore as $line){
			if(is_string($line)){
				$lines[] = $line;
			}
		}
		return $item->setLore($lines);
	}

	private function enchantRandomly(Item $item, bool $treasure, int $amount, int $levels) : Item{
		$book = $item->getTypeId() === VanillaItems::BOOK()->getTypeId();
		$registry = AvailableEnchantmentRegistry::getInstance();
		$candidates = array_values($book ? $registry->getAll() : $registry->getAllEnchantmentsForItem($item));
		if(!$treasure){
			$candidates = array_values(array_filter($candidates, fn(Enchantment $enchantment) : bool => !self::isTreasure($enchantment)));
		}
		if(count($candidates) === 0){
			return $item;
		}
		if($book){
			$item = VanillaItems::ENCHANTED_BOOK()->setCount($item->getCount());
		}

		for($i = 0; $i < $amount && count($candidates) > 0; ++$i){
			$key = array_rand($candidates);
			$enchantment = $candidates[$key];
			unset($candidates[$key]);
			$maxLevel = $enchantment->getMaxLevel();
			$level = $levels > 0 ? (int) ceil($maxLevel * min(1.0, $levels / 30)) : mt_rand(1, $maxLevel);
			$item->addEnchantment(new EnchantmentInstance($enchantment, max(1, min($maxLevel, $level))));
			$candidates = array_values(array_filter($candidates, fn(Enchantment $other) : bool => $other->isCompatibleWith($enchantment)));
		}
		return $item;
	}

	private static function isTreasure(Enchantment $enchantment) : bool{
		$map = EnchantmentIdMap::getInstance();
		foreach(self::TREASURE_IDS as $id){
			if($map->fromId($id) === $enchantment){
				return true;
			}
		}
		return false;
	}

	private function specificEnchants(Item $item) : Item{
		$enchants = $this->data["enchants"] ?? null;
		if(!is_array($enchants)){
			return $item;
		}
		foreach($enchants as $entry){
			$name = is_string($entry) ? $entry : (is_array($entry) ? ($entry["id"] ?? null) : null);
			if(!is_string($name)){
				continue;
			}
			$enchantment = LootItems::enchantment($name);
			if($enchantment === null){
				continue;
			}
			$level = is_array($entry) ? LootRange::rollInt($entry["level"] ?? null, 1) : 1;
			$item->addEnchantment(new EnchantmentInstance($enchantment, max(1, $level)));
		}
		return $item;
	}

	private function lootingEnchant(Item $item, int $lootingLevel) : Item{
		if($lootingLevel <= 0){
			return $item;
		}
		$count = $item->getCount();
		for($i = 0; $i < $lootingLevel; ++$i){
			$count += max(0, LootRange::rollInt($this->data["count"] ?? null, 0));
		}
		$limit = LootRange::rollInt($this->data["limit"] ?? null, 0);
		if($limit > 0){
			$count = min($limit, $count);
		}
		return $item->setCount($count);
	}

	private function smelt(Item $item) : Item{
		$recipe = Server::getInstance()->getCraftingManager()->getFurnaceRecipeManager(FurnaceType::FURNACE)->match($item);
		return $recipe === null ? $item : $recipe->getResult()->setCount($item->getCount());
	}

	/**
	 * Whether a function type is supported by apply().
	 */
	public static function isKnown(string $type) : bool{
		return in_array($type, ["set_count", "set_damage", "set_data", "set_name", "set_lore", "enchant_randomly", "enchant_with_levels", "specific_enchants", "looting_enchant", "furnace_smelt"], true);
	}
}
