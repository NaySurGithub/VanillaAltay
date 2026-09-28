<?php

declare(strict_types=1);

namespace behaviorpack\script;

use behaviorpack\BehaviorPack;
use behaviorpack\BehaviorPackException;
use pocketmine\block\Block;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\utils\Utils;
use pocketmine\world\World;
use Throwable;
use function array_shift;
use function count;
use function is_array;
use function is_numeric;
use function is_string;
use function mt_rand;
use function shuffle;
use function str_contains;
use function strpos;
use function strtolower;
use function substr;

/**
 * Assembles the jigsaw structures of the "worldgen" folder of the behavior
 * packs: a start piece taken from a template pool, then pieces connected
 * through their jigsaw blocks up to the maximum depth, with the rule
 * processors applied.
 */
final class ScriptJigsaw{

	private const FACING_OFFSETS = [0 => [0, -1, 0], 1 => [0, 1, 0], 2 => [0, 0, -1], 3 => [0, 0, 1], 4 => [-1, 0, 0], 5 => [1, 0, 0]];
	private const FACING_NAMES = [2 => "north", 3 => "south", 4 => "west", 5 => "east"];
	private const NAME_FACINGS = ["north" => 2, "south" => 3, "west" => 4, "east" => 5];
	private const OPPOSITE = [0 => 1, 1 => 0, 2 => 3, 3 => 2, 4 => 5, 5 => 4];

	/** @var array<string, array<mixed>> */
	private array $structures = [];

	/** @var array<string, array<mixed>> */
	private array $pools = [];

	/** @var array<string, list<array<mixed>>> */
	private array $processors = [];

	public function __construct(
		private ScriptValues $values,
		private ScriptStructures $templates
	){}

	/**
	 * @param list<BehaviorPack> $packs
	 */
	public function registerPacks(array $packs) : void{
		foreach($packs as $pack){
			foreach(["worldgen/structures" => "minecraft:jigsaw", "worldgen/template_pools" => "minecraft:template_pool", "worldgen/processors" => "minecraft:processor_list"] as $directory => $root){
				foreach($pack->listFiles($directory) as $file){
					try{
						$json = BehaviorPack::readJson($file);
					}catch(BehaviorPackException){
						continue;
					}
					$data = $json[$root] ?? null;
					$identifier = is_array($data) ? ($data["description"]["identifier"] ?? null) : null;
					if(!is_string($identifier)){
						continue;
					}
					$identifier = strtolower($identifier);
					if($root === "minecraft:jigsaw"){
						$this->structures[$identifier] = $data;
					}elseif($root === "minecraft:template_pool"){
						$this->pools[$identifier] = $data;
					}else{
						$this->processors[$identifier] = is_array($data["processors"] ?? null) ? $data["processors"] : [];
					}
				}
			}
		}
	}

	/**
	 * Places a jigsaw structure of the worldgen folder.
	 */
	public function placeStructure(string $identifier, World $world, Vector3 $location, bool $ignoreStartHeight, bool $keepJigsaws) : AxisAlignedBB{
		$definition = $this->structures[strtolower($identifier)] ?? null;
		if($definition === null){
			throw new ScriptException("Unknown jigsaw structure: " . $identifier);
		}
		$pool = $definition["start_pool"] ?? null;
		if(!is_string($pool)){
			throw new ScriptException("The jigsaw structure " . $identifier . " has no start pool");
		}
		$y = (int) $location->y;
		if(!$ignoreStartHeight){
			$height = $definition["start_height"]["value"] ?? null;
			if(is_array($height) && is_numeric($height["absolute"] ?? null)){
				$y = (int) $height["absolute"];
			}elseif(is_array($height) && is_numeric($height["above_bottom"] ?? null)){
				$y = $world->getMinY() + (int) $height["above_bottom"];
			}
			if(($definition["heightmap_projection"] ?? null) === "world_surface" || ($definition["heightmap_projection"] ?? null) === "ocean_floor"){
				$world->loadChunk(((int) $location->x) >> 4, ((int) $location->z) >> 4);
				$top = $world->getHighestBlockAt((int) $location->x, (int) $location->z);
				$y = ($top ?? $world->getMinY()) + 1;
			}
		}
		$maxDepth = is_numeric($definition["max_depth"] ?? null) ? (int) $definition["max_depth"] : 7;
		return $this->assemble($pool, null, $world, new Vector3((int) $location->x, $y, (int) $location->z), $maxDepth, $keepJigsaws);
	}

