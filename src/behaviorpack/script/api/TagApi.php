<?php

declare(strict_types=1);

namespace behaviorpack\script\api;

use behaviorpack\BehaviorPack;
use behaviorpack\Molang;
use behaviorpack\script\ScriptException;
use behaviorpack\script\ScriptLoader;
use behaviorpack\script\ScriptValues;
use pocketmine\block\BaseRail;
use pocketmine\block\BaseSign;
use pocketmine\block\Block;
use pocketmine\block\BlockToolType;
use pocketmine\block\CarvedPumpkin;
use pocketmine\block\Crops;
use pocketmine\block\Dirt;
use pocketmine\block\DoublePlant;
use pocketmine\block\Flower;
use pocketmine\block\Grass;
use pocketmine\block\Gravel;
use pocketmine\block\Lava;
use pocketmine\block\MonsterSpawner;
use pocketmine\block\Planks;
use pocketmine\block\Pumpkin;
use pocketmine\block\Sand;
use pocketmine\block\Sapling;
use pocketmine\block\Snow;
use pocketmine\block\SnowLayer;
use pocketmine\block\Stem;
use pocketmine\block\TallGrass;
use pocketmine\block\Trapdoor;
use pocketmine\block\Water;
use pocketmine\block\Wood;
use pocketmine\item\Armor;
use pocketmine\item\Arrow;
use pocketmine\item\Axe;
use pocketmine\item\Banner;
use pocketmine\item\FoodSourceItem;
use pocketmine\item\Hoe;
use pocketmine\item\Item;
use pocketmine\item\Mace;
use pocketmine\item\Pickaxe;
use pocketmine\item\Record;
use pocketmine\item\Shield;
use pocketmine\item\Shovel;
use pocketmine\item\SpawnEgg;
use pocketmine\item\Spear;
use pocketmine\item\Sword;
use pocketmine\item\TieredTool;
use pocketmine\item\Tool;
use pocketmine\item\ToolTier;
use pocketmine\item\Trident;
use Throwable;
use function array_keys;
use function array_values;
use function in_array;
use function is_array;
use function is_bool;
use function is_int;
use function is_numeric;
use function is_string;
use function preg_replace_callback;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;

/**
 * Answers the "tag." requests: the tags of blocks, block permutations and
 * item stacks, vanilla ones and the ones declared by the behavior packs.
 */
final class TagApi{

	private const STONE = [
		"stone", "cobblestone", "mossy_cobblestone", "granite", "diorite", "andesite", "polished_granite",
		"polished_diorite", "polished_andesite", "deepslate", "cobbled_deepslate", "tuff", "blackstone",
		"stone_bricks", "mossy_stone_bricks", "cracked_stone_bricks", "chiseled_stone_bricks", "smooth_stone",
		"sandstone", "red_sandstone", "calcite", "dripstone_block", "netherrack", "basalt", "end_stone"
	];

	private const METAL = [
		"iron_block", "gold_block", "iron_bars", "iron_door", "iron_trapdoor", "anvil", "chipped_anvil",
		"damaged_anvil", "cauldron", "hopper", "lantern", "soul_lantern", "chain", "netherite_block",
		"raw_iron_block", "raw_gold_block", "raw_copper_block", "heavy_weighted_pressure_plate",
		"light_weighted_pressure_plate", "bell", "brewing_stand"
	];

	private const MEAT = [
		"beef", "cooked_beef", "porkchop", "cooked_porkchop", "chicken", "cooked_chicken", "mutton",
		"cooked_mutton", "rabbit", "cooked_rabbit", "rotten_flesh"
	];

	private const FISH = ["cod", "cooked_cod", "salmon", "cooked_salmon", "tropical_fish", "pufferfish"];

	private const TRIM_MATERIALS = [
		"iron_ingot", "copper_ingot", "gold_ingot", "lapis_lazuli", "emerald", "diamond", "netherite_ingot",
		"redstone", "quartz", "amethyst_shard", "resin_brick"
	];

	/** @var array<string, list<string>> */
	private static array $blockTags = [];

	/** @var array<string, list<array{0: string, 1: list<string>}>> */
	private static array $permutationTags = [];

	/** @var array<string, list<string>> */
	private static array $itemTags = [];

	public function __construct(
		private ScriptValues $values,
		private ScriptLoader $loader
	){}

