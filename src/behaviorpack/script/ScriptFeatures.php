<?php

declare(strict_types=1);

namespace behaviorpack\script;

use behaviorpack\BehaviorPack;
use behaviorpack\BehaviorPackException;
use behaviorpack\Molang;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\utils\Utils;
use pocketmine\world\World;
use Throwable;
use function array_is_list;
use function count;
use function is_array;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function max;
use function min;
use function mt_rand;
use function sqrt;
use function str_starts_with;
use function strtolower;
use function substr;

/**
 * Places the features of the "features" folder of the behavior packs:
 * structure templates, single blocks, ores, trees, scatter, weighted random,
 * aggregate, sequence and snap to surface features.
 */
final class ScriptFeatures{

	private const MAX_DEPTH = 16;

	/** @var array<string, array{type: string, data: array<mixed>}> */
	private array $features = [];

	public function __construct(
		private ScriptValues $values,
		private ScriptStructures $structures
	){}

	/**
	 * @param list<BehaviorPack> $packs
	 */
	public function registerPacks(array $packs) : void{
		foreach($packs as $pack){
			foreach($pack->listFiles("features") as $file){
				try{
					$json = BehaviorPack::readJson($file);
				}catch(BehaviorPackException){
					continue;
				}
				foreach($json as $key => $data){
					if(!is_string($key) || !str_starts_with($key, "minecraft:") || !is_array($data)){
						continue;
					}
					$identifier = $data["description"]["identifier"] ?? null;
					if(is_string($identifier)){
						$this->features[strtolower($identifier)] = ["type" => substr($key, 10), "data" => $data];
					}
				}
			}
		}
	}

	public function has(string $identifier) : bool{
		return isset($this->features[strtolower($identifier)]);
	}

	public function place(string $identifier, World $world, Vector3 $origin) : bool{
		$feature = $this->features[strtolower($identifier)] ?? null;
		if($feature === null){
			throw new ScriptException("Unknown feature: " . $identifier);
		}
		return $this->placeFeature($feature, $world, (int) \floor($origin->x), (int) \floor($origin->y), (int) \floor($origin->z), 0);
	}

	/**
	 * @param array{type: string, data: array<mixed>} $feature
	 */
	private function placeFeature(array $feature, World $world, int $x, int $y, int $z, int $depth) : bool{
		if($depth > self::MAX_DEPTH || !$world->isInWorld($x, $y, $z)){
			return false;
		}
		$data = $feature["data"];
		return match($feature["type"]){
			"structure_template_feature" => $this->placeStructure($data, $world, $x, $y, $z),
			"single_block_feature" => $this->placeSingleBlock($data, $world, $x, $y, $z),
			"weighted_random_feature" => $this->placeWeighted($data, $world, $x, $y, $z, $depth),
			"aggregate_feature" => $this->placeAggregate($data, $world, $x, $y, $z, $depth),
			"sequence_feature" => $this->placeSequence($data, $world, $x, $y, $z, $depth),
			"scatter_feature" => $this->placeScatter($data, $world, $x, $y, $z, $depth),
			"snap_to_surface_feature" => $this->placeSnap($data, $world, $x, $y, $z, $depth),
			"ore_feature" => $this->placeOre($data, $world, $x, $y, $z),
			"tree_feature" => $this->placeTree($data, $world, $x, $y, $z),
			default => false
		};
	}

	private function placeChild(mixed $identifier, World $world, int $x, int $y, int $z, int $depth) : bool{
		if(!is_string($identifier)){
			return false;
		}
		$feature = $this->features[strtolower($identifier)] ?? null;
		return $feature !== null && $this->placeFeature($feature, $world, $x, $y, $z, $depth + 1);
	}

