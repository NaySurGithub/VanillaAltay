<?php

declare(strict_types=1);

namespace redstone\block\tile\comparator;

use Closure;
use pocketmine\block\Block;
use redstone\block\Redstone;
use redstone\block\RedstoneComparator;
use redstone\block\RedstoneRepeater;
use redstone\block\RedstoneWire;
use redstone\vanilla\ExtraVanillaBlocks;

final class ComparatorWeightRegistry{

	/** @var Closure[] */
	private static array $computers = [];

	public static function init() : void{
		$block = ExtraVanillaBlocks::REDSTONE_WIRE();
		self::register($block, static function(RedstoneWire $block) : int{ return $block->getPowerLevel(); });

		$block = ExtraVanillaBlocks::REDSTONE();
		self::register($block, static function(Redstone $block) : int{ return $block->getPowerLevel(); });

		$block = ExtraVanillaBlocks::REDSTONE_REPEATER();
		self::register($block, static function(RedstoneRepeater $block) : int{ return $block->getPowerLevel(); });

		$block = ExtraVanillaBlocks::REDSTONE_COMPARATOR();
		self::register($block, static function(RedstoneComparator $block) : int{ return $block->getPowerLevel(); });
	}

	/**
	 * @template TBlock of Block
	 * @param TBlock $block
	 * @param Closure(TBlock) : int $computer
	 */
	public static function register(Block $block, Closure $computer) : void{
		self::$computers[$block->getTypeId()] = $computer;
	}

	/**
	 * @param Block $block
	 * @return (Closure(Block) : int)|null $computer
	 */
	public static function get(Block $block) : ?Closure{
		return self::$computers[$block->getTypeId()] ?? null;
	}

	public static function getValue(Block $block) : int{
		return isset(self::$computers[$id = $block->getTypeId()]) ? (self::$computers[$id])($block) : 0;
	}
}