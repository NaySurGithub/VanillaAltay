<?php

declare(strict_types=1);

namespace behaviorpack\custom;

use behaviorpack\BehaviorPack;
use behaviorpack\BehaviorPackException;
use customiesdevs\customies\block\CustomiesBlockFactory;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\item\StringToItemParser;
use pocketmine\plugin\PluginBase;
use function array_keys;
use function array_merge;
use function array_unique;
use function array_values;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function range;
use function sort;
use function str_contains;
use const SORT_REGULAR;

/**
 * Registers the minecraft:block files of the behavior packs through
 * Customies.
 *
 * @phpstan-import-type Definition from BehaviorBlock
 */
final class CustomBlockRegistrar{

	public const MAX_STATES = 65536;

	private const DIRECTIONS = ["north", "south", "west", "east"];
	private const FACES = ["down", "up", "north", "south", "west", "east"];

	public function __construct(
		private PluginBase $plugin
	){
	}

	/**
	 * Reads the identifier of a block file without registering it, or null
	 * when the file is not a valid block definition.
	 */
	public function readIdentifier(string $file) : ?string{
		try{
			$identifier = BehaviorPack::readJson($file)["minecraft:block"]["description"]["identifier"] ?? null;
		}catch(BehaviorPackException){
			return null;
		}
		return is_string($identifier) ? $identifier : null;
	}

	/**
	 * Registers one block file, returns whether a block was registered.
	 */
	public function register(BehaviorPack $pack, string $file) : bool{
		$path = $pack->getName() . "/" . $pack->relativePath($file);
		try{
			return $this->registerFile($file, $path);
		}catch(BehaviorPackException $e){
			$this->plugin->getLogger()->warning("Behavior packs: skipped block $path: " . $e->getMessage());
			return false;
		}
	}

