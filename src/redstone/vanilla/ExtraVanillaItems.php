<?php

declare(strict_types=1);

namespace redstone\vanilla;

use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\utils\CloningRegistryTrait;
use ReflectionProperty;

/**
 * @method static Redstone REDSTONE_DUST()
 */
final class ExtraVanillaItems{
	use CloningRegistryTrait;

	private function __construct(){
	}

	protected static function register(string $name, Item $item) : void{
		self::_registryRegister($name, $item);
	}

	/**
	 * @return Item[]
	 * @phpstan-return array<string, Item>
	 */
	public static function getAll() : array{
		//phpstan doesn't support generic traits yet :(
		/** @var Item[] $result */
		$result = self::_registryGetAll();
		return $result;
	}

	protected static function setup() : void{
		self::register("redstone_dust", new Redstone(new ItemIdentifier(ItemTypeIds::newId()), "Redstone Dust"));
	}
}