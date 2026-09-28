<?php

declare(strict_types=1);

namespace behaviorpack\entity\player;

/**
 * Holds the "minecraft:player" definition of the behavior packs, if any.
 */
final class PlayerDefinition{

	public const IDENTIFIER = "minecraft:player";

	/** @var array<mixed>|null */
	private static ?array $definition = null;

	private function __construct(){
	}

	/**
	 * @param array<mixed> $definition
	 */
	public static function set(array $definition) : void{
		self::$definition = $definition;
	}

	/**
	 * @return array<mixed>|null
	 */
	public static function get() : ?array{
		return self::$definition;
	}

	public static function clear() : void{
		self::$definition = null;
	}
}