	/**
	 * @throws BehaviorPackException
	 */
	private function registerFile(string $file, string $path) : bool{
		$block = BehaviorPack::readJson($file)["minecraft:block"] ?? null;
		if(!is_array($block)){
			throw new BehaviorPackException("no minecraft:block object");
		}
		$description = $block["description"] ?? null;
		$identifier = is_array($description) ? ($description["identifier"] ?? null) : null;
		if(!is_array($description) || !is_string($identifier) || !str_contains($identifier, ":")){
			throw new BehaviorPackException("missing or invalid description.identifier");
		}
		if(StringToItemParser::getInstance()->parse($identifier) !== null){
			throw new BehaviorPackException("$identifier is already registered");
		}

		$components = $block["components"] ?? [];
		if(!is_array($components)){
			throw new BehaviorPackException("components must be an object");
		}
		$unsupported = $this->validateComponents($components);

		$properties = $this->readStates($description["states"] ?? $description["properties"] ?? []);
		$traits = $this->readTraits($description["traits"] ?? [], $properties);

		$permutations = [];
		$rawPermutations = $block["permutations"] ?? [];
		if(!is_array($rawPermutations)){
			throw new BehaviorPackException("permutations must be an array");
		}
		if(count($rawPermutations) > 0 && count($properties) === 0){
			$this->plugin->getLogger()->warning("Behavior packs: block $identifier has permutations but no states, permutations ignored ($path)");
		}elseif(count($rawPermutations) > 0){
			foreach($rawPermutations as $permutation){
				$condition = is_array($permutation) ? ($permutation["condition"] ?? null) : null;
				$permutationComponents = is_array($permutation) ? ($permutation["components"] ?? []) : null;
				if(!is_string($condition) || !is_array($permutationComponents)){
					throw new BehaviorPackException("a permutation needs a condition and components");
				}
				$unsupported = array_merge($unsupported, $this->validateComponents($permutationComponents));
				$permutations[] = [$condition, $permutationComponents];
			}
		}

		$stateCount = 1;
		foreach($properties as [, $values]){
			$stateCount *= count($values);
		}
		if($stateCount > self::MAX_STATES){
			throw new BehaviorPackException("$stateCount state combinations, the limit is " . self::MAX_STATES);
		}

		$flammability = isset($components["minecraft:flammable"]) ? BlockComponentMapper::flammability($components["minecraft:flammable"]) : null;
		$collision = isset($components["minecraft:collision_box"]) ? BlockComponentMapper::box($components["minecraft:collision_box"]) : [0.0, 0.0, 0.0, 1.0, 1.0, 1.0];
		if($collision !== null && ($collision[3] < $collision[0] || $collision[4] < $collision[1] || $collision[5] < $collision[2])){
			throw new BehaviorPackException("minecraft:collision_box has a negative size");
		}

		/** @phpstan-var Definition $definition */
		$definition = [
			"identifier" => $identifier,
			"name" => isset($components["minecraft:display_name"]) ? BlockComponentMapper::displayName($components["minecraft:display_name"]) : $identifier,
			"hardness" => isset($components["minecraft:destructible_by_mining"]) ? BlockComponentMapper::hardness($components["minecraft:destructible_by_mining"]) : 0.0,
			"blastResistance" => isset($components["minecraft:destructible_by_explosion"]) ? BlockComponentMapper::explosionResistance($components["minecraft:destructible_by_explosion"]) : 0.0,
			"lightLevel" => isset($components["minecraft:light_emission"]) ? BlockComponentMapper::lightLevel($components["minecraft:light_emission"], 0) : 0,
			"lightFilter" => isset($components["minecraft:light_dampening"]) ? BlockComponentMapper::lightLevel($components["minecraft:light_dampening"], 15) : 15,
			"frictionFactor" => 1.0 - (isset($components["minecraft:friction"]) ? BlockComponentMapper::friction($components["minecraft:friction"]) : 0.4),
			"flammability" => $flammability,
			"collision" => $collision,
			"components" => $components,
			"properties" => $properties,
			"permutations" => $permutations,
			"traits" => $traits
		];

		$unsupported = array_values(array_unique($unsupported));
		if(count($unsupported) > 0){
			$this->plugin->getLogger()->warning("Behavior packs: block $identifier: unsupported components ignored: " . implode(", ", $unsupported));
		}

		$typeId = BlockTypeIds::newId();
		CustomiesBlockFactory::getInstance()->registerBlock(
			static fn() : Block => BehaviorBlock::create($typeId, $definition),
			$identifier,
			CustomContentLoader::creativeInfo($description)
		);
		return true;
	}

	/**
	 * Checks every supported component and returns the names of the
	 * unsupported ones.
	 *
	 * @param array<mixed> $components
	 * @return list<string>
	 * @throws BehaviorPackException
	 */
	private function validateComponents(array $components) : array{
		$unsupported = [];
		foreach($components as $name => $value){
			$name = (string) $name;
			if(in_array($name, BlockComponentMapper::IGNORED, true)){
				continue;
			}
			if(!BlockComponentMapper::isSupported($name)){
				$unsupported[] = $name;
				continue;
			}
			try{
				BlockComponentMapper::map($name, $value);
			}catch(BehaviorPackException $e){
				throw new BehaviorPackException("$name: " . $e->getMessage(), 0, $e);
			}
		}
		return $unsupported;
	}

	/**
	 * @return list<array{string, list<bool|int|string>}>
	 * @throws BehaviorPackException
	 */
	private function readStates(mixed $states) : array{
		if(!is_array($states)){
			throw new BehaviorPackException("description.states must be an object");
		}
		$properties = [];
		foreach($states as $name => $definition){
			$name = (string) $name;
			if(is_array($definition) && is_array($definition["values"] ?? null)){
				$min = $definition["values"]["min"] ?? null;
				$max = $definition["values"]["max"] ?? null;
				if(!is_int($min) || !is_int($max) || $max < $min){
					throw new BehaviorPackException("state $name has an invalid range");
				}
				$values = range($min, $max);
			}elseif(is_array($definition)){
				$values = array_values($definition);
			}else{
				throw new BehaviorPackException("state $name must be a list or a range");
			}
			$properties[] = [$name, $this->normalizeValues($name, $values)];
		}
		return $properties;
	}

