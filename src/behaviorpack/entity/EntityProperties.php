<?php

declare(strict_types=1);

namespace behaviorpack\entity;

use behaviorpack\Molang;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\serializer\CacheableNbt;
use pocketmine\network\mcpe\protocol\SyncActorPropertyPacket;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use function array_values;
use function count;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function max;
use function min;
use function round;
use function strtolower;

/**
 * The "properties" declared in the description of a custom entity: their
 * types, ranges and defaults, and the registry the client needs to read
 * the synced values.
 */
final class EntityProperties{

	public const TYPE_INT = 0;
	public const TYPE_FLOAT = 1;
	public const TYPE_BOOL = 2;
	public const TYPE_ENUM = 3;

	/** @var array<string, array<string, array{type: int, min: float, max: float, values: list<string>, default: mixed, sync: bool, index: int}>> */
	private static array $cache = [];

	/**
	 * @return array<string, array{type: int, min: float, max: float, values: list<string>, default: mixed, sync: bool, index: int}>
	 */
	public static function of(string $identifier) : array{
		if(isset(self::$cache[$identifier])){
			return self::$cache[$identifier];
		}
		$declared = EntityDefinitionRegistry::get($identifier)["description"]["properties"] ?? null;
		$result = [];
		$index = 0;
		if(is_array($declared)){
			foreach($declared as $name => $property){
				if(!is_array($property)){
					continue;
				}
				$type = match(strtolower((string) ($property["type"] ?? ""))){
					"int" => self::TYPE_INT,
					"float" => self::TYPE_FLOAT,
					"bool" => self::TYPE_BOOL,
					"enum" => self::TYPE_ENUM,
					default => null
				};
				if($type === null){
					continue;
				}
				$range = is_array($property["range"] ?? null) ? array_values($property["range"]) : [];
				$values = [];
				foreach(is_array($property["values"] ?? null) ? $property["values"] : [] as $value){
					if(is_string($value)){
						$values[] = $value;
					}
				}
				if($type === self::TYPE_ENUM && count($values) === 0){
					continue;
				}
				$sync = ($property["client_sync"] ?? false) === true;
				$result[(string) $name] = [
					"type" => $type,
					"min" => is_numeric($range[0] ?? null) ? (float) $range[0] : -1000000.0,
					"max" => is_numeric($range[1] ?? null) ? (float) $range[1] : 1000000.0,
					"values" => $values,
					"default" => $property["default"] ?? null,
					"sync" => $sync,
					"index" => $sync ? $index++ : -1
				];
			}
		}
		return self::$cache[$identifier] = $result;
	}

	public static function clear() : void{
		self::$cache = [];
	}

	/**
	 * Returns the default value of a property, evaluating a Molang default.
	 *
	 * @param array{type: int, min: float, max: float, values: list<string>, default: mixed, sync: bool, index: int} $property
	 */
	public static function defaultValue(array $property) : bool|int|float|string{
		$default = $property["default"];
		if($property["type"] === self::TYPE_ENUM){
			if(is_string($default) && in_array($default, $property["values"], true)){
				return $default;
			}
			if(is_string($default)){
				$index = (int) Molang::evaluate($default);
				return $property["values"][$index] ?? $property["values"][0];
			}
			return $property["values"][0];
		}
		if($property["type"] === self::TYPE_BOOL){
			if(is_bool($default)){
				return $default;
			}
			return Molang::evaluate($default) != 0;
		}
		$value = $default === null ? $property["min"] : Molang::evaluate($default);
		return self::clamp($property, $value);
	}

	/**
	 * Converts and validates a value for a property, or returns null when the
	 * value does not fit it.
	 *
	 * @param array{type: int, min: float, max: float, values: list<string>, default: mixed, sync: bool, index: int} $property
	 */
	public static function coerce(array $property, mixed $value) : bool|int|float|string|null{
		return match($property["type"]){
			self::TYPE_BOOL => is_bool($value) ? $value : null,
			self::TYPE_ENUM => is_string($value) && in_array($value, $property["values"], true) ? $value : null,
			self::TYPE_INT => (is_int($value) || (is_float($value) && round($value) == $value)) && $value >= $property["min"] && $value <= $property["max"] ? (int) $value : null,
			self::TYPE_FLOAT => (is_int($value) || is_float($value)) && $value >= $property["min"] && $value <= $property["max"] ? (float) $value : null,
			default => null
		};
	}

	/**
	 * @param array{type: int, min: float, max: float, values: list<string>, default: mixed, sync: bool, index: int} $property
	 */
	private static function clamp(array $property, float $value) : int|float{
		$value = max($property["min"], min($property["max"], $value));
		return $property["type"] === self::TYPE_INT ? (int) round($value) : $value;
	}

	/**
	 * Builds the synced values of an entity.
	 *
	 * @param array<string, bool|int|float|string> $values
	 */
	public static function syncData(string $identifier, array $values) : PropertySyncData{
		$ints = [];
		$floats = [];
		foreach(self::of($identifier) as $name => $property){
			if(!$property["sync"] || !isset($values[$name])){
				continue;
			}
			$value = $values[$name];
			switch($property["type"]){
				case self::TYPE_FLOAT:
					$floats[$property["index"]] = (float) $value;
					break;
				case self::TYPE_BOOL:
					$ints[$property["index"]] = $value === true ? 1 : 0;
					break;
				case self::TYPE_ENUM:
					$ints[$property["index"]] = (int) \array_search($value, $property["values"], true);
					break;
				default:
					$ints[$property["index"]] = (int) $value;
			}
		}
		return new PropertySyncData($ints, $floats);
	}

	/**
	 * Builds the packet describing the synced properties of an entity type,
	 * or returns null when it syncs none.
	 */
	public static function registryPacket(string $identifier) : ?SyncActorPropertyPacket{
		$entries = [];
		foreach(self::of($identifier) as $name => $property){
			if(!$property["sync"]){
				continue;
			}
			$entry = CompoundTag::create()
				->setString("name", $name)
				->setInt("type", $property["type"]);
			if($property["type"] === self::TYPE_INT){
				$entry->setInt("min", (int) $property["min"]);
				$entry->setInt("max", (int) $property["max"]);
			}elseif($property["type"] === self::TYPE_FLOAT){
				$entry->setFloat("min", $property["min"]);
				$entry->setFloat("max", $property["max"]);
			}elseif($property["type"] === self::TYPE_ENUM){
				$entry->setTag("enum", new ListTag(\array_map(fn(string $value) => new StringTag($value), $property["values"]), NBT::TAG_String));
			}
			$entries[] = $entry;
		}
		if(count($entries) === 0){
			return null;
		}
		$root = CompoundTag::create()
			->setString("type", $identifier)
			->setTag("properties", new ListTag($entries, NBT::TAG_Compound));
		return SyncActorPropertyPacket::create(new CacheableNbt($root));
	}
}
