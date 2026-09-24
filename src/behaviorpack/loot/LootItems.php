<?php

declare(strict_types=1);

namespace behaviorpack\loot;

use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\data\bedrock\EnchantmentIds;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use Throwable;
use function str_contains;
use function str_replace;
use function strtolower;
use function trim;

/**
 * Resolves the item and enchantment identifiers used by loot tables.
 */
final class LootItems{

	private const ENCHANTMENT_IDS = [
		"looting" => EnchantmentIds::LOOTING,
		"smite" => EnchantmentIds::SMITE,
		"bane_of_arthropods" => EnchantmentIds::BANE_OF_ARTHROPODS,
		"luck_of_the_sea" => EnchantmentIds::LUCK_OF_THE_SEA,
		"lure" => EnchantmentIds::LURE,
		"depth_strider" => EnchantmentIds::DEPTH_STRIDER,
		"binding" => EnchantmentIds::BINDING,
		"soul_speed" => EnchantmentIds::SOUL_SPEED
	];

	private function __construct(){
	}

	public static function normalize(string $identifier) : string{
		$identifier = strtolower(trim($identifier));
		return str_contains($identifier, ":") ? $identifier : "minecraft:" . $identifier;
	}

	public static function resolve(string $identifier, int $meta = 0) : ?Item{
		$identifier = self::normalize($identifier);
		try{
			return GlobalItemDataHandlers::getDeserializer()->deserializeType(new SavedItemData($identifier, $meta));
		}catch(Throwable){
		}
		if($meta !== 0){
			return null;
		}
		return StringToItemParser::getInstance()->parse($identifier);
	}

	public static function identifierOf(Item $item) : ?string{
		try{
			return GlobalItemDataHandlers::getSerializer()->serializeType($item)->getName();
		}catch(Throwable){
			return null;
		}
	}

	public static function enchantment(string $name) : ?Enchantment{
		$name = str_replace("minecraft:", "", strtolower(trim($name)));
		$enchantment = StringToEnchantmentParser::getInstance()->parse($name);
		if($enchantment !== null){
			return $enchantment;
		}
		$id = self::ENCHANTMENT_IDS[$name] ?? null;
		return $id === null ? null : EnchantmentIdMap::getInstance()->fromId($id);
	}

	public static function lootingLevel(?Item $tool) : int{
		if($tool === null){
			return 0;
		}
		$looting = self::enchantment("looting");
		return $looting === null ? 0 : $tool->getEnchantmentLevel($looting);
	}
}
