<?php

declare(strict_types=1);

namespace behaviorpack\spawn;

use behaviorpack\entity\behavior\FilterEvaluator;
use Closure;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function array_is_list;
use function floor;
use function in_array;
use function is_array;
use function is_string;
use function strtolower;
use function substr;
use function strpos;

/**
 * Evaluates the "minecraft:biome_filter" of a spawn condition at a position,
 * with the biome tags the entity filters use.
 */
final class BiomeFilter{

	private static ?Closure $tags = null;

	/**
	 * @param array<mixed> $filter
	 */
	public static function test(array $filter, World $world, Vector3 $position) : bool{
		$tags = self::tags($world, $position);
		return self::evaluate($filter, $world, $position, $tags);
	}

	/**
	 * @return list<string>
	 */
	public static function tags(World $world, Vector3 $position) : array{
		if(self::$tags === null){
			self::$tags = Closure::bind(static function(World $world, Vector3 $position) : array{
				return self::biomeTags($world, $position);
			}, null, FilterEvaluator::class);
		}
		return (self::$tags)($world, $position);
	}

	/**
	 * @param array<mixed> $filter
	 * @param list<string> $tags
	 */
	private static function evaluate(array $filter, World $world, Vector3 $position, array $tags) : bool{
		if(array_is_list($filter)){
			foreach($filter as $entry){
				if(is_array($entry) && !self::evaluate($entry, $world, $position, $tags)){
					return false;
				}
			}
			return true;
		}
		foreach(["all_of" => true, "any_of" => false, "none_of" => null, "AND" => true, "OR" => false] as $group => $mode){
			if(!is_array($filter[$group] ?? null)){
				continue;
			}
			foreach($filter[$group] as $entry){
				if(!is_array($entry)){
					continue;
				}
				$result = self::evaluate($entry, $world, $position, $tags);
				if($mode === true && !$result){
					return false;
				}
				if($mode === false && $result){
					return true;
				}
				if($mode === null && $result){
					return false;
				}
			}
			return $mode !== false;
		}
		$test = $filter["test"] ?? null;
		$value = $filter["value"] ?? true;
		$result = match($test){
			"has_biome_tag", "is_biome" => is_string($value) && in_array(self::stripNamespace(strtolower($value)), $tags, true),
			"is_snow_covered" => $world->getBiome((int) floor($position->x), (int) floor($position->y), (int) floor($position->z))->getTemperature() < 0.15,
			"is_underground" => $world->getHighestBlockAt((int) floor($position->x), (int) floor($position->z)) > (int) floor($position->y),
			default => null
		};
		if($result === null){
			return false;
		}
		if($test === "is_snow_covered" || $test === "is_underground"){
			$result = $result === ($value !== false);
		}
		$operator = $filter["operator"] ?? "==";
		return match($operator){
			"!=", "<>", "not" => !$result,
			default => $result
		};
	}

	private static function stripNamespace(string $value) : string{
		$index = strpos($value, ":");
		return $index === false ? $value : substr($value, $index + 1);
	}
}