	/**
	 * Places a piece of a template pool, connected through the named jigsaw
	 * when one is given.
	 */
	public function placePool(string $pool, ?string $targetJigsaw, World $world, Vector3 $location, int $maxDepth, bool $keepJigsaws) : AxisAlignedBB{
		if(!isset($this->pools[strtolower($pool)])){
			throw new ScriptException("Unknown template pool: " . $pool);
		}
		return $this->assemble($pool, $targetJigsaw, $world, $location, $maxDepth, $keepJigsaws);
	}

	private function assemble(string $pool, ?string $targetJigsaw, World $world, Vector3 $location, int $maxDepth, bool $keepJigsaws) : AxisAlignedBB{
		$start = $this->pickElement($pool);
		if($start === null){
			throw new ScriptException("The template pool " . $pool . " has no structure");
		}
		$rotation = [0, 90, 180, 270][mt_rand(0, 3)];
		$origin = $location->floor();
		if($targetJigsaw !== null){
			foreach($this->templates->jigsaws($start["location"]) as $jigsaw){
				if($jigsaw["name"] === $targetJigsaw){
					[$sx, , $sz] = $this->templates->size($start["location"]);
					[$tx, $tz] = ScriptStructures::transformPosition($jigsaw["x"], $jigsaw["z"], $sx, $sz, $rotation, "None");
					$origin = $origin->subtract($tx, $jigsaw["y"], $tz);
					break;
				}
			}
		}
		$placed = [];
		$queue = [[$start, (int) $origin->x, (int) $origin->y, (int) $origin->z, $rotation, 0]];
		$boxes = [];
		$bounds = null;
		while(count($queue) > 0){
			[$element, $x, $y, $z, $rotation, $depth] = array_shift($queue);
			$box = $this->box($element["location"], $x, $y, $z, $rotation);
			$boxes[] = $box;
			$bounds = $bounds === null ? $box : new AxisAlignedBB(\min($bounds->minX, $box->minX), \min($bounds->minY, $box->minY), \min($bounds->minZ, $box->minZ), \max($bounds->maxX, $box->maxX), \max($bounds->maxY, $box->maxY), \max($bounds->maxZ, $box->maxZ));
			$placed[] = [$element, $x, $y, $z, $rotation];
			if($depth >= $maxDepth){
				continue;
			}
			foreach($this->worldJigsaws($element["location"], $x, $y, $z, $rotation) as $jigsaw){
				if($jigsaw["pool"] === "minecraft:empty"){
					continue;
				}
				$connection = $this->connect($jigsaw, $boxes, $box);
				if($connection !== null){
					$queue[] = [$connection[0], $connection[1], $connection[2], $connection[3], $connection[4], $depth + 1];
					$boxes[] = $this->box($connection[0]["location"], $connection[1], $connection[2], $connection[3], $connection[4]);
				}
			}
		}
		foreach($placed as [$element, $x, $y, $z, $rotation]){
			$this->templates->place($element["location"], $world, new Vector3($x, $y, $z), $rotation, "None", true, true, 1.0, null, $this->processor($element["processors"], $world));
			foreach($this->worldJigsaws($element["location"], $x, $y, $z, $rotation) as $jigsaw){
				if($keepJigsaws){
					continue;
				}
				try{
					$world->setBlockAt($jigsaw["x"], $jigsaw["y"], $jigsaw["z"], $this->values->block($jigsaw["final"]), false);
				}catch(Throwable){
				}
			}
		}
		return $bounds ?? new AxisAlignedBB($origin->x, $origin->y, $origin->z, $origin->x + 1, $origin->y + 1, $origin->z + 1);
	}

