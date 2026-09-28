<?php

declare(strict_types=1);

namespace behaviorpack\custom\block;

use behaviorpack\BehaviorPackException;
use behaviorpack\custom\BlockComponentMapper;
use pocketmine\nbt\tag\CompoundTag;
use function hexdec;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function ltrim;
use function max;
use function min;
use function preg_match;
use function strtolower;

/**
 * Builds the network value of the block components that carry plain data,
 * and reads the server-side values they imply.
 */
final class ExtraBlockComponents{

	public const MOVEMENT_PUSH_PULL = "push_pull";
	public const MOVEMENT_PUSH = "push";
	public const MOVEMENT_IMMOVABLE = "immovable";
	public const MOVEMENT_POPPED = "popped";

	public const SUPPORT_FENCE = "fence";
	public const SUPPORT_STAIR = "stair";

	private const MOVEMENT_TYPES = [self::MOVEMENT_PUSH_PULL, self::MOVEMENT_PUSH, self::MOVEMENT_IMMOVABLE, self::MOVEMENT_POPPED];
	private const STICKY_TYPES = ["none", "same"];
	private const PRECIPITATION_BEHAVIORS = ["obstruct_rain_accumulate_snow", "obstruct_rain", "none"];
	private const SUPPORT_SHAPES = [self::SUPPORT_FENCE, self::SUPPORT_STAIR];
	private const TINT_METHODS = ["none", "default_foliage", "birch_foliage", "evergreen_foliage", "dry_foliage", "grass", "water"];

	private function __construct(){
	}

	/**
	 * @throws BehaviorPackException
	 */
	public static function mapColor(mixed $value) : TagComponent{
		$color = is_array($value) ? ($value["color"] ?? null) : $value;
		$tint = is_array($value) ? ($value["tint_method"] ?? "none") : "none";
		if(!is_string($tint) || !in_array($tint, self::TINT_METHODS, true)){
			throw new BehaviorPackException("invalid tint_method");
		}
		return new TagComponent("minecraft:map_color", CompoundTag::create()
			->setInt("color", self::argb($color))
			->setString("tint_method", $tint));
	}

	/**
	 * @throws BehaviorPackException
	 */
	public static function destructionParticles(mixed $value) : TagComponent{
		if(!is_array($value)){
			throw new BehaviorPackException("minecraft:destruction_particles must be an object");
		}
		$texture = $value["texture"] ?? "";
		$tint = $value["tint_method"] ?? "none";
		$count = $value["particle_count"] ?? 100;
		if(!is_string($texture) || !is_string($tint) || !in_array($tint, self::TINT_METHODS, true) || !is_int($count)){
			throw new BehaviorPackException("invalid texture, tint_method or particle_count");
		}
		return new TagComponent("minecraft:destruction_particles", CompoundTag::create()
			->setString("texture", $texture)
			->setString("tint_method", $tint)
			->setInt("particle_count", max(0, min(255, $count))));
	}

	/**
	 * @return array{bool, bool} redstone_conductor and allows_wire_to_step_down
	 * @throws BehaviorPackException
	 */
	public static function redstoneConductivity(mixed $value) : array{
		if(!is_array($value)){
			throw new BehaviorPackException("minecraft:redstone_conductivity must be an object");
		}
		$conductor = $value["redstone_conductor"] ?? false;
		$stepDown = $value["allows_wire_to_step_down"] ?? true;
		if(!is_bool($conductor) || !is_bool($stepDown)){
			throw new BehaviorPackException("redstone_conductor and allows_wire_to_step_down must be booleans");
		}
		return [$conductor, $stepDown];
	}

	/**
	 * @throws BehaviorPackException
	 */
	public static function redstoneConductivityComponent(mixed $value) : TagComponent{
		[$conductor, $stepDown] = self::redstoneConductivity($value);
		return new TagComponent("minecraft:redstone_conductivity", CompoundTag::create()
			->setByte("redstone_conductor", $conductor ? 1 : 0)
			->setByte("allows_wire_to_step_down", $stepDown ? 1 : 0));
	}

	/**
	 * @return array{string, bool} the movement type and whether the block is sticky
	 * @throws BehaviorPackException
	 */
	public static function movable(mixed $value) : array{
		if(!is_array($value)){
			throw new BehaviorPackException("minecraft:movable must be an object");
		}
		$type = $value["movement_type"] ?? self::MOVEMENT_PUSH_PULL;
		$sticky = $value["sticky"] ?? "none";
		if(!is_string($type) || !in_array($type, self::MOVEMENT_TYPES, true)){
			throw new BehaviorPackException("invalid movement_type");
		}
		if(!is_string($sticky) || !in_array($sticky, self::STICKY_TYPES, true)){
			throw new BehaviorPackException("invalid sticky");
		}
		if($sticky === "same" && $type !== self::MOVEMENT_PUSH_PULL){
			throw new BehaviorPackException("sticky \"same\" needs the push_pull movement type");
		}
		return [$type, $sticky === "same"];
	}

