<?php

declare(strict_types=1);

namespace behaviorpack;

use function is_bool;
use function is_int;
use function is_string;
use function max;

/**
 * The "behavior-packs" section of config.yml.
 */
final class BehaviorPackSettings{

	public function __construct(
		public readonly bool $enabled,
		public readonly string $folder,
		public readonly bool $itemsAndBlocks,
		public readonly bool $entities,
		public readonly bool $recipes,
		public readonly bool $lootTables,
		public readonly bool $scripts,
		public readonly int $scriptTimeoutMs
	){}

	/**
	 * @param array<mixed> $section
	 */
	public static function fromConfig(array $section) : self{
		$timeout = $section["script-timeout-ms"] ?? null;
		$folder = $section["folder"] ?? null;
		return new self(
			self::flag($section, "enabled"),
			is_string($folder) && $folder !== "" ? $folder : "behavior_packs",
			self::flag($section, "items-and-blocks"),
			self::flag($section, "entities"),
			self::flag($section, "recipes"),
			self::flag($section, "loot-tables"),
			self::flag($section, "scripts"),
			is_int($timeout) ? max(100, $timeout) : 2000
		);
	}

	/**
	 * @param array<mixed> $section
	 */
	private static function flag(array $section, string $key) : bool{
		$value = $section[$key] ?? null;
		return is_bool($value) ? $value : true;
	}
}