	/**
	 * Finds a piece of the target pool of a jigsaw that fits its position
	 * without overlapping the pieces already planned.
	 *
	 * @param array{x: int, y: int, z: int, facing: int, name: string, target: string, pool: string, final: string, joint: string} $jigsaw
	 * @param list<AxisAlignedBB> $boxes
	 * @return array{0: array{location: string, processors: mixed}, 1: int, 2: int, 3: int, 4: int}|null
	 */
	private function connect(array $jigsaw, array $boxes, AxisAlignedBB $parent) : ?array{
		$offset = self::FACING_OFFSETS[$jigsaw["facing"]] ?? [0, 0, 1];
		$targetX = $jigsaw["x"] + $offset[0];
		$targetY = $jigsaw["y"] + $offset[1];
		$targetZ = $jigsaw["z"] + $offset[2];
		$wanted = self::OPPOSITE[$jigsaw["facing"]] ?? 2;
		$candidates = $this->elements($jigsaw["pool"]);
		foreach($candidates as $element){
			$rotations = [0, 90, 180, 270];
			shuffle($rotations);
			foreach($rotations as $rotation){
				foreach($this->templates->jigsaws($element["location"]) as $candidate){
					if($candidate["name"] !== $jigsaw["target"]){
						continue;
					}
					$facing = $this->rotateFacing($candidate["facing"], $rotation);
					if($facing !== $wanted){
						continue;
					}
					[$sx, , $sz] = $this->templates->size($element["location"]);
					[$tx, $tz] = ScriptStructures::transformPosition($candidate["x"], $candidate["z"], $sx, $sz, $rotation, "None");
					$x = $targetX - $tx;
					$y = $targetY - $candidate["y"];
					$z = $targetZ - $tz;
					$box = $this->box($element["location"], $x, $y, $z, $rotation)->contract(0.25, 0.25, 0.25);
					$overlaps = false;
					foreach($boxes as $other){
						if($other !== $parent && $other->intersectsWith($box)){
							$overlaps = true;
							break;
						}
					}
					if(!$overlaps){
						return [$element, $x, $y, $z, $rotation];
					}
				}
			}
		}
		return null;
	}

	private function rotateFacing(int $facing, int $rotation) : int{
		if(!isset(self::FACING_NAMES[$facing])){
			return $facing;
		}
		return self::NAME_FACINGS[ScriptStructures::rotateCardinal(self::FACING_NAMES[$facing], (int) ($rotation / 90), "None")];
	}

	/**
	 * @return list<array{x: int, y: int, z: int, facing: int, name: string, target: string, pool: string, final: string, joint: string}>
	 */
	private function worldJigsaws(string $location, int $x, int $y, int $z, int $rotation) : array{
		[$sx, , $sz] = $this->templates->size($location);
		$result = [];
		foreach($this->templates->jigsaws($location) as $jigsaw){
			[$tx, $tz] = ScriptStructures::transformPosition($jigsaw["x"], $jigsaw["z"], $sx, $sz, $rotation, "None");
			$jigsaw["x"] = $x + $tx;
			$jigsaw["y"] = $y + $jigsaw["y"];
			$jigsaw["z"] = $z + $tz;
			$jigsaw["facing"] = $this->rotateFacing($jigsaw["facing"], $rotation);
			$result[] = $jigsaw;
		}
		return $result;
	}

	private function box(string $location, int $x, int $y, int $z, int $rotation) : AxisAlignedBB{
		[$sx, $sy, $sz] = $this->templates->size($location);
		$width = $rotation === 90 || $rotation === 270 ? $sz : $sx;
		$length = $rotation === 90 || $rotation === 270 ? $sx : $sz;
		return new AxisAlignedBB($x, $y, $z, $x + $width, $y + $sy, $z + $length);
	}

