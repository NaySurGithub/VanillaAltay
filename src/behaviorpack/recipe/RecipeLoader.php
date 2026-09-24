<?php

declare(strict_types=1);

namespace behaviorpack\recipe;

use behaviorpack\BehaviorPack;
use behaviorpack\BehaviorPackException;
use behaviorpack\ContentLoader;
use pocketmine\crafting\CraftingManager;
use pocketmine\crafting\FurnaceRecipe;
use pocketmine\crafting\FurnaceType;
use pocketmine\crafting\PotionContainerChangeRecipe;
use pocketmine\crafting\PotionTypeRecipe;
use pocketmine\crafting\ShapedRecipe;
use pocketmine\crafting\ShapelessRecipe;
use pocketmine\crafting\ShapelessRecipeType;
use pocketmine\crafting\ExactRecipeIngredient;
use pocketmine\plugin\PluginBase;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function max;
use function str_contains;
use function str_pad;
use function strlen;
use function strtolower;

/**
 * Registers the recipes of the "recipes" folder of every behavior pack into
 * the server crafting manager. The crafting data sent to players is rebuilt
 * by the server when a recipe is registered.
 */
final class RecipeLoader implements ContentLoader{

	private const POTION_CONTAINERS = ["minecraft:potion", "minecraft:splash_potion", "minecraft:lingering_potion"];

	private int $registered = 0;

	/** @var array<string, true> */
	private array $warnedItems = [];

	public function __construct(
		private PluginBase $plugin
	){}

	public function getName() : string{
		return "recipes";
	}

	public function requiresCustomies() : bool{
		return false;
	}

	public function load(array $packs) : void{
		$manager = $this->plugin->getServer()->getCraftingManager();
		foreach($packs as $pack){
			foreach($pack->listFiles("recipes", "json") as $file){
				$this->loadFile($manager, $pack, $file);
			}
		}
		if($this->registered > 0){
			$this->plugin->getLogger()->info("Behavior packs: " . $this->registered . " recipes");
		}
	}

	public function close() : void{
		$this->warnedItems = [];
	}

	private function loadFile(CraftingManager $manager, BehaviorPack $pack, string $file) : void{
		$location = $pack->getName() . "/" . $pack->relativePath($file);
		try{
			$json = BehaviorPack::readJson($file);
		}catch(BehaviorPackException $e){
			$this->plugin->getLogger()->warning("Skipped recipe $location: " . $e->getMessage());
			return;
		}

		foreach($json as $key => $body){
			if(!is_string($key) || !is_array($body) || $key === "format_version"){
				continue;
			}
			try{
				$this->registered += $this->loadRecipe($manager, strtolower($key), $body);
			}catch(UnknownItemException $e){
				$warnKey = $location . "|" . $e->getIdentifier();
				if(!isset($this->warnedItems[$warnKey])){
					$this->warnedItems[$warnKey] = true;
					$this->plugin->getLogger()->warning("Skipped recipe $location: unknown item \"" . $e->getIdentifier() . "\"");
				}
			}catch(RecipeException | \InvalidArgumentException $e){
				$this->plugin->getLogger()->warning("Skipped recipe $location: " . $e->getMessage());
			}
		}
	}

	/**
	 * Registers one recipe and returns how many server recipes it produced.
	 *
	 * @param array<mixed> $body
	 * @throws RecipeException
	 * @throws UnknownItemException
	 */
	private function loadRecipe(CraftingManager $manager, string $type, array $body) : int{
		return match($type){
			"minecraft:recipe_shaped" => $this->loadShaped($manager, $body),
			"minecraft:recipe_shapeless" => $this->loadShapeless($manager, $body),
			"minecraft:recipe_furnace" => $this->loadFurnace($manager, $body),
			"minecraft:recipe_brewing_mix" => $this->loadBrewingMix($manager, $body),
			"minecraft:recipe_brewing_container" => $this->loadBrewingContainer($manager, $body),
			"minecraft:recipe_smithing_transform", "minecraft:recipe_smithing_trim" => throw new RecipeException("Smithing recipes are not supported by the server crafting system"),
			default => throw new RecipeException("Unsupported recipe type \"$type\"")
		};
	}

	/**
	 * @param array<mixed> $body
	 * @return list<string>
	 */
	private function tags(array $body) : array{
		$tags = [];
		foreach(is_array($body["tags"] ?? null) ? $body["tags"] : [] as $tag){
			if(is_string($tag)){
				$tags[] = strtolower($tag);
			}
		}
		return $tags;
	}

	/**
	 * @param array<mixed> $body
	 * @throws RecipeException
	 * @throws UnknownItemException
	 */
	private function loadShaped(CraftingManager $manager, array $body) : int{
		$tags = $this->tags($body);
		if(count($tags) > 0 && !in_array("crafting_table", $tags, true)){
			throw new RecipeException("Shaped recipes are only supported on the crafting table (tags: " . implode(", ", $tags) . ")");
		}

		$pattern = $body["pattern"] ?? null;
		if(!is_array($pattern) || count($pattern) === 0){
			throw new RecipeException("Shaped recipe must have a pattern");
		}
		$shape = [];
		$width = 0;
		foreach($pattern as $row){
			if(!is_string($row)){
				throw new RecipeException("Shaped recipe pattern rows must be strings");
			}
			$shape[] = $row;
			$width = max($width, strlen($row));
		}
		foreach($shape as $i => $row){
			$shape[$i] = str_pad($row, $width);
		}

		$key = $body["key"] ?? [];
		if(!is_array($key)){
			throw new RecipeException("Shaped recipe key must be an object");
		}
		$ingredients = [];
		foreach($key as $symbol => $descriptor){
			$symbol = (string) $symbol;
			if(strlen($symbol) !== 1){
				throw new RecipeException("Shaped recipe key symbols must be one character, got \"$symbol\"");
			}
			if(!str_contains(implode("", $shape), $symbol)){
				continue;
			}
			[$ingredients[$symbol],] = RecipeItemResolver::ingredient($descriptor);
		}

		$results = RecipeItemResolver::results($body["result"] ?? null);
		$manager->registerShapedRecipe(new ShapedRecipe($shape, $ingredients, $results));
		return 1;
	}

