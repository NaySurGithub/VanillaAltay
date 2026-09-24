<?php

declare(strict_types=1);

namespace behaviorpack\loot;

use behaviorpack\BehaviorPackException;
use function is_array;
use function is_string;
use function str_replace;
use function strtolower;

/**
 * Builds a LootTable from the decoded JSON of a loot_tables file.
 */
final class LootTableParser{

	private function __construct(){
	}

	/**
	 * @param array<mixed> $json
	 *
	 * @throws BehaviorPackException
	 */
	public static function parse(string $path, array $json) : LootTable{
		$pools = $json["pools"] ?? [];
		if(!is_array($pools)){
			throw new BehaviorPackException("\"pools\" must be a list");
		}
		$result = [];
		foreach($pools as $index => $pool){
			if(!is_array($pool)){
				throw new BehaviorPackException("Pool $index must be an object");
			}
			$result[] = self::parsePool($pool, (string) $index);
		}
		return new LootTable($path, $result);
	}

	/**
	 * @param array<mixed> $pool
	 *
	 * @throws BehaviorPackException
	 */
	private static function parsePool(array $pool, string $index) : LootPool{
		$entries = $pool["entries"] ?? [];
		if(!is_array($entries)){
			throw new BehaviorPackException("Pool $index: \"entries\" must be a list");
		}
		$parsed = [];
		foreach($entries as $entryIndex => $entry){
			if(!is_array($entry)){
				throw new BehaviorPackException("Pool $index entry $entryIndex must be an object");
			}
			$parsed[] = self::parseEntry($entry, "Pool $index entry $entryIndex");
		}
		$tiers = $pool["tiers"] ?? null;
		return new LootPool(
			$pool["rolls"] ?? 1,
			LootRange::number($pool["bonus_rolls"] ?? null, 0.0),
			is_array($tiers) ? $tiers : null,
			$parsed,
			self::parseConditions($pool["conditions"] ?? []),
			self::parseFunctions($pool["functions"] ?? [])
		);
	}

	/**
	 * @param array<mixed> $entry
	 *
	 * @throws BehaviorPackException
	 */
	private static function parseEntry(array $entry, string $where) : LootEntry{
		$type = self::stripNamespace($entry["type"] ?? LootEntry::TYPE_ITEM);
		$name = $entry["name"] ?? "";
		if($type !== LootEntry::TYPE_ITEM && $type !== LootEntry::TYPE_LOOT_TABLE && $type !== LootEntry::TYPE_EMPTY){
			throw new BehaviorPackException("$where: unknown entry type \"$type\"");
		}
		if($type !== LootEntry::TYPE_EMPTY && (!is_string($name) || $name === "")){
			throw new BehaviorPackException("$where: missing \"name\"");
		}
		return new LootEntry(
			$type,
			is_string($name) ? $name : "",
			(int) LootRange::number($entry["weight"] ?? null, 1),
			(int) LootRange::number($entry["quality"] ?? null, 0),
			self::parseConditions($entry["conditions"] ?? []),
			self::parseFunctions($entry["functions"] ?? [])
		);
	}

	/**
	 * @return list<LootCondition>
	 */
	private static function parseConditions(mixed $conditions) : array{
		if(!is_array($conditions)){
			return [];
		}
		$result = [];
		foreach($conditions as $condition){
			if(is_array($condition) && is_string($condition["condition"] ?? null)){
				$result[] = new LootCondition(self::stripNamespace($condition["condition"]), $condition);
			}
		}
		return $result;
	}

	/**
	 * @return list<LootFunction>
	 */
	private static function parseFunctions(mixed $functions) : array{
		if(!is_array($functions)){
			return [];
		}
		$result = [];
		foreach($functions as $function){
			if(!is_array($function) || !is_string($function["function"] ?? null)){
				continue;
			}
			$type = self::stripNamespace($function["function"]);
			if(LootFunction::isKnown($type)){
				$result[] = new LootFunction($type, $function, self::parseConditions($function["conditions"] ?? []));
			}
		}
		return $result;
	}

	private static function stripNamespace(mixed $value) : string{
		return is_string($value) ? str_replace("minecraft:", "", strtolower($value)) : "";
	}
}