	/**
	 * @return array{location: string, processors: mixed}|null
	 */
	private function pickElement(string $pool) : ?array{
		$elements = $this->elements($pool);
		return $elements[0] ?? null;
	}

	/**
	 * Returns the structures of a pool in a weighted random order, followed
	 * by those of its fallback pool.
	 *
	 * @return list<array{location: string, processors: mixed}>
	 */
	private function elements(string $pool, int $depth = 0) : array{
		$data = $this->pools[strtolower($pool)] ?? null;
		if($data === null || $depth > 4){
			return [];
		}
		$weighted = [];
		foreach(is_array($data["elements"] ?? null) ? $data["elements"] : [] as $entry){
			if(!is_array($entry) || !is_array($entry["element"] ?? null)){
				continue;
			}
			$element = $entry["element"];
			$location = $element["location"] ?? null;
			if(($element["element_type"] ?? "") !== "minecraft:single_pool_element" || !is_string($location)){
				continue;
			}
			$id = str_contains($location, ":") ? $location : (($slash = strpos($location, "/")) === false ? "mystructure:" . $location : substr($location, 0, $slash) . ":" . substr($location, $slash + 1));
			if(!$this->templates->exists($id)){
				continue;
			}
			$weight = is_numeric($entry["weight"] ?? null) ? (float) $entry["weight"] : 1.0;
			$weighted[] = [["location" => $id, "processors" => $element["processors"] ?? null], $weight * Utils::getRandomFloat()];
		}
		\usort($weighted, fn(array $a, array $b) : int => $b[1] <=> $a[1]);
		$result = [];
		foreach($weighted as [$element]){
			$result[] = $element;
		}
		$fallback = $data["fallback"] ?? null;
		if(is_string($fallback) && $fallback !== "minecraft:empty"){
			foreach($this->elements($fallback, $depth + 1) as $element){
				$result[] = $element;
			}
		}
		return $result;
	}

	/**
	 * Builds the block processor of a pool element.
	 */
	private function processor(mixed $reference, World $world) : ?\Closure{
		$processors = is_string($reference) ? ($this->processors[strtolower($reference)] ?? null) : (is_array($reference) ? ($reference["processors"] ?? $reference) : null);
		if(!is_array($processors) || count($processors) === 0){
			return null;
		}
		return function(Block $block, int $x, int $y, int $z) use ($processors, $world) : ?Block{
			foreach($processors as $processor){
				if(!is_array($processor) || ($processor["processor_type"] ?? null) !== "minecraft:rule"){
					continue;
				}
				foreach(is_array($processor["rules"] ?? null) ? $processor["rules"] : [] as $rule){
					if(!is_array($rule)){
						continue;
					}
					if(!$this->predicate($rule["input_predicate"] ?? null, $block) || !$this->predicate($rule["location_predicate"] ?? null, $world->getBlockAt($x, $y, $z))){
						continue;
					}
					$output = $rule["output_state"] ?? null;
					if(is_array($output) && is_string($output["name"] ?? null)){
						try{
							return $this->values->block(["\$p" => $output["name"], "s" => is_array($output["states"] ?? null) ? $output["states"] : []]);
						}catch(Throwable){
							return $block;
						}
					}
				}
			}
			return $block;
		};
	}

	private function predicate(mixed $predicate, Block $block) : bool{
		if(!is_array($predicate)){
			return true;
		}
		$type = $predicate["predicate_type"] ?? "minecraft:always_true";
		if($type === "minecraft:always_true"){
			return true;
		}
		$name = $predicate["block"] ?? null;
		$matches = is_string($name) && ScriptValues::namespaced($name) === $this->values->blockTypeId($block);
		if($type === "minecraft:random_block_match"){
			return $matches && Utils::getRandomFloat() < (float) ($predicate["probability"] ?? 1.0);
		}
		return $matches;
	}
}