	/**
	 * @param list<mixed> $a
	 */
	public function handle(string $method, array $a) : mixed{
		return match($method){
			"tag.block" => $this->blockTags($a[0] ?? null),
			"tag.permutation" => $this->permutationTags($a[0] ?? null),
			"tag.item" => $this->itemTags($a[0] ?? null),
			default => throw new ScriptException("Unknown request " . $method)
		};
	}

	/**
	 * Collects the tags that the blocks and items of the packs declare.
	 *
	 * @param list<BehaviorPack> $packs
	 */
	public static function registerPacks(array $packs) : void{
		self::$blockTags = [];
		self::$permutationTags = [];
		self::$itemTags = [];
		foreach($packs as $pack){
			foreach($pack->listFiles("blocks") as $file){
				try{
					self::registerBlock(BehaviorPack::readJson($file));
				}catch(Throwable){
					continue;
				}
			}
			foreach($pack->listFiles("items") as $file){
				try{
					self::registerItem(BehaviorPack::readJson($file));
				}catch(Throwable){
					continue;
				}
			}
		}
	}

	/**
	 * @param array<mixed> $json
	 */
	private static function registerBlock(array $json) : void{
		$definition = $json["minecraft:block"] ?? null;
		if(!is_array($definition)){
			return;
		}
		$id = $definition["description"]["identifier"] ?? null;
		if(!is_string($id)){
			return;
		}
		$id = strtolower($id);
		$components = is_array($definition["components"] ?? null) ? $definition["components"] : [];
		self::$blockTags[$id] = self::componentTags($components, self::$blockTags[$id] ?? []);
		$permutations = is_array($definition["permutations"] ?? null) ? $definition["permutations"] : [];
		foreach($permutations as $permutation){
			if(!is_array($permutation) || !is_array($permutation["components"] ?? null)){
				continue;
			}
			$tags = self::componentTags($permutation["components"], []);
			if($tags === []){
				continue;
			}
			$condition = $permutation["condition"] ?? "1";
			self::$permutationTags[$id][] = [is_string($condition) ? $condition : "1", $tags];
		}
	}

	/**
	 * @param array<mixed> $json
	 */
	private static function registerItem(array $json) : void{
		$definition = $json["minecraft:item"] ?? null;
		if(!is_array($definition)){
			return;
		}
		$id = $definition["description"]["identifier"] ?? null;
		if(!is_string($id)){
			return;
		}
		$id = strtolower($id);
		$tags = self::$itemTags[$id] ?? [];
		$components = is_array($definition["components"] ?? null) ? $definition["components"] : [];
		$tags = self::componentTags($components, $tags);
		foreach([$components["minecraft:tags"]["tags"] ?? null, $definition["description"]["tags"] ?? null] as $list){
			if(!is_array($list)){
				continue;
			}
			foreach($list as $tag){
				if(is_string($tag) && !in_array($tag, $tags, true)){
					$tags[] = $tag;
				}
			}
		}
		self::$itemTags[$id] = $tags;
	}

	/**
	 * @param array<mixed>  $components
	 * @param list<string>  $tags
	 * @return list<string>
	 */
	private static function componentTags(array $components, array $tags) : array{
		foreach(array_keys($components) as $key){
			if(!is_string($key) || !str_starts_with($key, "tag:")){
				continue;
			}
			$tag = substr($key, 4);
			if($tag !== "" && !in_array($tag, $tags, true)){
				$tags[] = $tag;
			}
		}
		return $tags;
	}

	/**
	 * @return list<string>
	 */
	private function blockTags(mixed $ref) : array{
		if(!is_array($ref) || !is_array($ref["\$b"] ?? null)){
			throw new ScriptException("Invalid block");
		}
		[$x, $y, $z] = $ref["\$b"];
		$block = $this->values->world($ref["d"] ?? null)->getBlockAt((int) $x, (int) $y, (int) $z);
		$permutation = $this->values->permutation($block);
		$states = is_array($permutation["s"]) ? $permutation["s"] : [];
		return $this->merge($this->customBlockTags((string) $permutation["\$p"], $states), $this->vanillaBlockTags($block, (string) $permutation["\$p"]));
	}

	/**
	 * @return list<string>
	 */
	private function permutationTags(mixed $value) : array{
		if(!is_array($value) || !is_string($value["\$p"] ?? null)){
			throw new ScriptException("Invalid block permutation");
		}
		$typeId = ScriptValues::namespaced($value["\$p"]);
		$states = is_array($value["s"] ?? null) ? $value["s"] : [];
		$tags = $this->customBlockTags($typeId, $states);
		try{
			$block = $this->values->block(["\$p" => $typeId, "s" => $states]);
		}catch(Throwable){
			return $tags;
		}
		return $this->merge($tags, $this->vanillaBlockTags($block, $typeId));
	}

