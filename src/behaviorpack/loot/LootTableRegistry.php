<?php

declare(strict_types=1);

namespace behaviorpack\loot;

use function count;
use function ltrim;
use function str_replace;

/**
 * The loot tables of every behavior pack, keyed by their pack-relative path,
 * for example "loot_tables/blocks/ruby_ore.json".
 */
final class LootTableRegistry{

	/** @var array<string, LootTable> */
	private static array $tables = [];

	private function __construct(){
	}

	public static function normalizePath(string $path) : string{
		return ltrim(str_replace("\\", "/", $path), "/");
	}

	public static function register(LootTable $table) : void{
		self::$tables[self::normalizePath($table->getPath())] = $table;
	}

	public static function get(string $path) : ?LootTable{
		return self::$tables[self::normalizePath($path)] ?? null;
	}

	/**
	 * @return array<string, LootTable>
	 */
	public static function getAll() : array{
		return self::$tables;
	}

	public static function count() : int{
		return count(self::$tables);
	}

	public static function clear() : void{
		self::$tables = [];
	}
}
