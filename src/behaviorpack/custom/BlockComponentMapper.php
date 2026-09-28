<?php

declare(strict_types=1);

namespace behaviorpack\custom;

use behaviorpack\BehaviorPackException;
use behaviorpack\custom\block\ExtraBlockComponents;
use behaviorpack\custom\block\LiquidDetection;
use behaviorpack\custom\block\PlacementFilter;
use customiesdevs\customies\block\component\BlockComponent;
use customiesdevs\customies\block\component\CollisionBoxComponent;
use customiesdevs\customies\block\component\DestructibleByExplosionComponent;
use customiesdevs\customies\block\component\DestructibleByMiningComponent;
use customiesdevs\customies\block\component\DisplayNameComponent;
use customiesdevs\customies\block\component\FlammableComponent;
use customiesdevs\customies\block\component\FrictionComponent;
use customiesdevs\customies\block\component\GeometryComponent;
use customiesdevs\customies\block\component\LightDampeningComponent;
use customiesdevs\customies\block\component\LightEmissionComponent;
use customiesdevs\customies\block\component\MaterialInstancesComponent;
use customiesdevs\customies\block\component\SelectionBoxComponent;
use customiesdevs\customies\block\Material;
use pocketmine\math\Vector3;
use function count;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function max;
use function min;

/**
 * Turns the JSON components of a minecraft:block definition into Customies
 * block components, and reads the server-side values they imply.
 */
final class BlockComponentMapper{

	public const UNBREAKABLE_EXPLOSION_RESISTANCE = 3600000.0;

	/** Components handled by other loaders or by scripts, never reported. */
	public const IGNORED = [
		"minecraft:loot",
		"minecraft:custom_components",
		"minecraft:tick",
		"minecraft:redstone_consumer"
	];

	public const SUPPORTED = [
		"minecraft:geometry",
		"minecraft:unit_cube",
		"minecraft:material_instances",
		"minecraft:collision_box",
		"minecraft:selection_box",
		"minecraft:destructible_by_mining",
		"minecraft:destructible_by_explosion",
		"minecraft:friction",
		"minecraft:light_emission",
		"minecraft:light_dampening",
		"minecraft:flammable",
		"minecraft:display_name",
		"minecraft:transformation",
		"minecraft:liquid_detection",
		"minecraft:map_color",
		"minecraft:destruction_particles",
		"minecraft:redstone_conductivity",
		"minecraft:movable",
		"minecraft:precipitation_interactions",
		"minecraft:flower_pottable",
		"minecraft:random_offset",
		"minecraft:replaceable",
		"minecraft:support",
		"minecraft:placement_filter",
		"minecraft:embedded_visual",
		"minecraft:item_visual",
		"minecraft:connection_rule"
	];

	public const GEOMETRY_FULL_BLOCK = "minecraft:geometry.full_block";
	public const GEOMETRY_CROSS = "minecraft:geometry.cross";

	private function __construct(){
	}

	public static function isSupported(string $name) : bool{
		return in_array($name, self::SUPPORTED, true);
	}

	/**
	 * Returns the Customies component for a JSON component, or null when it
	 * is not supported.
	 *
	 * @throws BehaviorPackException
	 */
	public static function map(string $name, mixed $value) : ?BlockComponent{
		return match($name){
			"minecraft:geometry" => self::geometry($value),
			"minecraft:unit_cube" => new GeometryComponent(),
			"minecraft:material_instances" => self::materialInstances($value),
			"minecraft:collision_box" => self::collisionBox($value),
			"minecraft:selection_box" => self::selectionBox($value),
			"minecraft:destructible_by_mining" => new DestructibleByMiningComponent(self::hardness($value)),
			"minecraft:destructible_by_explosion" => new DestructibleByExplosionComponent(self::explosionResistance($value)),
			"minecraft:friction" => new FrictionComponent(self::friction($value)),
			"minecraft:light_emission" => new LightEmissionComponent(self::lightLevel($value, 0)),
			"minecraft:light_dampening" => new LightDampeningComponent(self::lightLevel($value, 15)),
			"minecraft:flammable" => self::flammable($value),
			"minecraft:display_name" => new DisplayNameComponent(self::displayName($value)),
			"minecraft:transformation" => self::transformation($value),
			"minecraft:liquid_detection" => LiquidDetection::component($value),
			"minecraft:map_color" => ExtraBlockComponents::mapColor($value),
			"minecraft:destruction_particles" => ExtraBlockComponents::destructionParticles($value),
			"minecraft:redstone_conductivity" => ExtraBlockComponents::redstoneConductivityComponent($value),
			"minecraft:movable" => ExtraBlockComponents::movableComponent($value),
			"minecraft:precipitation_interactions" => ExtraBlockComponents::precipitationInteractions($value),
			"minecraft:flower_pottable", "minecraft:replaceable" => ExtraBlockComponents::marker($name, $value),
			"minecraft:random_offset" => ExtraBlockComponents::randomOffset($value),
			"minecraft:support" => ExtraBlockComponents::support($value),
			"minecraft:placement_filter" => PlacementFilter::component($value),
			"minecraft:embedded_visual", "minecraft:item_visual" => ExtraBlockComponents::visual($name, $value),
			"minecraft:connection_rule" => \behaviorpack\custom\connection\ConnectionRule::map($value),
			default => null
		};
	}