	/**
	 * @return list<string>
	 */
	private function itemTags(mixed $value) : array{
		$data = is_array($value) && is_array($value["\$i"] ?? null) ? $value["\$i"] : null;
		if($data === null || !is_string($data["t"] ?? null)){
			return [];
		}
		$typeId = ScriptValues::namespaced($data["t"]);
		$tags = self::$itemTags[$typeId] ?? [];
		try{
			$item = $this->values->decodeItem($value);
		}catch(Throwable){
			return $tags;
		}
		return $this->merge($tags, $this->vanillaItemTags($item, $typeId));
	}

	/**
	 * @param list<string> $first
	 * @param list<string> $second
	 * @return list<string>
	 */
	private function merge(array $first, array $second) : array{
		foreach($second as $tag){
			if(!in_array($tag, $first, true)){
				$first[] = $tag;
			}
		}
		return array_values($first);
	}

	/**
	 * @param array<string, mixed> $states
	 * @return list<string>
	 */
	private function customBlockTags(string $typeId, array $states) : array{
		$tags = self::$blockTags[$typeId] ?? [];
		foreach(self::$permutationTags[$typeId] ?? [] as [$condition, $permutationTags]){
			if($this->matchesCondition($condition, $states)){
				$tags = $this->merge($tags, $permutationTags);
			}
		}
		return $tags;
	}

	/**
	 * Evaluates a permutation condition against block states. String
	 * comparisons are resolved first since Molang only compares numbers; a
	 * condition that cannot be evaluated is treated as matching.
	 *
	 * @param array<string, mixed> $states
	 */
	private function matchesCondition(string $condition, array $states) : bool{
		$pattern = '/(?:q|query)\.(?:block_state|block_property)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)\s*(==|!=)\s*[\'"]([^\'"]*)[\'"]/';
		$expression = preg_replace_callback($pattern, function(array $match) use ($states) : string{
			$current = $states[$match[1]] ?? null;
			$equal = is_string($current) && $current === $match[3];
			return ($match[2] === "==") === $equal ? "1" : "0";
		}, $condition);
		if($expression === null){
			return true;
		}
		try{
			return Molang::evaluate($expression, [], function(string $query, array $args) use ($states) : float|int|bool|string|null{
				if($query !== "block_state" && $query !== "block_property"){
					return 0;
				}
				$name = $args[0] ?? null;
				if(!is_string($name)){
					return 0;
				}
				$value = $states[$name] ?? null;
				if(is_bool($value) || is_int($value)){
					return $value;
				}
				return is_numeric($value) ? (float) $value : 0;
			}) != 0;
		}catch(Throwable){
			return true;
		}
	}