	/**
	 * @param array<mixed> $data
	 */
	private function placeStructure(array $data, World $world, int $x, int $y, int $z) : bool{
		$name = $data["structure_name"] ?? null;
		if(!is_string($name) || !$this->structures->exists($name)){
			return false;
		}
		$facing = $data["facing_direction"] ?? "random";
		$rotation = match($facing){
			"north" => 180,
			"east" => 270,
			"west" => 90,
			"south" => 0,
			default => [0, 90, 180, 270][mt_rand(0, 3)]
		};
		[$sx, , $sz] = $this->structures->size($name);
		$width = $rotation === 90 || $rotation === 270 ? $sz : $sx;
		$length = $rotation === 90 || $rotation === 270 ? $sx : $sz;
		$constraints = is_array($data["constraints"] ?? null) ? $data["constraints"] : [];
		if(isset($constraints["grounded"])){
			$radius = is_int($data["adjustment_radius"] ?? null) ? $data["adjustment_radius"] : 0;
			$found = false;
			for($dy = 0; $dy <= $radius && !$found; $dy++){
				foreach([$y - $dy, $y + $dy] as $candidate){
					if($candidate > $world->getMinY() && $world->getBlockAt($x, $candidate - 1, $z)->isSolid()){
						$y = $candidate;
						$found = true;
						break;
					}
				}
			}
			if(!$found){
				return false;
			}
		}
		$this->structures->place($name, $world, new Vector3($x - (int) ($width / 2), $y, $z - (int) ($length / 2)), $rotation, "None", true, true, 1.0, null);
		return true;
	}

	/**
	 * @param array<mixed> $data
	 */
	private function placeSingleBlock(array $data, World $world, int $x, int $y, int $z) : bool{
		$block = $this->pickBlock($data["places_block"] ?? null);
		if($block === null){
			return false;
		}
		$current = $world->getBlockAt($x, $y, $z);
		$mayReplace = $data["may_replace"] ?? null;
		if(is_array($mayReplace) && !$this->matchesAny($current, $mayReplace)){
			return false;
		}
		if(!is_array($mayReplace) && $current->getTypeId() !== \pocketmine\block\BlockTypeIds::AIR){
			return false;
		}
		$attach = $data["may_attach_to"] ?? null;
		if(is_array($attach) && is_array($attach["bottom"] ?? null) && !$this->matchesAny($world->getBlockAt($x, $y - 1, $z), $attach["bottom"])){
			return false;
		}
		$world->setBlockAt($x, $y, $z, $block);
		return true;
	}

	/**
	 * @param array<mixed> $data
	 */
	private function placeWeighted(array $data, World $world, int $x, int $y, int $z, int $depth) : bool{
		$entries = is_array($data["features"] ?? null) ? $data["features"] : [];
		$total = 0.0;
		foreach($entries as $entry){
			if(is_array($entry) && is_numeric($entry[1] ?? null)){
				$total += (float) $entry[1];
			}
		}
		if($total <= 0){
			return false;
		}
		$roll = Utils::getRandomFloat() * $total;
		foreach($entries as $entry){
			if(!is_array($entry) || !is_numeric($entry[1] ?? null)){
				continue;
			}
			$roll -= (float) $entry[1];
			if($roll <= 0){
				return $this->placeChild($entry[0] ?? null, $world, $x, $y, $z, $depth);
			}
		}
		return false;
	}

	/**
	 * @param array<mixed> $data
	 */
	private function placeAggregate(array $data, World $world, int $x, int $y, int $z, int $depth) : bool{
		$earlyOut = $data["early_out"] ?? "none";
		$any = false;
		foreach(is_array($data["features"] ?? null) ? $data["features"] : [] as $identifier){
			$placed = $this->placeChild($identifier, $world, $x, $y, $z, $depth);
			$any = $any || $placed;
			if(($earlyOut === "first_failure" && !$placed) || ($earlyOut === "first_success" && $placed)){
				break;
			}
		}
		return $any;
	}

	/**
	 * @param array<mixed> $data
	 */
	private function placeSequence(array $data, World $world, int $x, int $y, int $z, int $depth) : bool{
		foreach(is_array($data["features"] ?? null) ? $data["features"] : [] as $identifier){
			if(!$this->placeChild($identifier, $world, $x, $y, $z, $depth)){
				return false;
			}
		}
		return true;
	}