	/**
	 * @throws BehaviorPackException
	 */
	public static function movableComponent(mixed $value) : TagComponent{
		[$type, $sticky] = self::movable($value);
		return new TagComponent("minecraft:movable", CompoundTag::create()
			->setString("movement_type", $type)
			->setString("sticky", $sticky ? "same" : "none"));
	}

	/**
	 * @throws BehaviorPackException
	 */
	public static function precipitationInteractions(mixed $value) : TagComponent{
		$behavior = is_array($value) ? ($value["precipitation_behavior"] ?? null) : null;
		if(!is_string($behavior) || !in_array($behavior, self::PRECIPITATION_BEHAVIORS, true)){
			throw new BehaviorPackException("invalid precipitation_behavior");
		}
		return new TagComponent("minecraft:precipitation_interactions", CompoundTag::create()
			->setString("precipitation_behavior", $behavior));
	}

	/**
	 * @throws BehaviorPackException
	 */
	public static function supportShape(mixed $value) : string{
		$shape = is_array($value) ? ($value["shape"] ?? null) : null;
		if(!is_string($shape) || !in_array($shape, self::SUPPORT_SHAPES, true)){
			throw new BehaviorPackException("minecraft:support shape must be fence or stair");
		}
		return $shape;
	}

	/**
	 * @throws BehaviorPackException
	 */
	public static function support(mixed $value) : TagComponent{
		return new TagComponent("minecraft:support", CompoundTag::create()->setString("shape", self::supportShape($value)));
	}

	/**
	 * @throws BehaviorPackException
	 */
	public static function marker(string $name, mixed $value) : TagComponent{
		if($value !== true && !is_array($value)){
			throw new BehaviorPackException("$name must be an object");
		}
		return new TagComponent($name, CompoundTag::create());
	}

	/**
	 * @throws BehaviorPackException
	 */
	public static function randomOffset(mixed $value) : TagComponent{
		if(!is_array($value)){
			throw new BehaviorPackException("minecraft:random_offset must be an object");
		}
		$tag = CompoundTag::create();
		foreach(["x", "y", "z"] as $axis){
			$config = $value[$axis] ?? [];
			if(!is_array($config)){
				throw new BehaviorPackException("random_offset.$axis must be an object");
			}
			$steps = $config["steps"] ?? 0;
			$range = $config["range"] ?? [];
			if(!is_int($steps) || $steps < 0 || !is_array($range)){
				throw new BehaviorPackException("random_offset.$axis needs integer steps and a range");
			}
			$rangeMin = $range["min"] ?? 0;
			$rangeMax = $range["max"] ?? 0;
			if((!is_int($rangeMin) && !is_float($rangeMin)) || (!is_int($rangeMax) && !is_float($rangeMax))){
				throw new BehaviorPackException("random_offset.$axis.range needs numeric min and max");
			}
			$tag->setTag($axis, CompoundTag::create()
				->setInt("steps", $steps)
				->setTag("range", CompoundTag::create()
					->setFloat("min", max(-8.0, min(8.0, (float) $rangeMin)))
					->setFloat("max", max(-8.0, min(8.0, (float) $rangeMax)))));
		}
		return new TagComponent("minecraft:random_offset", $tag);
	}

	/**
	 * Builds minecraft:item_visual or minecraft:embedded_visual from a
	 * geometry and material_instances pair.
	 *
	 * @throws BehaviorPackException
	 */
	public static function visual(string $name, mixed $value) : TagComponent{
		if(!is_array($value) || !isset($value["geometry"], $value["material_instances"])){
			throw new BehaviorPackException("$name needs geometry and material_instances");
		}
		$geometry = BlockComponentMapper::map("minecraft:geometry", $value["geometry"]);
		$materials = BlockComponentMapper::map("minecraft:material_instances", $value["material_instances"]);
		if($geometry === null || $materials === null){
			throw new BehaviorPackException("$name has an invalid geometry or material_instances");
		}
		return new TagComponent($name, CompoundTag::create()
			->setTag("geometry", $geometry->getValue())
			->setTag("material_instances", $materials->getValue()));
	}

	/**
	 * @throws BehaviorPackException
	 */
	private static function argb(mixed $color) : int{
		if(is_array($color) && isset($color[0], $color[1], $color[2])){
			$rgb = 0;
			foreach([$color[0], $color[1], $color[2]] as $channel){
				if(!is_int($channel)){
					throw new BehaviorPackException("map_color channels must be integers");
				}
				$rgb = ($rgb << 8) | max(0, min(255, $channel));
			}
			return self::signed((0xff << 24) | $rgb);
		}
		if(!is_string($color) || preg_match("/^#?[0-9a-fA-F]{6}$/", $color) !== 1){
			throw new BehaviorPackException("map_color must be a #rrggbb string");
		}
		return self::signed((0xff << 24) | (int) hexdec(strtolower(ltrim($color, "#"))));
	}

	private static function signed(int $argb) : int{
		return $argb >= 0x80000000 ? $argb - 0x100000000 : $argb;
	}
}