	/**
	 * @return list<string>
	 */
	private function vanillaBlockTags(Block $block, string $typeId) : array{
		if(!str_starts_with($typeId, "minecraft:")){
			return [];
		}
		$name = substr($typeId, strlen("minecraft:"));
		$tags = [];
		$info = $block->getBreakInfo();
		$toolType = $info->getToolType();
		foreach([
			BlockToolType::PICKAXE => "minecraft:is_pickaxe_item_destructible",
			BlockToolType::AXE => "minecraft:is_axe_item_destructible",
			BlockToolType::SHOVEL => "minecraft:is_shovel_item_destructible",
			BlockToolType::HOE => "minecraft:is_hoe_item_destructible",
			BlockToolType::SWORD => "minecraft:is_sword_item_destructible",
			BlockToolType::SHEARS => "minecraft:is_shears_item_destructible"
		] as $type => $tag){
			if(($toolType & $type) !== 0){
				$tags[] = $tag;
			}
		}
		if(($toolType & BlockToolType::PICKAXE) !== 0){
			$level = $info->getToolHarvestLevel();
			if($level <= ToolTier::WOOD->getHarvestLevel()){
				$tags[] = "wood_pick_diggable";
			}elseif($level <= ToolTier::GOLD->getHarvestLevel()){
				$tags[] = "gold_pick_diggable";
			}elseif($level <= ToolTier::STONE->getHarvestLevel()){
				$tags[] = "stone_pick_diggable";
				$tags[] = "minecraft:stone_tier_destructible";
			}elseif($level <= ToolTier::IRON->getHarvestLevel()){
				$tags[] = "iron_pick_diggable";
				$tags[] = "minecraft:iron_tier_destructible";
			}else{
				$tags[] = "diamond_pick_diggable";
				$tags[] = "minecraft:diamond_tier_destructible";
			}
		}
		if(in_array($name, self::STONE, true) || str_ends_with($name, "_stone_bricks") || str_ends_with($name, "cobblestone")){
			$tags[] = "stone";
		}
		if(in_array($name, self::METAL, true) || str_contains($name, "copper")){
			$tags[] = "metal";
		}
		if($block instanceof Wood || str_ends_with($name, "_log") || str_ends_with($name, "_stem") || str_ends_with($name, "_hyphae")){
			$tags[] = "wood";
			$tags[] = "log";
		}elseif($block instanceof Planks || str_ends_with($name, "_planks")){
			$tags[] = "wood";
		}
		if($block instanceof Grass){
			$tags[] = "grass";
			$tags[] = "dirt";
			$tags[] = "fertilize_area";
		}elseif($block instanceof Dirt || in_array($name, ["podzol", "mycelium", "dirt_with_roots", "mud", "moss_block"], true)){
			$tags[] = "dirt";
		}
		if($block instanceof Sand){
			$tags[] = "sand";
		}
		if($block instanceof Gravel){
			$tags[] = "gravel";
		}
		if($block instanceof Snow || $block instanceof SnowLayer){
			$tags[] = "snow";
		}
		if($block instanceof Crops || $block instanceof Stem){
			$tags[] = "crop";
			$tags[] = "plant";
		}elseif($block instanceof Sapling || $block instanceof Flower || $block instanceof DoublePlant || $block instanceof TallGrass){
			$tags[] = "plant";
		}
		if($block instanceof Water){
			$tags[] = "water";
		}
		if($block instanceof Lava){
			$tags[] = "lava";
		}
		if($block instanceof BaseSign){
			$tags[] = "text_sign";
		}
		if($block instanceof Trapdoor){
			$tags[] = "trapdoors";
		}
		if($block instanceof BaseRail){
			$tags[] = "rail";
		}
		if($block instanceof MonsterSpawner){
			$tags[] = "mob_spawner";
		}
		if($block instanceof Pumpkin || $block instanceof CarvedPumpkin){
			$tags[] = "pumpkin";
		}
		if($name === "scaffolding"){
			$tags[] = "one_way_collidable";
		}
		return $tags;
	}