	/**
	 * @param array<mixed> $data
	 */
	private function placeScatter(array $data, World $world, int $x, int $y, int $z, int $depth) : bool{
		$variables = ["originx" => $x, "originy" => $y, "originz" => $z, "worldx" => $x, "worldy" => $y, "worldz" => $z];
		$chance = $data["scatter_chance"] ?? 100;
		if(is_array($chance)){
			$numerator = (float) ($chance["numerator"] ?? 1);
			$denominator = max(1.0, (float) ($chance["denominator"] ?? 1));
			$probability = $numerator / $denominator;
		}else{
			$probability = Molang::evaluate($chance, $variables) / 100;
		}
		if(Utils::getRandomFloat() > $probability){
			return false;
		}
		$iterations = (int) Molang::evaluate($data["iterations"] ?? 1, $variables);
		$any = false;
		for($i = 0; $i < $iterations; $i++){
			$tx = $x + (int) $this->coordinate($data["x"] ?? 0, $variables, $i, $iterations);
			$tz = $z + (int) $this->coordinate($data["z"] ?? 0, $variables, $i, $iterations);
			$ty = $y + (int) $this->coordinate($data["y"] ?? 0, $variables, $i, $iterations);
			if(($data["project_input_to_floor"] ?? false) === true){
				$ty = $this->surface($world, $tx, $tz) ?? $ty;
			}
			$any = $this->placeChild($data["places_feature"] ?? null, $world, $tx, $ty, $tz, $depth) || $any;
		}
		return $any;
	}

	/**
	 * @param array<string, float|int|bool> $variables
	 */
	private function coordinate(mixed $spec, array $variables, int $step, int $steps) : float{
		if(!is_array($spec)){
			return Molang::evaluate($spec, $variables);
		}
		$extent = is_array($spec["extent"] ?? null) ? $spec["extent"] : [0, 0];
		$low = Molang::evaluate($extent[0] ?? 0, $variables);
		$high = Molang::evaluate($extent[1] ?? 0, $variables);
		return match($spec["distribution"] ?? "uniform"){
			"gaussian" => ($low + $high) / 2 + (Utils::getRandomFloat() + Utils::getRandomFloat() - 1) * ($high - $low) / 2,
			"triangle" => $low + (Utils::getRandomFloat() + Utils::getRandomFloat()) / 2 * ($high - $low),
			"fixed_grid" => $low + ($steps > 0 ? $step % (int) max(1, $high - $low + 1) : 0),
			"jittered_grid" => $low + ($step % (int) max(1, $high - $low + 1)) + Utils::getRandomFloat(),
			default => $low + Utils::getRandomFloat() * ($high - $low + 1)
		};
	}

	private function surface(World $world, int $x, int $z) : ?int{
		$world->loadChunk($x >> 4, $z >> 4);
		$y = $world->getHighestBlockAt($x, $z);
		return $y === null ? null : $y + 1;
	}

	/**
	 * @param array<mixed> $data
	 */
	private function placeSnap(array $data, World $world, int $x, int $y, int $z, int $depth) : bool{
		$range = is_int($data["vertical_search_range"] ?? null) ? $data["vertical_search_range"] : 8;
		$ceiling = ($data["surface"] ?? "floor") === "ceiling";
		for($dy = 0; $dy <= $range; $dy++){
			$ty = $ceiling ? $y + $dy : $y - $dy;
			$below = $world->getBlockAt($x, $ceiling ? $ty + 1 : $ty - 1, $z);
			if($below->isSolid() && !$world->getBlockAt($x, $ty, $z)->isSolid()){
				return $this->placeChild($data["feature_to_snap"] ?? null, $world, $x, $ty, $z, $depth);
			}
		}
		return false;
	}

