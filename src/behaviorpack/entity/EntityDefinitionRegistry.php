<?php

declare(strict_types=1);

namespace behaviorpack\entity;

/**
 * Parsed "minecraft:entity" definitions of the custom entities loaded from
 * behavior packs, indexed by identifier.
 */
final class EntityDefinitionRegistry{

	/** @var array<string, array<string, mixed>> */
	private static array $definitions = [];

	private function __construct(){
	}

	/**
	 * @param array<string, mixed> $definition
	 */
	public static function register(string $identifier, array $definition) : void{
		self::$definitions[$identifier] = $definition;
	}

	/**
	 * Returns the raw "minecraft:entity" object of a custom entity, or null
	 * when no behavior pack defines it.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get(string $identifier) : ?array{
		return self::$definitions[$identifier] ?? null;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function all() : array{
		return self::$definitions;
	}

	public static function clear() : void{
		self::$definitions = [];
	}
}
