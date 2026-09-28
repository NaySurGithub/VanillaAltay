<?php

declare(strict_types=1);

namespace behaviorpack\spawn;

use pocketmine\block\Block;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use Throwable;
use function str_contains;
use function strtolower;

/**
 * Block identifiers as written in spawn rules, including legacy names.
 */
final class BlockNames{

	private const ALIASES = [
		"minecraft:grass" => "minecraft:grass_block",
		"minecraft:log" => "minecraft:oak_log",
		"minecraft:leaves" => "minecraft:oak_leaves",
		"minecraft:planks" => "minecraft:oak_planks"
	];

	public static function normalize(string $name) : string{
		$name = strtolower($name);
		if(!str_contains($name, ":")){
			$name = "minecraft:" . $name;
		}
		return self::ALIASES[$name] ?? $name;
	}

	public static function of(Block $block) : string{
		try{
			return GlobalBlockStateHandlers::getSerializer()->serialize($block->getStateId())->getName();
		}catch(Throwable){
			return "";
		}
	}
}