	/**
	 * @param array<mixed> $data
	 */
	private function placeOre(array $data, World $world, int $x, int $y, int $z) : bool{
		$count = max(1, (int) Molang::evaluate($data["count"] ?? 1));
		$rules = is_array($data["replace_rules"] ?? null) ? $data["replace_rules"] : [];
		$placed = false;
		$radius = max(1.0, sqrt($count) / 1.5);
		for($i = 0; $i < $count; $i++){
			$tx = $x + (int) \round((Utils::getRandomFloat() * 2 - 1) * $radius);
			$ty = $y + (int) \round((Utils::getRandomFloat() * 2 - 1) * $radius);
			$tz = $z + (int) \round((Utils::getRandomFloat() * 2 - 1) * $radius);
			if(!$world->isInWorld($tx, $ty, $tz)){
				continue;
			}
			$current = $world->getBlockAt($tx, $ty, $tz);
			foreach($rules as $rule){
				if(!is_array($rule)){
					continue;
				}
				$mayReplace = $rule["may_replace"] ?? null;
				if(is_array($mayReplace) && !$this->matchesAny($current, $mayReplace)){
					continue;
				}
				$block = $this->pickBlock($rule["places_block"] ?? null);
				if($block !== null){
					$world->setBlockAt($tx, $ty, $tz, $block);
					$placed = true;
				}
				break;
			}
		}
		return $placed;
	}

	/**
	 * @param array<mixed> $data
	 */
	private function placeTree(array $data, World $world, int $x, int $y, int $z) : bool{
		$growOn = $data["may_grow_on"] ?? null;
		if(is_array($growOn) && !$this->matchesAny($world->getBlockAt($x, $y - 1, $z), $growOn)){
			return false;
		}
		$trunk = null;
		foreach(["trunk", "cherry_trunk", "acacia_trunk", "fancy_trunk", "mega_trunk", "mangrove_trunk"] as $key){
			if(is_array($data[$key] ?? null)){
				$trunk = $data[$key];
				break;
			}
		}
		$canopy = null;
		foreach(["canopy", "cherry_canopy", "acacia_canopy", "fancy_canopy", "mega_canopy", "mangrove_canopy", "pine_canopy", "spruce_canopy", "roofed_canopy", "random_spread_canopy"] as $key){
			if(is_array($data[$key] ?? null)){
				$canopy = $data[$key];
				break;
			}
		}
		if($trunk === null){
			return false;
		}
		$log = $this->pickBlock($trunk["trunk_block"] ?? null);
		if($log === null){
			return false;
		}
		$height = $this->treeHeight($trunk["trunk_height"] ?? 5);
		$mayReplace = is_array($data["may_replace"] ?? null) ? $data["may_replace"] : ["minecraft:air"];
		$growThrough = is_array($data["may_grow_through"] ?? null) ? $data["may_grow_through"] : [];
		for($dy = 0; $dy < $height; $dy++){
			$current = $world->getBlockAt($x, $y + $dy, $z);
			if(!$this->matchesAny($current, $mayReplace) && !$this->matchesAny($current, $growThrough)){
				return false;
			}
		}
		$base = $data["base_block"] ?? null;
		if(is_array($base) && count($base) > 0){
			$baseBlock = $this->pickBlock($base[0]);
			if($baseBlock !== null){
				$world->setBlockAt($x, $y - 1, $z, $baseBlock);
			}
		}
		for($dy = 0; $dy < $height; $dy++){
			$world->setBlockAt($x, $y + $dy, $z, $log);
		}
		$top = $y + $height;
		if(is_array($trunk["branches"] ?? null)){
			$branches = $trunk["branches"];
			$length = (int) $this->range($branches["branch_horizontal_length"] ?? 2);
			$count = mt_rand(1, 2);
			for($b = 0; $b < $count; $b++){
				$dir = [[1, 0], [-1, 0], [0, 1], [0, -1]][mt_rand(0, 3)];
				$by = $top + (int) $this->range($branches["branch_start_offset_from_top"] ?? -3);
				$bx = $x;
				$bz = $z;
				for($i = 1; $i <= $length; $i++){
					$bx = $x + $dir[0] * $i;
					$bz = $z + $dir[1] * $i;
					$by += $i === $length ? 0 : (int) ($i % 2);
					if($this->matchesAny($world->getBlockAt($bx, $by, $bz), $mayReplace)){
						$world->setBlockAt($bx, $by, $bz, $log);
					}
				}
				$branchCanopy = $branches["branch_canopy"] ?? null;
				if(is_array($branchCanopy)){
					foreach($branchCanopy as $value){
						if(is_array($value)){
							$this->placeCanopy($value, $world, $bx, $by + 1, $bz, $mayReplace);
							break;
						}
					}
				}
			}
		}
		if($canopy !== null){
			$this->placeCanopy($canopy, $world, $x, $top, $z, $mayReplace);
		}
		return true;
	}