	/**
	 * @return list<string>
	 */
	private function vanillaItemTags(Item $item, string $typeId) : array{
		if(!str_starts_with($typeId, "minecraft:")){
			return [];
		}
		$name = substr($typeId, strlen("minecraft:"));
		$tags = [];
		if($item instanceof FoodSourceItem){
			$tags[] = "minecraft:is_food";
		}
		if(in_array($name, self::MEAT, true)){
			$tags[] = "minecraft:is_meat";
		}
		if(in_array($name, self::FISH, true)){
			$tags[] = "minecraft:is_fish";
		}
		if(str_starts_with($name, "cooked_") || $name === "baked_potato"){
			$tags[] = "minecraft:is_cooked";
		}
		if($item instanceof Tool){
			$tags[] = "minecraft:is_tool";
		}
		foreach([
			[Pickaxe::class, "minecraft:is_pickaxe"],
			[Axe::class, "minecraft:is_axe"],
			[Shovel::class, "minecraft:is_shovel"],
			[Hoe::class, "minecraft:is_hoe"]
		] as [$class, $tag]){
			if($item instanceof $class){
				$tags[] = $tag;
				$tags[] = "minecraft:digger";
			}
		}
		if($item instanceof Sword){
			$tags[] = "minecraft:is_sword";
		}
		if($item instanceof Trident){
			$tags[] = "minecraft:is_trident";
		}
		if($item instanceof Shield){
			$tags[] = "minecraft:is_shield";
		}
		if($item instanceof Mace){
			$tags[] = "minecraft:is_mace";
		}
		if($item instanceof Spear){
			$tags[] = "minecraft:is_spear";
		}
		if($item instanceof TieredTool){
			$tags[] = match($item->getTier()){
				ToolTier::WOOD => "minecraft:wooden_tier",
				ToolTier::GOLD => "minecraft:golden_tier",
				ToolTier::STONE => "minecraft:stone_tier",
				ToolTier::COPPER => "minecraft:copper_tier",
				ToolTier::IRON => "minecraft:iron_tier",
				ToolTier::DIAMOND => "minecraft:diamond_tier",
				ToolTier::NETHERITE => "minecraft:netherite_tier"
			};
		}
		if($item instanceof Armor){
			$tags[] = "minecraft:is_armor";
			foreach(["leather", "chainmail", "iron", "golden", "diamond", "netherite", "copper"] as $material){
				if(str_starts_with($name, $material . "_")){
					$tags[] = "minecraft:" . $material . "_tier";
					break;
				}
			}
		}
		if(str_ends_with($name, "_horse_armor")){
			$tags[] = "minecraft:horse_armor";
		}
		if(str_ends_with($name, "_planks")){
			$tags[] = "minecraft:planks";
		}
		$isLog = str_ends_with($name, "_log") || str_ends_with($name, "_wood") || str_ends_with($name, "_stem") || str_ends_with($name, "_hyphae");
		if($isLog){
			$tags[] = "minecraft:logs";
			if(str_contains($name, "crimson")){
				$tags[] = "minecraft:crimson_stems";
			}elseif(str_contains($name, "warped")){
				$tags[] = "minecraft:warped_stems";
			}else{
				$tags[] = "minecraft:logs_that_burn";
			}
			if(str_contains($name, "mangrove")){
				$tags[] = "minecraft:mangrove_logs";
			}
		}
		if($name === "wool" || str_ends_with($name, "_wool")){
			$tags[] = "minecraft:wool";
			$tags[] = "minecraft:vibration_damper";
		}elseif(str_ends_with($name, "_carpet")){
			$tags[] = "minecraft:vibration_damper";
		}
		if($name === "coal" || $name === "charcoal"){
			$tags[] = "minecraft:coals";
		}
		if($item instanceof Arrow || $name === "arrow"){
			$tags[] = "minecraft:arrow";
		}
		if($item instanceof Banner || $name === "banner"){
			$tags[] = "minecraft:banner";
		}
		if(str_ends_with($name, "_boat") || str_ends_with($name, "_raft")){
			$tags[] = "minecraft:boats";
			if(!str_contains($name, "chest_")){
				$tags[] = "minecraft:boat";
			}
		}
		if(in_array($name, ["book", "writable_book", "written_book", "enchanted_book"], true)){
			$tags[] = "minecraft:bookshelf_books";
		}
		if($name === "writable_book" || $name === "written_book"){
			$tags[] = "minecraft:lectern_books";
		}
		if(str_ends_with($name, "_door")){
			$tags[] = "minecraft:door";
		}
		if(in_array($name, ["egg", "blue_egg", "brown_egg"], true)){
			$tags[] = "minecraft:egg";
		}
		if($name === "painting"){
			$tags[] = "minecraft:hanging_actor";
		}
		if($item instanceof Record || str_starts_with($name, "music_disc_")){
			$tags[] = "minecraft:music_disc";
		}
		if($name === "sand" || $name === "red_sand"){
			$tags[] = "minecraft:sand";
		}
		if(str_ends_with($name, "_sign")){
			$tags[] = "minecraft:sign";
		}
		if($name === "soul_sand" || $name === "soul_soil"){
			$tags[] = "minecraft:soul_fire_base_blocks";
		}
		if($item instanceof SpawnEgg || str_ends_with($name, "_spawn_egg")){
			$tags[] = "minecraft:spawn_egg";
		}
		if(in_array($name, ["stone_bricks", "mossy_stone_bricks", "cracked_stone_bricks", "chiseled_stone_bricks"], true)){
			$tags[] = "minecraft:stone_bricks";
		}
		if(in_array($name, ["cobblestone", "blackstone", "cobbled_deepslate"], true)){
			$tags[] = "minecraft:stone_crafting_materials";
			$tags[] = "minecraft:stone_tool_materials";
		}
		if($name === "netherite_ingot"){
			$tags[] = "minecraft:transform_materials";
		}
		if($name === "netherite_upgrade_smithing_template"){
			$tags[] = "minecraft:transform_templates";
		}
		if(str_ends_with($name, "_armor_trim_smithing_template")){
			$tags[] = "minecraft:trim_templates";
		}
		if(in_array($name, self::TRIM_MATERIALS, true)){
			$tags[] = "minecraft:trim_materials";
		}
		return $tags;
	}
}
