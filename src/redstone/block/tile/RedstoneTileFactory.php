<?php

declare(strict_types=1);

namespace redstone\block\tile;

use pocketmine\block\tile\Tile;
use pocketmine\block\tile\TileFactory as VanillaTileFactory;
use redstone\block\tile\comparator\ComparatorWeightRegistry;
use redstone\block\tile\dispenser\DispensableItemManager;
use redstone\block\tile\dispenser\Dispenser;
use redstone\block\tile\dispenser\Dropper;
use redstone\block\tile\piston\PistonArm;
use ReflectionProperty;
use function array_keys;

final class RedstoneTileFactory{

	public static function init() : void{
		self::register(Dispenser::class, ["Dispenser", "minecraft:dispenser"]);
		self::register(Dropper::class, ["Dropper", "minecraft:dropper"]);
		self::register(PistonArm::class, ["PistonArm", "minecraft:piston_arm"]);
		DispensableItemManager::init();
		ComparatorWeightRegistry::init();
	}

	/**
	 * @param class-string<Tile> $className
	 * @param string[] $saveNames
	 */
	private static function register(string $className, array $saveNames) : void{
		VanillaTileFactory::getInstance()->register($className, $saveNames);
	}
}