	/**
	 * Returns the geometry identifier of a minecraft:geometry value, or null
	 * when it has none.
	 */
	public static function geometryIdentifier(mixed $value) : ?string{
		if(is_string($value)){
			return $value;
		}
		if(is_array($value) && is_string($value["identifier"] ?? null)){
			return $value["identifier"];
		}
		return null;
	}

	/**
	 * Returns the collision box a block gets when it has no collision_box
	 * component: none for the cross geometry, a full cube otherwise.
	 *
	 * @param array<mixed> $components
	 * @return list<float>|null
	 */
	public static function defaultCollision(array $components) : ?array{
		if(self::geometryIdentifier($components["minecraft:geometry"] ?? null) === self::GEOMETRY_CROSS){
			return null;
		}
		return [0.0, 0.0, 0.0, 1.0, 1.0, 1.0];
	}

	/**
	 * Returns the seconds_to_destroy of a destructible_by_mining value, or -1
	 * when the block cannot be mined.
	 *
	 * @throws BehaviorPackException
	 */
	public static function hardness(mixed $value) : float{
		if($value === false){
			return -1.0;
		}
		if($value === true){
			return 0.0;
		}
		return self::number(self::field($value, "seconds_to_destroy", 0.0), "destructible_by_mining");
	}

	/**
	 * @throws BehaviorPackException
	 */
	public static function explosionResistance(mixed $value) : float{
		if($value === false){
			return self::UNBREAKABLE_EXPLOSION_RESISTANCE;
		}
		if($value === true){
			return 0.0;
		}
		return self::number(self::field($value, "explosion_resistance", 0.0), "destructible_by_explosion");
	}

	/**
	 * @throws BehaviorPackException
	 */
	public static function friction(mixed $value) : float{
		return max(0.0, min(0.9, self::number(self::field($value, "value", 0.4), "friction")));
	}

	/**
	 * @throws BehaviorPackException
	 */
	public static function lightLevel(mixed $value, int $default) : int{
		return (int) max(0, min(15, self::number(self::field($value, "value", $default), "light level")));
	}

	/**
	 * Returns the catch and destroy chance modifiers, or null when the block
	 * does not burn.
	 *
	 * @return array{int, int}|null
	 * @throws BehaviorPackException
	 */
	public static function flammability(mixed $value) : ?array{
		if($value === false){
			return null;
		}
		if($value === true){
			return [5, 20];
		}
		if(!is_array($value)){
			throw new BehaviorPackException("minecraft:flammable must be a boolean or an object");
		}
		return [
			(int) self::number($value["catch_chance_modifier"] ?? 5, "catch_chance_modifier"),
			(int) self::number($value["destroy_chance_modifier"] ?? 20, "destroy_chance_modifier")
		];
	}

	/**
	 * Returns the box of a collision_box or selection_box value in block
	 * units as [minX, minY, minZ, maxX, maxY, maxZ], or null when disabled.
	 *
	 * @return list<float>|null
	 * @throws BehaviorPackException
	 */
	public static function box(mixed $value) : ?array{
		if($value === false){
			return null;
		}
		if($value === true){
			return [0.0, 0.0, 0.0, 1.0, 1.0, 1.0];
		}
		[$origin, $size] = self::boxVectors($value);
		$minX = ($origin->x + 8) / 16;
		$minY = $origin->y / 16;
		$minZ = ($origin->z + 8) / 16;
		return [$minX, $minY, $minZ, $minX + $size->x / 16, $minY + $size->y / 16, $minZ + $size->z / 16];
	}

	/**
	 * @throws BehaviorPackException
	 */
	public static function displayName(mixed $value) : string{
		$name = self::field($value, "value", null);
		if(!is_string($name)){
			throw new BehaviorPackException("minecraft:display_name must be a string");
		}
		return $name;
	}

	/**
	 * @throws BehaviorPackException
	 */
	private static function geometry(mixed $value) : GeometryComponent{
		if(is_string($value)){
			return new GeometryComponent($value);
		}
		if(!is_array($value) || !is_string($value["identifier"] ?? null)){
			throw new BehaviorPackException("minecraft:geometry needs an identifier");
		}
		$component = new GeometryComponent($value["identifier"]);
		$bones = $value["bone_visibility"] ?? [];
		if(is_array($bones)){
			foreach($bones as $bone => $visibility){
				if(is_bool($visibility) || is_string($visibility)){
					$component->addBoneVisibility((string) $bone, $visibility);
				}
			}
		}
		return $component;
	}