	/**
	 * @param array<mixed> $canopy
	 * @param array<mixed> $mayReplace
	 */
	private function placeCanopy(array $canopy, World $world, int $x, int $y, int $z, array $mayReplace) : void{
		$leaves = $this->pickBlock($canopy["leaf_block"] ?? null);
		if($leaves === null){
			return;
		}
		$offset = is_array($canopy["canopy_offset"] ?? null) ? $canopy["canopy_offset"] : ["min" => -2, "max" => 1];
		$min = is_int($offset["min"] ?? null) ? $offset["min"] : -2;
		$max = is_int($offset["max"] ?? null) ? $offset["max"] : 1;
		if(isset($canopy["height"]) || isset($canopy["radius"])){
			$max = 1;
			$min = 1 - (int) Molang::evaluate($canopy["height"] ?? 4);
		}
		$radius = isset($canopy["radius"]) ? (int) Molang::evaluate($canopy["radius"]) : null;
		$minWidth = is_int($canopy["min_width"] ?? null) ? $canopy["min_width"] : 0;
		$chance = $canopy["variation_chance"] ?? null;
		for($dy = $min; $dy <= $max; $dy++){
			$width = $radius ?? max($minWidth, (int) (($max - $dy) / 2) + 1);
			if($radius !== null){
				$width = max(1, (int) \round($radius * (1 - \abs($dy - ($min + $max) / 2) / max(1, ($max - $min + 1)))));
			}
			for($dx = -$width; $dx <= $width; $dx++){
				for($dz = -$width; $dz <= $width; $dz++){
					$corner = \abs($dx) === $width && \abs($dz) === $width;
					if($corner && $width > 0 && is_array($chance) && mt_rand(1, max(1, (int) ($chance["denominator"] ?? 2))) > (int) ($chance["numerator"] ?? 1)){
						continue;
					}
					$tx = $x + $dx;
					$ty = $y + $dy;
					$tz = $z + $dz;
					if($world->isInWorld($tx, $ty, $tz) && $this->matchesAny($world->getBlockAt($tx, $ty, $tz), $mayReplace)){
						$world->setBlockAt($tx, $ty, $tz, $leaves);
					}
				}
			}
		}
	}

	private function treeHeight(mixed $spec) : int{
		if(is_int($spec) || is_float($spec) || is_string($spec)){
			return max(1, (int) Molang::evaluate($spec));
		}
		if(!is_array($spec)){
			return 5;
		}
		if(isset($spec["base"])){
			$height = (int) $spec["base"];
			foreach(is_array($spec["intervals"] ?? null) ? $spec["intervals"] : [] as $interval){
				$height += mt_rand(0, max(0, (int) $interval));
			}
			return max(1, $height);
		}
		return max(1, (int) $this->range($spec));
	}

	private function range(mixed $spec) : float{
		if(is_array($spec)){
			if(array_is_list($spec) && count($spec) === 2){
				return (float) mt_rand((int) min($spec[0], $spec[1]), (int) max($spec[0], $spec[1]));
			}
			$low = (int) ($spec["range_min"] ?? $spec["min"] ?? 0);
			$high = (int) ($spec["range_max"] ?? $spec["max"] ?? $low);
			return (float) mt_rand(min($low, $high), max($low, $high));
		}
		return Molang::evaluate($spec);
	}

