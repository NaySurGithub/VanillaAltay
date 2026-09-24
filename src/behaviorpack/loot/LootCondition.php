<?php

declare(strict_types=1);

namespace behaviorpack\loot;

use pocketmine\item\Item;
use function is_array;
use function is_bool;
use function is_string;

/**
 * A loot table condition. Unknown conditions always pass.
 */
final class LootCondition{

	/**
	 * @param array<mixed> $data
	 */
	public function __construct(
		private string $type,
		private array $data
	){
	}

	public function getType() : string{
		return $this->type;
	}

	public function test(LootContext $context) : bool{
		switch($this->type){
			case "random_chance":
				return LootRange::chance() < LootRange::number($this->data["chance"] ?? null, 1.0);
			case "random_chance_with_looting":
				$chance = LootRange::number($this->data["chance"] ?? null, 1.0);
				$multiplier = LootRange::number($this->data["looting_multiplier"] ?? null, 0.0);
				return LootRange::chance() < $chance + $multiplier * $context->getLootingLevel();
			case "killed_by_player":
				return $context->isKilledByPlayer();
			case "killed_by_player_or_pets":
				return $context->isKilledByPlayerOrPet();
			case "match_tool":
				return $this->matchTool($context->getTool());
			case "entity_properties":
				return $this->matchEntity($context);
			default:
				return true;
		}
	}

	private function matchTool(?Item $tool) : bool{
		if($tool === null){
			return false;
		}
		$item = $this->data["item"] ?? null;
		if(is_string($item) && LootItems::identifierOf($tool) !== LootItems::normalize($item)){
			return false;
		}
		if(isset($this->data["count"]) && !LootRange::contains($this->data["count"], $tool->getCount())){
			return false;
		}
		$enchantments = $this->data["enchantments"] ?? [];
		if(!is_array($enchantments)){
			return true;
		}
		foreach($enchantments as $requirement){
			if(!is_array($requirement) || !is_string($requirement["enchantment"] ?? null)){
				continue;
			}
			$enchantment = LootItems::enchantment($requirement["enchantment"]);
			if($enchantment === null){
				return false;
			}
			$level = $tool->getEnchantmentLevel($enchantment);
			if($level === 0){
				return false;
			}
			if(isset($requirement["levels"]) && !LootRange::contains($requirement["levels"], $level)){
				return false;
			}
		}
		return true;
	}

	private function matchEntity(LootContext $context) : bool{
		$target = $this->data["entity"] ?? "this";
		$entity = $target === "killer" ? $context->getKiller() : $context->getEntity();
		if($entity === null){
			return false;
		}
		$properties = $this->data["properties"] ?? [];
		if(is_array($properties) && is_bool($properties["on_fire"] ?? null) && $entity->isOnFire() !== $properties["on_fire"]){
			return false;
		}
		return true;
	}
}