	/**
	 * @throws BehaviorPackException
	 */
	private static function materialInstances(mixed $value) : MaterialInstancesComponent{
		if(!is_array($value) || count($value) === 0){
			throw new BehaviorPackException("minecraft:material_instances must be a non-empty object");
		}
		$materials = [];
		foreach($value as $target => $instance){
			$resolved = $instance;
			$depth = 0;
			while(is_string($resolved) && $depth < 8){
				$resolved = $value[$resolved] ?? null;
				$depth++;
			}
			if(!is_array($resolved)){
				throw new BehaviorPackException("Material instance \"$target\" cannot be resolved");
			}
			$texture = $resolved["texture"] ?? null;
			if(!is_string($texture)){
				throw new BehaviorPackException("Material instance \"$target\" has no texture");
			}
			$renderMethod = $resolved["render_method"] ?? Material::RENDER_METHOD_OPAQUE;
			$materials[] = new Material(
				(string) $target,
				$texture,
				is_string($renderMethod) ? $renderMethod : Material::RENDER_METHOD_OPAQUE,
				($resolved["face_dimming"] ?? true) !== false,
				($resolved["ambient_occlusion"] ?? true) !== false
			);
		}
		return new MaterialInstancesComponent($materials);
	}

	/**
	 * @throws BehaviorPackException
	 */
	private static function collisionBox(mixed $value) : CollisionBoxComponent{
		if(is_bool($value)){
			return new CollisionBoxComponent($value);
		}
		[$origin, $size] = self::boxVectors($value);
		return new CollisionBoxComponent(true, $origin, $size);
	}

	/**
	 * @throws BehaviorPackException
	 */
	private static function selectionBox(mixed $value) : SelectionBoxComponent{
		if(is_bool($value)){
			return new SelectionBoxComponent($value);
		}
		[$origin, $size] = self::boxVectors($value);
		return new SelectionBoxComponent(true, $origin, $size);
	}

	/**
	 * @throws BehaviorPackException
	 */
	private static function flammable(mixed $value) : FlammableComponent{
		$flammability = self::flammability($value);
		if($flammability === null){
			return new FlammableComponent(0, 0);
		}
		return new FlammableComponent($flammability[0], $flammability[1]);
	}

	/**
	 * @throws BehaviorPackException
	 */
	private static function transformation(mixed $value) : TransformationComponent{
		if(!is_array($value)){
			throw new BehaviorPackException("minecraft:transformation must be an object");
		}
		return new TransformationComponent(
			self::triple($value["rotation"] ?? null, 0.0),
			self::triple($value["translation"] ?? null, 0.0),
			self::triple($value["scale"] ?? null, 1.0)
		);
	}

	/**
	 * @return array{Vector3, Vector3}
	 * @throws BehaviorPackException
	 */
	private static function boxVectors(mixed $value) : array{
		if(!is_array($value)){
			throw new BehaviorPackException("A block box must be a boolean or an object");
		}
		if(\array_is_list($value) && count($value) > 0 && is_array($value[0])){
			$min = null;
			$max = null;
			foreach($value as $box){
				[$boxOrigin, $boxSize] = self::boxVectors($box);
				$boxMax = $boxOrigin->addVector($boxSize);
				$min = $min === null ? $boxOrigin : new Vector3(min($min->x, $boxOrigin->x), min($min->y, $boxOrigin->y), min($min->z, $boxOrigin->z));
				$max = $max === null ? $boxMax : new Vector3(max($max->x, $boxMax->x), max($max->y, $boxMax->y), max($max->z, $boxMax->z));
			}
			return [$min, $max->subtractVector($min)];
		}
		$origin = self::triple($value["origin"] ?? [-8, 0, -8], 0.0);
		$size = self::triple($value["size"] ?? [16, 16, 16], 16.0);
		return [new Vector3($origin[0], $origin[1], $origin[2]), new Vector3($size[0], $size[1], $size[2])];
	}

	/**
	 * @return array{float, float, float}
	 * @throws BehaviorPackException
	 */
	private static function triple(mixed $value, float $default) : array{
		if($value === null){
			return [$default, $default, $default];
		}
		if(!is_array($value) || count($value) !== 3){
			throw new BehaviorPackException("Expected an array of 3 numbers");
		}
		return [
			self::number($value[0] ?? null, "vector"),
			self::number($value[1] ?? null, "vector"),
			self::number($value[2] ?? null, "vector")
		];
	}

	private static function field(mixed $value, string $key, mixed $default) : mixed{
		if(is_array($value)){
			return $value[$key] ?? $default;
		}
		return $value ?? $default;
	}

	/**
	 * @throws BehaviorPackException
	 */
	private static function number(mixed $value, string $what) : float{
		if(!is_int($value) && !is_float($value)){
			throw new BehaviorPackException("Expected a number for $what");
		}
		return (float) $value;
	}
}