	/**
	 * @param array<mixed> $body
	 * @throws RecipeException
	 * @throws UnknownItemException
	 */
	private function loadShapeless(CraftingManager $manager, array $body) : int{
		$types = [];
		$tags = $this->tags($body);
		foreach(count($tags) === 0 ? ["crafting_table"] : $tags as $tag){
			$recipeType = match($tag){
				"crafting_table" => ShapelessRecipeType::CRAFTING,
				"stonecutter" => ShapelessRecipeType::STONECUTTER,
				"cartography_table" => ShapelessRecipeType::CARTOGRAPHY,
				"smithing_table" => ShapelessRecipeType::SMITHING,
				default => null
			};
			if($recipeType !== null){
				$types[$recipeType->name] = $recipeType;
			}
		}
		if(count($types) === 0){
			throw new RecipeException("Shapeless recipe has no supported tag (tags: " . implode(", ", $tags) . ")");
		}

		$descriptors = $body["ingredients"] ?? null;
		if(!is_array($descriptors) || count($descriptors) === 0){
			throw new RecipeException("Shapeless recipe must have ingredients");
		}
		if(!isset($descriptors[0])){
			$descriptors = [$descriptors];
		}
		$ingredients = [];
		foreach($descriptors as $descriptor){
			[$ingredient, $count] = RecipeItemResolver::ingredient($descriptor);
			for($i = 0; $i < $count; ++$i){
				$ingredients[] = $ingredient;
			}
		}
		if(count($ingredients) > 9){
			throw new RecipeException("Shapeless recipe has more than 9 ingredients");
		}

		$results = RecipeItemResolver::results($body["result"] ?? null);
		foreach($types as $recipeType){
			$manager->registerShapelessRecipe(new ShapelessRecipe($ingredients, $results, $recipeType));
		}
		return count($types);
	}

	/**
	 * @param array<mixed> $body
	 * @throws RecipeException
	 * @throws UnknownItemException
	 */
	private function loadFurnace(CraftingManager $manager, array $body) : int{
		$types = [];
		$tags = $this->tags($body);
		foreach($tags as $tag){
			$furnaceType = match($tag){
				"furnace" => FurnaceType::FURNACE,
				"blast_furnace" => FurnaceType::BLAST_FURNACE,
				"smoker" => FurnaceType::SMOKER,
				"campfire" => FurnaceType::CAMPFIRE,
				"soul_campfire" => FurnaceType::SOUL_CAMPFIRE,
				default => null
			};
			if($furnaceType !== null){
				$types[$furnaceType->name] = $furnaceType;
			}
		}
		if(count($types) === 0){
			throw new RecipeException("Furnace recipe has no supported tag (tags: " . implode(", ", $tags) . ")");
		}

		[$input,] = RecipeItemResolver::ingredient($body["input"] ?? null);
		$output = RecipeItemResolver::result($body["output"] ?? null);
		foreach($types as $furnaceType){
			$manager->getFurnaceRecipeManager($furnaceType)->register(new FurnaceRecipe($output, $input));
		}
		return count($types);
	}

	/**
	 * @param array<mixed> $body
	 * @throws RecipeException
	 * @throws UnknownItemException
	 */
	private function loadBrewingMix(CraftingManager $manager, array $body) : int{
		$input = $body["input"] ?? null;
		$output = $body["output"] ?? null;
		if(!is_string($input) || !is_string($output)){
			throw new RecipeException("Brewing mix must have string input and output");
		}
		[$reagent,] = RecipeItemResolver::ingredient($body["reagent"] ?? null);

		if(RecipeItemResolver::isPotionType($input) || RecipeItemResolver::isPotionType($output)){
			$inputType = RecipeItemResolver::potionType($input);
			$outputType = RecipeItemResolver::potionType($output);
			foreach(self::POTION_CONTAINERS as $container){
				$manager->registerPotionTypeRecipe(new PotionTypeRecipe(
					new ExactRecipeIngredient(RecipeItemResolver::potion($container, $inputType)),
					$reagent,
					RecipeItemResolver::potion($container, $outputType)
				));
			}
			return count(self::POTION_CONTAINERS);
		}

		$manager->registerPotionTypeRecipe(new PotionTypeRecipe(
			new ExactRecipeIngredient(RecipeItemResolver::result($input)),
			$reagent,
			RecipeItemResolver::result($output)
		));
		return 1;
	}

	/**
	 * @param array<mixed> $body
	 * @throws RecipeException
	 * @throws UnknownItemException
	 */
	private function loadBrewingContainer(CraftingManager $manager, array $body) : int{
		$input = $body["input"] ?? null;
		$output = $body["output"] ?? null;
		if(!is_string($input) || !is_string($output)){
			throw new RecipeException("Brewing container recipe must have string input and output");
		}
		[$reagent,] = RecipeItemResolver::ingredient($body["reagent"] ?? null);
		$manager->registerPotionContainerChangeRecipe(new PotionContainerChangeRecipe(
			RecipeItemResolver::knownIdentifier($input),
			$reagent,
			RecipeItemResolver::knownIdentifier($output)
		));
		return 1;
	}
}