	/**
	 * Resolves a block specification: an identifier, a {name, states} object
	 * or a weighted list of them.
	 */
	private function pickBlock(mixed $spec) : ?Block{
		if(is_array($spec) && array_is_list($spec)){
			$total = 0.0;
			foreach($spec as $entry){
				$total += is_array($entry) && is_numeric($entry["weight"] ?? null) ? (float) $entry["weight"] : 1.0;
			}
			$roll = Utils::getRandomFloat() * $total;
			foreach($spec as $entry){
				$roll -= is_array($entry) && is_numeric($entry["weight"] ?? null) ? (float) $entry["weight"] : 1.0;
				if($roll <= 0){
					return $this->pickBlock(is_array($entry) && isset($entry["block"]) ? $entry["block"] : $entry);
				}
			}
			return null;
		}
		try{
			if(is_string($spec)){
				return $this->values->block($spec);
			}
			if(is_array($spec) && is_string($spec["name"] ?? null)){
				return $this->values->block(["\$p" => $spec["name"], "s" => is_array($spec["states"] ?? null) ? $spec["states"] : []]);
			}
		}catch(Throwable){
		}
		return null;
	}

	/**
	 * @param array<mixed> $list
	 */
	private function matchesAny(Block $block, array $list) : bool{
		$typeId = $this->values->blockTypeId($block);
		foreach($list as $entry){
			if(is_string($entry) && ScriptValues::namespaced($entry) === $typeId){
				return true;
			}
			if(is_array($entry)){
				if(is_string($entry["name"] ?? null)){
					if(ScriptValues::namespaced($entry["name"]) !== $typeId){
						continue;
					}
					$states = is_array($entry["states"] ?? null) ? $entry["states"] : [];
					$current = $this->values->permutation($block)["s"];
					$matches = true;
					foreach($states as $name => $value){
						if(!is_array($current) || ($current[$name] ?? null) != $value){
							$matches = false;
							break;
						}
					}
					if($matches){
						return true;
					}
				}elseif(isset($entry["tags"])){
					if($this->matchesTags($block, (string) $entry["tags"])){
						return true;
					}
				}
			}
		}
		return false;
	}

	private function matchesTags(Block $block, string $expression) : bool{
		$typeId = $this->values->blockTypeId($block);
		return Molang::evaluate($expression, [], function(string $query, array $args) use ($typeId) : bool{
			if($query !== "any_tag" && $query !== "all_tags"){
				return false;
			}
			foreach($args as $tag){
				if(!is_string($tag)){
					continue;
				}
				$has = match($tag){
					"dirt" => \in_array($typeId, ["minecraft:dirt", "minecraft:grass_block", "minecraft:grass", "minecraft:podzol", "minecraft:coarse_dirt", "minecraft:mycelium", "minecraft:rooted_dirt", "minecraft:moss_block", "minecraft:mud", "minecraft:dirt_with_roots"], true),
					"stone" => \in_array($typeId, ["minecraft:stone", "minecraft:cobblestone", "minecraft:deepslate", "minecraft:granite", "minecraft:diorite", "minecraft:andesite", "minecraft:tuff"], true),
					"sand" => \in_array($typeId, ["minecraft:sand", "minecraft:red_sand"], true),
					"log" => \str_ends_with($typeId, "_log") || \str_ends_with($typeId, "_wood"),
					"plant" => \str_ends_with($typeId, "_sapling") || \str_contains($typeId, "grass") || \str_contains($typeId, "flower"),
					default => \str_contains($typeId, $tag)
				};
				if($has && $query === "any_tag"){
					return true;
				}
				if(!$has && $query === "all_tags"){
					return false;
				}
			}
			return $query === "all_tags";
		}) != 0;
	}
}
