<?php

declare(strict_types=1);

namespace VanillaAltay;

use pocketmine\utils\Config;

use function array_filter;
use function array_key_exists;
use function array_map;
use function explode;
use function in_array;
use function is_array;
use function is_bool;
use function is_int;
use function max;
use function strtolower;

use const ARRAY_FILTER_USE_BOTH;

final class VanillaAltayConfig
{
	/** @var array<string, mixed> */
	private static array $values = [];

	private function __construct()
	{
		//NOOP
	}

	public static function load(Config $config) : void
	{
		self::$values = $config->getAll();
	}

	public static function generationEnabled() : bool
	{
		return self::bool("generation.enabled", true);
	}

	public static function customBiomesEnabled() : bool
	{
		return self::generationEnabled() && self::bool("generation.register-custom-biomes", true);
	}

	public static function featureEnabled(string $feature) : bool
	{
		return self::generationEnabled() && self::bool("generation.features." . $feature, true);
	}

	public static function locateCommandEnabled() : bool
	{
		return self::bool("commands.locate.enabled", true);
	}

	public static function commandHintsEnabled() : bool
	{
		return self::bool("commands.locate.command-hints", true);
	}

	public static function summonCommandEnabled() : bool
	{
		return self::entitiesEnabled() && self::bool("commands.summon.enabled", true);
	}

	public static function summonCommandHintsEnabled() : bool
	{
		return self::summonCommandEnabled() && self::bool("commands.summon.command-hints", true);
	}

	public static function entitiesEnabled() : bool
	{
		return self::bool("entities.enabled", true);
	}

	public static function entitySelfTestEnabled() : bool
	{
		return self::entitiesEnabled() && self::bool("entities.self-test-on-startup", false);
	}

	public static function entityAiEnabled() : bool
	{
		return self::entitiesEnabled() && self::bool("entities.behavior-ai", true);
	}

	public static function naturalSpawningEnabled() : bool
	{
		return self::entitiesEnabled() && self::bool("entities.natural-spawning", true);
	}

	public static function overrideAltayEntities() : bool
	{
		return self::entitiesEnabled() && self::bool("entities.override-altay-entities", true);
	}

	public static function spawnEggOverridesEnabled() : bool
	{
		return self::overrideAltayEntities() && self::bool("entities.interactions.spawn-egg-overrides", true);
	}

	public static function mountsEnabled() : bool
	{
		return self::entitiesEnabled() && self::bool("entities.interactions.mounts", true);
	}

	public static function ownerCombatEnabled() : bool
	{
		return self::entitiesEnabled() && self::bool("entities.interactions.owner-combat", true);
	}

	public static function angerPropagationEnabled() : bool
	{
		return self::entitiesEnabled() && self::bool("entities.interactions.anger-propagation", true);
	}

	public static function spawnInterval() : int
	{
		return self::int("entities.spawning.interval-ticks", 20, 1);
	}

	public static function monsterCap() : int
	{
		return self::int("entities.spawning.monster-cap", 70, 0);
	}

	public static function creatureCap() : int
	{
		return self::int("entities.spawning.creature-cap", 10, 0);
	}

	public static function waterCreatureCap() : int
	{
		return self::int("entities.spawning.water-creature-cap", 20, 0);
	}

	public static function ambientCap() : int
	{
		return self::int("entities.spawning.ambient-cap", 15, 0);
	}

	public static function entitySpawnEnabled(string $identifier) : bool
	{
		$disabled = self::value("entities.spawning.disabled-types", []);
		return !is_array($disabled) || !in_array(strtolower($identifier), array_map(static fn(mixed $id) : string => strtolower((string) $id), $disabled), true);
	}

	public static function structureEnabled(string $name) : bool
	{
		return self::featureEnabled("structures") && self::bool("generation.structures." . $name, true);
	}

	/** @param array<string, world\generator\structure\Structure> $structures */
	public static function filterStructures(array $structures) : array
	{
		return array_filter($structures, fn($structure, string $name) : bool => self::structureEnabled($name), ARRAY_FILTER_USE_BOTH);
	}

	private static function bool(string $path, bool $default) : bool
	{
		$value = self::value($path, $default);
		return is_bool($value) ? $value : $default;
	}

	private static function int(string $path, int $default, int $minimum) : int
	{
		$value = self::value($path, $default);
		return is_int($value) ? max($minimum, $value) : $default;
	}

	private static function value(string $path, mixed $default) : mixed
	{
		$value = self::$values;
		foreach (explode(".", $path) as $key) {
			if (!is_array($value) || !array_key_exists($key, $value)) {
				return $default;
			}
			$value = $value[$key];
		}

		return $value;
	}
}
