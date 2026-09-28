<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\combat;

use pocketmine\event\entity\EntityDamageEvent;
use function is_string;
use function strtolower;

/**
 * Converts between server damage causes and the damage cause names used by
 * entity definitions.
 */
final class DamageCause{

	private const NAMES = [
		EntityDamageEvent::CAUSE_CONTACT => "contact",
		EntityDamageEvent::CAUSE_ENTITY_ATTACK => "entity_attack",
		EntityDamageEvent::CAUSE_PROJECTILE => "projectile",
		EntityDamageEvent::CAUSE_SUFFOCATION => "suffocation",
		EntityDamageEvent::CAUSE_FALL => "fall",
		EntityDamageEvent::CAUSE_FIRE => "fire",
		EntityDamageEvent::CAUSE_FIRE_TICK => "fire_tick",
		EntityDamageEvent::CAUSE_LAVA => "lava",
		EntityDamageEvent::CAUSE_DROWNING => "drowning",
		EntityDamageEvent::CAUSE_BLOCK_EXPLOSION => "block_explosion",
		EntityDamageEvent::CAUSE_ENTITY_EXPLOSION => "entity_explosion",
		EntityDamageEvent::CAUSE_VOID => "void",
		EntityDamageEvent::CAUSE_SUICIDE => "self_destruct",
		EntityDamageEvent::CAUSE_MAGIC => "magic",
		EntityDamageEvent::CAUSE_CUSTOM => "override",
		EntityDamageEvent::CAUSE_STARVATION => "starve",
		EntityDamageEvent::CAUSE_FALLING_BLOCK => "falling_block",
		EntityDamageEvent::CAUSE_FREEZING => "freezing"
	];

	private const ALIASES = [
		"entity_attack" => EntityDamageEvent::CAUSE_ENTITY_ATTACK,
		"projectile" => EntityDamageEvent::CAUSE_PROJECTILE,
		"suffocation" => EntityDamageEvent::CAUSE_SUFFOCATION,
		"fall" => EntityDamageEvent::CAUSE_FALL,
		"fire" => EntityDamageEvent::CAUSE_FIRE,
		"fire_tick" => EntityDamageEvent::CAUSE_FIRE_TICK,
		"lava" => EntityDamageEvent::CAUSE_LAVA,
		"drowning" => EntityDamageEvent::CAUSE_DROWNING,
		"block_explosion" => EntityDamageEvent::CAUSE_BLOCK_EXPLOSION,
		"entity_explosion" => EntityDamageEvent::CAUSE_ENTITY_EXPLOSION,
		"void" => EntityDamageEvent::CAUSE_VOID,
		"self_destruct" => EntityDamageEvent::CAUSE_SUICIDE,
		"suicide" => EntityDamageEvent::CAUSE_SUICIDE,
		"magic" => EntityDamageEvent::CAUSE_MAGIC,
		"wither" => EntityDamageEvent::CAUSE_MAGIC,
		"override" => EntityDamageEvent::CAUSE_CUSTOM,
		"starve" => EntityDamageEvent::CAUSE_STARVATION,
		"falling_block" => EntityDamageEvent::CAUSE_FALLING_BLOCK,
		"anvil" => EntityDamageEvent::CAUSE_FALLING_BLOCK,
		"freezing" => EntityDamageEvent::CAUSE_FREEZING,
		"contact" => EntityDamageEvent::CAUSE_CONTACT,
		"thorns" => EntityDamageEvent::CAUSE_MAGIC,
		"lightning" => EntityDamageEvent::CAUSE_FIRE,
		"temperature" => EntityDamageEvent::CAUSE_FIRE_TICK
	];

	public static function name(int $cause) : string{
		return self::NAMES[$cause] ?? "none";
	}

	/**
	 * Returns the server cause for a definition cause name, or null when the
	 * name is unknown.
	 */
	public static function fromName(mixed $name) : ?int{
		if(!is_string($name)){
			return null;
		}
		return self::ALIASES[strtolower($name)] ?? null;
	}

	/**
	 * Returns whether a definition cause (missing or "all" matches anything)
	 * matches a server cause.
	 */
	public static function matches(mixed $expected, int $cause) : bool{
		if(!is_string($expected) || $expected === "" || strtolower($expected) === "all"){
			return true;
		}
		$expected = strtolower($expected);
		if($expected === self::name($cause)){
			return true;
		}
		return (self::ALIASES[$expected] ?? null) === $cause;
	}
}
