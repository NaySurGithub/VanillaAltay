<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior;

use function class_exists;
use function constant;
use function defined;

/**
 * Maps the component names of the entity definitions to their systems and
 * goals. Each group of components declares its own table in the "registry"
 * namespace, as a SYSTEMS or GOALS constant.
 */
final class BehaviorRegistry{

	private const TABLES = [
		"MovementRegistry",
		"CombatRegistry",
		"TamingRegistry",
		"InventoryRegistry",
		"StateRegistry",
		"CombatGoalRegistry",
		"MovementGoalRegistry",
		"SocialGoalRegistry"
	];

	/** @var array<string, class-string<EntitySystem>>|null */
	private static ?array $systems = null;

	/** @var array<string, class-string<Goal>>|null */
	private static ?array $goals = null;

	private static function load() : void{
		self::$systems = [];
		self::$goals = [];
		foreach(self::TABLES as $table){
			$class = __NAMESPACE__ . "\\registry\\" . $table;
			if(!class_exists($class)){
				continue;
			}
			if(defined($class . "::SYSTEMS")){
				foreach(constant($class . "::SYSTEMS") as $component => $system){
					self::$systems[$component] = $system;
				}
			}
			if(defined($class . "::GOALS")){
				foreach(constant($class . "::GOALS") as $component => $goal){
					self::$goals[$component] = $goal;
				}
			}
		}
	}

	/**
	 * @return class-string<EntitySystem>|null
	 */
	public static function system(string $component) : ?string{
		if(self::$systems === null){
			self::load();
		}
		return self::$systems[$component] ?? null;
	}

	/**
	 * @return class-string<Goal>|null
	 */
	public static function goal(string $component) : ?string{
		if(self::$goals === null){
			self::load();
		}
		return self::$goals[$component] ?? null;
	}
}
