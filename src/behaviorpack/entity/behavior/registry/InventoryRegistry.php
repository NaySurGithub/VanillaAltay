<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\registry;

use behaviorpack\entity\behavior\inventory\EquipmentSystem;
use behaviorpack\entity\behavior\inventory\EquippableSystem;
use behaviorpack\entity\behavior\inventory\InteractSystem;
use behaviorpack\entity\behavior\inventory\InventorySystem;
use behaviorpack\entity\behavior\inventory\TradeTableSystem;

/**
 * The inventory, equipment, interaction and trading components.
 */
final class InventoryRegistry{

	public const SYSTEMS = [
		"minecraft:inventory" => InventorySystem::class,
		"minecraft:equipment" => EquipmentSystem::class,
		"minecraft:equippable" => EquippableSystem::class,
		"minecraft:interact" => InteractSystem::class,
		"minecraft:economy_trade_table" => TradeTableSystem::class,
		"minecraft:trade_table" => TradeTableSystem::class
	];
}
