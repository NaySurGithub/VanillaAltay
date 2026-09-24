<?php

declare(strict_types=1);

namespace dimension\rule;

use dimension\rule\block\DimensionIce;
use dimension\rule\block\DimensionLava;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\VanillaBlocks;
use ReflectionProperty;

/**
 * Swaps the runtime class of lava and ice for dimension aware subclasses.
 * The type id and every state id stay the same, so saved worlds, the network
 * palette and the block serializers keep working unchanged: only the object
 * the world hands out when a state id is read changes.
 */
final class BlockOverrides{

	private function __construct(){
	}

	public static function register() : void{
		$lava = VanillaBlocks::LAVA();
		self::replace(new DimensionLava($lava->getIdInfo(), $lava->getName(), self::typeInfo($lava)));

		$ice = VanillaBlocks::ICE();
		self::replace(new DimensionIce($ice->getIdInfo(), $ice->getName(), self::typeInfo($ice)));
	}

	private static function typeInfo(Block $block) : BlockTypeInfo{
		return new BlockTypeInfo($block->getBreakInfo(), $block->getTypeTags(), $block->getEnchantmentTags());
	}

	private static function replace(Block $replacement) : void{
		$registry = RuntimeBlockStateRegistry::getInstance();

		$fullListProperty = new ReflectionProperty(RuntimeBlockStateRegistry::class, "fullList");
		$fullList = $fullListProperty->getValue($registry);
		foreach($replacement->generateStatePermutations() as $state){
			$fullList[$state->getStateId()] = $state;
		}
		$fullListProperty->setValue($registry, $fullList);

		$typeIndexProperty = new ReflectionProperty(RuntimeBlockStateRegistry::class, "typeIndex");
		$typeIndex = $typeIndexProperty->getValue($registry);
		$typeIndex[$replacement->getTypeId()] = clone $replacement;
		$typeIndexProperty->setValue($registry, $typeIndex);
	}
}