	/**
	 * Sorts and deduplicates the values of a state, and puts its only falsy
	 * value first: Customies walks the values with next(), which stops on a
	 * falsy value.
	 *
	 * @param list<mixed> $values
	 * @return list<bool|int|string>
	 * @throws BehaviorPackException
	 */
	private function normalizeValues(string $name, array $values) : array{
		if(count($values) === 0){
			throw new BehaviorPackException("state $name has no values");
		}
		$first = $values[0];
		foreach($values as $value){
			$sameType = (is_bool($first) && is_bool($value)) || (is_int($first) && is_int($value)) || (is_string($first) && is_string($value));
			if(!$sameType){
				throw new BehaviorPackException("state $name mixes value types or uses unsupported values");
			}
		}
		$values = array_values(array_unique($values, SORT_REGULAR));
		sort($values);
		$falsy = [];
		$truthy = [];
		foreach($values as $value){
			if($value){
				$truthy[] = $value;
			}else{
				$falsy[] = $value;
			}
		}
		if(count($falsy) > 1){
			throw new BehaviorPackException("state $name cannot have both \"\" and \"0\" values");
		}
		return array_merge($falsy, $truthy);
	}

	/**
	 * Adds the states of the supported placement traits to the properties.
	 *
	 * @param list<array{string, list<bool|int|string>}> $properties
	 * @return array{cardinal: bool, yRotationOffset: int, facing: bool, blockFace: bool, verticalHalf: bool}
	 * @throws BehaviorPackException
	 */
	private function readTraits(mixed $traits, array &$properties) : array{
		$result = ["cardinal" => false, "yRotationOffset" => 0, "facing" => false, "blockFace" => false, "verticalHalf" => false];
		if(!is_array($traits)){
			throw new BehaviorPackException("description.traits must be an object");
		}
		$names = [];
		foreach($properties as [$name]){
			$names[$name] = true;
		}
		$add = static function(string $name, array $values) use (&$properties, &$names) : bool{
			if(isset($names[$name])){
				return false;
			}
			$names[$name] = true;
			$properties[] = [$name, $values];
			return true;
		};

		foreach(array_keys($traits) as $trait){
			$config = $traits[$trait];
			$enabled = is_array($config) && is_array($config["enabled_states"] ?? null) ? $config["enabled_states"] : [];
			if($trait === "minecraft:placement_direction"){
				if(in_array(BehaviorPermutableBlock::CARDINAL_DIRECTION, $enabled, true)){
					$result["cardinal"] = $add(BehaviorPermutableBlock::CARDINAL_DIRECTION, self::DIRECTIONS);
				}
				if(in_array(BehaviorPermutableBlock::FACING_DIRECTION, $enabled, true)){
					$result["facing"] = $add(BehaviorPermutableBlock::FACING_DIRECTION, self::FACES);
				}
				$offset = is_array($config) ? ($config["y_rotation_offset"] ?? 0) : 0;
				$result["yRotationOffset"] = is_int($offset) || is_float($offset) ? (int) $offset : 0;
			}elseif($trait === "minecraft:placement_position"){
				if(in_array(BehaviorPermutableBlock::BLOCK_FACE, $enabled, true)){
					$result["blockFace"] = $add(BehaviorPermutableBlock::BLOCK_FACE, self::FACES);
				}
				if(in_array(BehaviorPermutableBlock::VERTICAL_HALF, $enabled, true)){
					$result["verticalHalf"] = $add(BehaviorPermutableBlock::VERTICAL_HALF, ["bottom", "top"]);
				}
			}else{
				throw new BehaviorPackException("unsupported trait $trait");
			}
		}
		return $result;
	}
}
