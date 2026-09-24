<?php

declare(strict_types=1);

namespace behaviorpack\recipe;

use pocketmine\crafting\CraftingManagerFromDataHelper;
use pocketmine\crafting\ExactRecipeIngredient;
use pocketmine\crafting\MetaWildcardRecipeIngredient;
use pocketmine\crafting\RecipeIngredient;
use pocketmine\crafting\TagWildcardRecipeIngredient;
use pocketmine\data\bedrock\PotionTypeIdMap;
use pocketmine\item\Item;
use pocketmine\item\PotionType;
use function count;
use function ctype_digit;
use function explode;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use function max;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function strtoupper;

/**
 * Turns the item descriptors of Bedrock recipe files into items and recipe
 * ingredients, using the same lookup the server uses for its own recipes, so
 * vanilla and custom identifiers resolve alike.
 */
final class RecipeItemResolver{

	private const WILDCARD_META = 32767;
	private const POTION_TYPE_PREFIX = "minecraft:potion_type:";

	/**
	 * Resolves an ingredient descriptor: a string ("minecraft:stick",
	 * "minecraft:wool:3") or an object with item/data or tag. Returns the
	 * ingredient and how many times it is required.
	 *
	 * @return array{RecipeIngredient, int}
	 * @throws RecipeException
	 * @throws UnknownItemException
	 */
	public static function ingredient(mixed $descriptor) : array{
		if(is_string($descriptor)){
			return [self::itemIngredient($descriptor, null), 1];
		}
		if(!is_array($descriptor)){
			throw new RecipeException("Ingredient must be a string or an object");
		}

		$count = self::count($descriptor);
		$tag = $descriptor["tag"] ?? null;
		if($tag !== null){
			if(!is_string($tag) || $tag === ""){
				throw new RecipeException("Ingredient tag must be a string");
			}
			return [new TagWildcardRecipeIngredient(self::namespaced($tag)), $count];
		}

		$item = $descriptor["item"] ?? null;
		if(!is_string($item) || $item === ""){
			throw new RecipeException("Ingredient must have an item or a tag");
		}
		return [self::itemIngredient($item, self::data($descriptor)), $count];
	}

	/**
	 * Resolves a result descriptor: a string or an object with item, data
	 * and count.
	 *
	 * @throws RecipeException
	 * @throws UnknownItemException
	 */
	public static function result(mixed $descriptor) : Item{
		if(is_string($descriptor)){
			[$identifier, $meta] = self::splitIdentifier($descriptor);
			return self::item($identifier, $meta ?? 0, 1);
		}
		if(!is_array($descriptor)){
			throw new RecipeException("Result must be a string or an object");
		}

		$item = $descriptor["item"] ?? null;
		if(!is_string($item) || $item === ""){
			throw new RecipeException("Result must have an item");
		}
		[$identifier, $meta] = self::splitIdentifier($item);
		$data = self::data($descriptor);
		if($data !== null){
			$meta = $data;
		}
		if($meta === self::WILDCARD_META){
			$meta = 0;
		}
		return self::item($identifier, $meta ?? 0, self::count($descriptor));
	}

	/**
	 * Resolves a list of results, accepting a single descriptor too.
	 *
	 * @return list<Item>
	 * @throws RecipeException
	 * @throws UnknownItemException
	 */
	public static function results(mixed $descriptor) : array{
		if(is_array($descriptor) && isset($descriptor[0])){
			$results = [];
			foreach($descriptor as $entry){
				$results[] = self::result($entry);
			}
			return $results;
		}
		return [self::result($descriptor)];
	}

	/**
	 * Resolves an identifier of the form "minecraft:potion_type:<name>" to
	 * the matching potion type.
	 *
	 * @throws RecipeException
	 */
	public static function potionType(string $identifier) : PotionType{
		if(!self::isPotionType($identifier)){
			throw new RecipeException("Expected a potion type, got \"$identifier\"");
		}
		$name = strtoupper(substr($identifier, strlen(self::POTION_TYPE_PREFIX)));
		foreach(PotionType::cases() as $case){
			if($case->name === $name){
				return $case;
			}
		}
		throw new RecipeException("Unknown potion type \"$identifier\"");
	}

	public static function isPotionType(string $identifier) : bool{
		return str_starts_with(strtolower($identifier), self::POTION_TYPE_PREFIX);
	}

	/**
	 * Builds a potion container item ("minecraft:potion", ...) of the given
	 * potion type.
	 *
	 * @throws UnknownItemException
	 */
	public static function potion(string $containerId, PotionType $type) : Item{
		return self::item($containerId, PotionTypeIdMap::getInstance()->toId($type), 1);
	}

	/**
	 * Checks that an identifier resolves to a known item and returns it in
	 * its namespaced form.
	 *
	 * @throws UnknownItemException
	 */
	public static function knownIdentifier(string $identifier) : string{
		[$identifier,] = self::splitIdentifier($identifier);
		self::item($identifier, 0, 1);
		return $identifier;
	}

	/**
	 * @throws UnknownItemException
	 */
	public static function item(string $identifier, int $meta, int $count) : Item{
		$item = CraftingManagerFromDataHelper::deserializeItemStackFromFields($identifier, $meta, $count, null, null);
		if($item === null){
			throw new UnknownItemException($meta === 0 ? $identifier : "$identifier:$meta");
		}
		return $item;
	}

	/**
	 * @throws UnknownItemException
	 */
	private static function itemIngredient(string $descriptor, ?int $data) : RecipeIngredient{
		[$identifier, $meta] = self::splitIdentifier($descriptor);
		if($data !== null){
			$meta = $data;
		}
		if($meta === null || $meta === -1 || $meta === self::WILDCARD_META){
			self::item($identifier, 0, 1);
			return new MetaWildcardRecipeIngredient($identifier);
		}
		return new ExactRecipeIngredient(self::item($identifier, $meta, 1));
	}

	/**
	 * Splits "namespace:id" or "namespace:id:data" into the namespaced
	 * identifier and the optional data value.
	 *
	 * @return array{string, ?int}
	 */
	private static function splitIdentifier(string $descriptor) : array{
		$parts = explode(":", $descriptor);
		$meta = null;
		$last = count($parts) - 1;
		if($last >= 1 && ctype_digit($parts[$last])){
			$meta = (int) $parts[$last];
			unset($parts[$last]);
		}
		return [self::namespaced(implode(":", $parts)), $meta];
	}

	private static function namespaced(string $identifier) : string{
		return str_contains($identifier, ":") ? $identifier : "minecraft:" . $identifier;
	}

	/**
	 * @param array<mixed> $descriptor
	 * @throws RecipeException
	 */
	private static function data(array $descriptor) : ?int{
		$data = $descriptor["data"] ?? null;
		if($data === null){
			return null;
		}
		if(!is_int($data)){
			throw new RecipeException("Item data must be an integer");
		}
		return $data;
	}

	/**
	 * @param array<mixed> $descriptor
	 * @throws RecipeException
	 */
	private static function count(array $descriptor) : int{
		$count = $descriptor["count"] ?? 1;
		if(!is_int($count)){
			throw new RecipeException("Item count must be an integer");
		}
		return max(1, $count);
	}
}
