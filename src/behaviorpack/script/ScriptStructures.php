<?php

declare(strict_types=1);

namespace behaviorpack\script;

use behaviorpack\BehaviorPack;
use pocketmine\block\Block;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\tile\TileFactory;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Human;
use pocketmine\math\Vector3;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\utils\Utils;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\World;
use Throwable;
use function array_keys;
use function count;
use function file_get_contents;
use function file_put_contents;
use function in_array;
use function intdiv;
use function is_dir;
use function is_file;
use function max;
use function min;
use function mkdir;
use function str_contains;
use function str_replace;
use function strlen;
use function strtolower;
use function substr;
use function unlink;

/**
 * The structures of the scripts: those shipped in the "structures" folder
 * of the behavior packs, those saved to the world, and those kept in
 * memory. They use the .mcstructure format.
 */
final class ScriptStructures{

	public const MODE_MEMORY = "Memory";
	public const MODE_WORLD = "World";

	/** @var array<string, string> */
	private array $packFiles = [];

	/** @var array<string, array{size: array{int, int, int}, palette: list<Block>, blocks: list<int>, tiles: array<int, CompoundTag>, entities: list<CompoundTag>, mode: string}> */
	private array $structures = [];

	public function __construct(
		private string $directory
	){}

	/**
	 * @param list<BehaviorPack> $packs
	 */
	public function registerPacks(array $packs) : void{
		foreach($packs as $pack){
			foreach($pack->listFiles("structures", "mcstructure") as $file){
				$relative = substr($pack->relativePath($file), strlen("structures/"));
				$relative = substr($relative, 0, -strlen(".mcstructure"));
				$slash = \strpos($relative, "/");
				$id = $slash === false ? "mystructure:" . $relative : substr($relative, 0, $slash) . ":" . substr($relative, $slash + 1);
				$this->packFiles[strtolower($id)] = $file;
			}
		}
	}

	public static function normalize(string $id) : string{
		$id = strtolower($id);
		return str_contains($id, ":") ? $id : "mystructure:" . $id;
	}

	private function worldFile(string $id) : string{
		return $this->directory . "/" . str_replace(":", "/", $id) . ".mcstructure";
	}

	/**
	 * @return array{size: array{int, int, int}, palette: list<Block>, blocks: list<int>, tiles: array<int, CompoundTag>, entities: list<CompoundTag>, mode: string}|null
	 */
	public function find(string $id) : ?array{
		$id = self::normalize($id);
		if(isset($this->structures[$id])){
			return $this->structures[$id];
		}
		$file = is_file($this->worldFile($id)) ? $this->worldFile($id) : ($this->packFiles[$id] ?? null);
		if($file === null){
			return null;
		}
		$data = @file_get_contents($file);
		if($data === false){
			return null;
		}
		try{
			$structure = $this->decode($data);
		}catch(Throwable $e){
			throw new ScriptException("Structure " . $id . " is invalid: " . $e->getMessage());
		}
		$structure["mode"] = self::MODE_WORLD;
		return $this->structures[$id] = $structure;
	}

	public function get(string $id) : array{
		$structure = $this->find($id);
		if($structure === null){
			throw new ScriptException("Structure " . $id . " does not exist");
		}
		return $structure;
	}

	/**
	 * @return list<string>
	 */
	public function worldIds() : array{
		$ids = [];
		foreach($this->structures as $id => $structure){
			if($structure["mode"] === self::MODE_WORLD){
				$ids[] = $id;
			}
		}
		foreach(array_keys($this->packFiles) as $id){
			if(!in_array($id, $ids, true)){
				$ids[] = $id;
			}
		}
		return $ids;
	}

	public function exists(string $id) : bool{
		return $this->find($id) !== null;
	}

	/**
	 * @param array{int, int, int} $size
	 */
	public function createEmpty(string $id, array $size, string $mode) : void{
		$id = self::normalize($id);
		if($this->find($id) !== null){
			throw new ScriptException("Structure " . $id . " already exists");
		}
		$volume = max(1, $size[0]) * max(1, $size[1]) * max(1, $size[2]);
		$this->structures[$id] = [
			"size" => [max(1, $size[0]), max(1, $size[1]), max(1, $size[2])],
			"palette" => [],
			"blocks" => \array_fill(0, $volume, -1),
			"tiles" => [],
			"entities" => [],
			"mode" => $mode
		];
		$this->persist($id);
	}

	public function createFromWorld(string $id, World $world, Vector3 $from, Vector3 $to, bool $includeBlocks, bool $includeEntities, string $mode) : void{
		$id = self::normalize($id);
		if($this->find($id) !== null){
			throw new ScriptException("Structure " . $id . " already exists");
		}
		$minX = (int) min($from->x, $to->x);
		$minY = (int) min($from->y, $to->y);
		$minZ = (int) min($from->z, $to->z);
		$maxX = (int) max($from->x, $to->x);
		$maxY = (int) max($from->y, $to->y);
		$maxZ = (int) max($from->z, $to->z);
		$size = [$maxX - $minX + 1, $maxY - $minY + 1, $maxZ - $minZ + 1];
		$palette = [];
		$paletteIndex = [];
		$blocks = [];
		$tiles = [];
		for($x = 0; $x < $size[0]; $x++){
			for($y = 0; $y < $size[1]; $y++){
				for($z = 0; $z < $size[2]; $z++){
					$index = ($x * $size[1] + $y) * $size[2] + $z;
					if(!$includeBlocks || !$world->isInWorld($minX + $x, $minY + $y, $minZ + $z)){
						$blocks[$index] = -1;
						continue;
					}
					$block = $world->getBlockAt($minX + $x, $minY + $y, $minZ + $z);
					$stateId = $block->getStateId();
					if(!isset($paletteIndex[$stateId])){
						$paletteIndex[$stateId] = count($palette);
						$palette[] = $block;
					}
					$blocks[$index] = $paletteIndex[$stateId];
					$tile = $world->getTileAt($minX + $x, $minY + $y, $minZ + $z);
					if($tile !== null){
						$tiles[$index] = $tile->saveNBT();
					}
				}
			}
		}
		$entities = [];
		if($includeEntities){
			$box = new \pocketmine\math\AxisAlignedBB($minX, $minY, $minZ, $maxX + 1, $maxY + 1, $maxZ + 1);
			foreach($world->getNearbyEntities($box) as $entity){
				if($entity instanceof Human){
					continue;
				}
				$entities[] = $entity->saveNBT();
			}
		}
		$this->structures[$id] = [
			"size" => $size,
			"palette" => $palette,
			"blocks" => $blocks,
			"tiles" => $tiles,
			"entities" => $this->relativeEntities($entities, $minX, $minY, $minZ),
			"mode" => $mode
		];
		$this->persist($id);
	}

	/**
	 * @param list<CompoundTag> $entities
	 * @return list<CompoundTag>
	 */
	private function relativeEntities(array $entities, int $x, int $y, int $z) : array{
		$result = [];
		foreach($entities as $nbt){
			$pos = $nbt->getListTag(Entity::TAG_POS);
			if($pos === null || count($pos) !== 3){
				continue;
			}
			$values = $pos->getValue();
			$nbt->setTag(Entity::TAG_POS, new ListTag([
				new DoubleTag((float) $values[0]->getValue() - $x),
				new DoubleTag((float) $values[1]->getValue() - $y),
				new DoubleTag((float) $values[2]->getValue() - $z)
			]));
			$result[] = $nbt;
		}
		return $result;
	}

	public function delete(string $id) : bool{
		$id = self::normalize($id);
		$existed = $this->find($id) !== null;
		unset($this->structures[$id]);
		if(is_file($this->worldFile($id))){
			@unlink($this->worldFile($id));
		}
		return $existed;
	}

	public function saveAs(string $id, string $newId, string $mode) : void{
		$structure = $this->get($id);
		$newId = self::normalize($newId);
		$structure["mode"] = $mode;
		$this->structures[$newId] = $structure;
		$this->persist($newId);
	}

	public function setMode(string $id, string $mode) : void{
		$id = self::normalize($id);
		$this->get($id);
		$this->structures[$id]["mode"] = $mode;
		$this->persist($id);
	}

	public function getBlock(string $id, int $x, int $y, int $z) : ?Block{
		$structure = $this->get($id);
		[$sx, $sy, $sz] = $structure["size"];
		if($x < 0 || $y < 0 || $z < 0 || $x >= $sx || $y >= $sy || $z >= $sz){
			throw new ScriptException("The location is outside of the structure");
		}
		$paletteIndex = $structure["blocks"][($x * $sy + $y) * $sz + $z] ?? -1;
		return $paletteIndex < 0 ? null : ($structure["palette"][$paletteIndex] ?? null);
	}

	public function setBlock(string $id, int $x, int $y, int $z, ?Block $block) : void{
		$id = self::normalize($id);
		$structure = $this->get($id);
		[$sx, $sy, $sz] = $structure["size"];
		if($x < 0 || $y < 0 || $z < 0 || $x >= $sx || $y >= $sy || $z >= $sz){
			throw new ScriptException("The location is outside of the structure");
		}
		$index = ($x * $sy + $y) * $sz + $z;
		if($block === null){
			$structure["blocks"][$index] = -1;
		}else{
			$paletteIndex = null;
			foreach($structure["palette"] as $i => $candidate){
				if($candidate->getStateId() === $block->getStateId()){
					$paletteIndex = $i;
					break;
				}
			}
			if($paletteIndex === null){
				$paletteIndex = count($structure["palette"]);
				$structure["palette"][] = $block;
			}
			$structure["blocks"][$index] = $paletteIndex;
		}
		unset($structure["tiles"][$index]);
		$this->structures[$id] = $structure;
		$this->persist($id);
	}

	/**
	 * Places a structure. Rotation is one of 0, 90, 180 and 270, mirror one
	 * of "None", "X", "Z" and "XZ".
	 */
	public function place(string $id, World $world, Vector3 $origin, int $rotation, string $mirror, bool $includeBlocks, bool $includeEntities, float $integrity, ?int $seed, ?\Closure $processor = null) : void{
		[$sx, , $sz] = $this->get($id)["size"];
		$span = max($sx, $sz);
		$missing = [];
		for($cx = ((int) $origin->x - $span) >> 4; $cx <= ((int) $origin->x + $span) >> 4; $cx++){
			for($cz = ((int) $origin->z - $span) >> 4; $cz <= ((int) $origin->z + $span) >> 4; $cz++){
				if(!$world->isChunkGenerated($cx, $cz)){
					$missing[] = [$cx, $cz];
				}
			}
		}
		if(count($missing) === 0){
			$this->placeNow($id, $world, $origin, $rotation, $mirror, $includeBlocks, $includeEntities, $integrity, $seed, $processor);
			return;
		}
		$remaining = count($missing);
		foreach($missing as [$cx, $cz]){
			$world->orderChunkPopulation($cx, $cz, null)->onCompletion(
				function() use (&$remaining, $id, $world, $origin, $rotation, $mirror, $includeBlocks, $includeEntities, $integrity, $seed, $processor) : void{
					if(--$remaining === 0 && $world->isLoaded()){
						$this->placeNow($id, $world, $origin, $rotation, $mirror, $includeBlocks, $includeEntities, $integrity, $seed, $processor);
					}
				},
				function() use (&$remaining) : void{
					$remaining = -1;
				}
			);
		}
	}

	private function placeNow(string $id, World $world, Vector3 $origin, int $rotation, string $mirror, bool $includeBlocks, bool $includeEntities, float $integrity, ?int $seed, ?\Closure $processor) : void{
		$structure = $this->get($id);
		[$sx, $sy, $sz] = $structure["size"];
		$random = $seed === null ? null : new \pocketmine\utils\Random($seed);
		$ox = (int) $origin->x;
		$oy = (int) $origin->y;
		$oz = (int) $origin->z;
		if($includeBlocks){
			$rotated = [];
			foreach($structure["palette"] as $i => $block){
				$rotated[$i] = self::transformBlock($block, $rotation, $mirror);
			}
			for($x = 0; $x < $sx; $x++){
				for($y = 0; $y < $sy; $y++){
					for($z = 0; $z < $sz; $z++){
						$index = ($x * $sy + $y) * $sz + $z;
						$paletteIndex = $structure["blocks"][$index] ?? -1;
						if($paletteIndex < 0 || !isset($rotated[$paletteIndex])){
							continue;
						}
						if($integrity < 1.0 && ($random === null ? Utils::getRandomFloat() : $random->nextFloat()) > $integrity){
							continue;
						}
						[$tx, $tz] = self::transformPosition($x, $z, $sx, $sz, $rotation, $mirror);
						$wx = $ox + $tx;
						$wy = $oy + $y;
						$wz = $oz + $tz;
						if(!$world->isInWorld($wx, $wy, $wz)){
							continue;
						}
						$world->loadChunk($wx >> 4, $wz >> 4);
						if(!$world->isChunkGenerated($wx >> 4, $wz >> 4)){
							continue;
						}
						$block = $processor === null ? $rotated[$paletteIndex] : $processor($rotated[$paletteIndex], $wx, $wy, $wz);
						if($block === null){
							continue;
						}
						$world->setBlockAt($wx, $wy, $wz, $block, false);
						$tileNbt = $structure["tiles"][$index] ?? null;
						if($tileNbt !== null){
							$copy = clone $tileNbt;
							$copy->setInt("x", $wx)->setInt("y", $wy)->setInt("z", $wz);
							$existing = $world->getTileAt($wx, $wy, $wz);
							if($existing !== null){
								$world->removeTile($existing);
							}
							try{
								$tile = TileFactory::getInstance()->createFromData($world, $copy);
								if($tile !== null){
									$world->addTile($tile);
								}
							}catch(Throwable){
							}
						}
					}
				}
			}
		}
		if($includeEntities){
			foreach($structure["entities"] as $nbt){
				$this->spawnEntity($world, clone $nbt, $ox, $oy, $oz, $sx, $sz, $rotation, $mirror);
			}
		}
	}

	private function spawnEntity(World $world, CompoundTag $nbt, int $ox, int $oy, int $oz, int $sx, int $sz, int $rotation, string $mirror) : void{
		$pos = $nbt->getListTag(Entity::TAG_POS);
		if($pos === null || count($pos) !== 3){
			return;
		}
		$values = $pos->getValue();
		$x = (float) $values[0]->getValue();
		$y = (float) $values[1]->getValue();
		$z = (float) $values[2]->getValue();
		[$tx, $tz] = self::transformPoint($x, $z, $sx, $sz, $rotation, $mirror);
		$nbt->setTag(Entity::TAG_POS, new ListTag([new DoubleTag($ox + $tx), new DoubleTag($oy + $y), new DoubleTag($oz + $tz)]));
		$rot = $nbt->getListTag(Entity::TAG_ROTATION);
		$yaw = $rot !== null && count($rot) === 2 ? (float) $rot->getValue()[0]->getValue() : 0.0;
		$pitch = $rot !== null && count($rot) === 2 ? (float) $rot->getValue()[1]->getValue() : 0.0;
		$nbt->setTag(Entity::TAG_ROTATION, new ListTag([new FloatTag($yaw + $rotation), new FloatTag($pitch)]));
		$nbt->removeTag("UniqueID");
		if($nbt->getTag(EntityFactory::TAG_IDENTIFIER) === null){
			return;
		}
		try{
			$entity = EntityFactory::getInstance()->createFromData($world, $nbt);
			$entity?->spawnToAll();
		}catch(Throwable){
		}
	}

	/**
	 * @return array{int, int}
	 */
	public static function transformPosition(int $x, int $z, int $sx, int $sz, int $rotation, string $mirror) : array{
		[$fx, $fz] = self::transformPoint($x + 0.5, $z + 0.5, $sx, $sz, $rotation, $mirror);
		return [(int) \floor($fx), (int) \floor($fz)];
	}

	/**
	 * @return array{float, float}
	 */
	private static function transformPoint(float $x, float $z, int $sx, int $sz, int $rotation, string $mirror) : array{
		if($mirror === "X" || $mirror === "XZ"){
			$z = $sz - $z;
		}
		if($mirror === "Z" || $mirror === "XZ"){
			$x = $sx - $x;
		}
		return match($rotation){
			90 => [$sz - $z, $x],
			180 => [$sx - $x, $sz - $z],
			270 => [$z, $sx - $x],
			default => [$x, $z]
		};
	}

	private const CARDINALS = ["north", "east", "south", "west"];

	/**
	 * Rotates and mirrors the directional states of a block.
	 */
	private static function transformBlock(Block $block, int $rotation, string $mirror) : Block{
		if($rotation === 0 && $mirror === "None"){
			return $block;
		}
		try{
			$data = GlobalBlockStateHandlers::getSerializer()->serializeBlock($block);
		}catch(Throwable){
			return $block;
		}
		$states = $data->getStates();
		$turns = (int) ($rotation / 90);
		$changed = false;
		foreach(["minecraft:cardinal_direction", "minecraft:facing_direction"] as $name){
			$tag = $states[$name] ?? null;
			if($tag instanceof StringTag && in_array($tag->getValue(), self::CARDINALS, true)){
				$states[$name] = new StringTag(self::rotateCardinal($tag->getValue(), $turns, $mirror));
				$changed = true;
			}
		}
		$tag = $states["facing_direction"] ?? null;
		if($tag instanceof IntTag && $tag->getValue() >= 2 && $tag->getValue() <= 5){
			$name = [2 => "north", 3 => "south", 4 => "west", 5 => "east"][$tag->getValue()];
			$rotated = self::rotateCardinal($name, $turns, $mirror);
			$states["facing_direction"] = new IntTag(["north" => 2, "south" => 3, "west" => 4, "east" => 5][$rotated]);
			$changed = true;
		}
		$tag = $states["direction"] ?? null;
		if($tag instanceof IntTag && $tag->getValue() >= 0 && $tag->getValue() <= 3){
			$name = ["south", "west", "north", "east"][$tag->getValue()];
			$rotated = self::rotateCardinal($name, $turns, $mirror);
			$states["direction"] = new IntTag(\array_search($rotated, ["south", "west", "north", "east"], true));
			$changed = true;
		}
		$tag = $states["pillar_axis"] ?? null;
		if($tag instanceof StringTag && $turns % 2 === 1 && ($tag->getValue() === "x" || $tag->getValue() === "z")){
			$states["pillar_axis"] = new StringTag($tag->getValue() === "x" ? "z" : "x");
			$changed = true;
		}
		if(!$changed){
			return $block;
		}
		try{
			return GlobalBlockStateHandlers::getDeserializer()->deserializeBlock(new \pocketmine\data\bedrock\block\BlockStateData($data->getName(), $states, $data->getVersion()));
		}catch(Throwable){
			return $block;
		}
	}

	public static function rotateCardinal(string $direction, int $turns, string $mirror) : string{
		if(($mirror === "X" || $mirror === "XZ") && ($direction === "north" || $direction === "south")){
			$direction = $direction === "north" ? "south" : "north";
		}
		if(($mirror === "Z" || $mirror === "XZ") && ($direction === "east" || $direction === "west")){
			$direction = $direction === "east" ? "west" : "east";
		}
		$index = (int) \array_search($direction, self::CARDINALS, true);
		return self::CARDINALS[($index + $turns) % 4];
	}

	private function persist(string $id) : void{
		$structure = $this->structures[$id] ?? null;
		if($structure === null || $structure["mode"] !== self::MODE_WORLD){
			return;
		}
		$file = $this->worldFile($id);
		$directory = \dirname($file);
		if(!is_dir($directory)){
			@mkdir($directory, 0777, true);
		}
		@file_put_contents($file, $this->encode($structure));
	}

	/**
	 * @return array{size: array{int, int, int}, palette: list<Block>, blocks: list<int>, tiles: array<int, CompoundTag>, entities: list<CompoundTag>, mode: string}
	 */
	private function decode(string $data) : array{
		$root = (new LittleEndianNbtSerializer())->read($data)->mustGetCompoundTag();
		$sizeTag = $root->getListTag("size");
		if($sizeTag === null || count($sizeTag) !== 3){
			throw new ScriptException("missing size");
		}
		$sizeValues = $sizeTag->getValue();
		$size = [(int) $sizeValues[0]->getValue(), (int) $sizeValues[1]->getValue(), (int) $sizeValues[2]->getValue()];
		$structure = $root->getCompoundTag("structure");
		if($structure === null){
			throw new ScriptException("missing structure");
		}
		$layers = $structure->getListTag("block_indices");
		$indices = [];
		if($layers !== null && count($layers) > 0){
			$first = $layers->getValue()[0];
			if($first instanceof ListTag){
				foreach($first->getValue() as $tag){
					$indices[] = (int) $tag->getValue();
				}
			}
		}
		$default = $structure->getCompoundTag("palette")?->getCompoundTag("default");
		$palette = [];
		$jigsaw = [];
		$registry = RuntimeBlockStateRegistry::getInstance();
		foreach($default?->getListTag("block_palette")?->getValue() ?? [] as $entry){
			if(!$entry instanceof CompoundTag){
				$palette[] = VanillaBlocks::AIR();
				continue;
			}
			if($entry->getString("name", "") === "minecraft:jigsaw"){
				$jigsaw[count($palette)] = $entry->getCompoundTag("states")?->getInt("facing_direction", 2) ?? 2;
				$palette[] = VanillaBlocks::AIR();
				continue;
			}
			try{
				$stateData = GlobalBlockStateHandlers::getUpgrader()->upgradeBlockStateNbt($entry);
				$palette[] = $registry->fromStateId(GlobalBlockStateHandlers::getDeserializer()->deserialize($stateData));
			}catch(Throwable){
				$palette[] = VanillaBlocks::AIR();
			}
		}
		$tiles = [];
		foreach($default?->getCompoundTag("block_position_data")?->getValue() ?? [] as $key => $entry){
			if($entry instanceof CompoundTag){
				$tile = $entry->getCompoundTag("block_entity_data");
				if($tile !== null){
					$tiles[(int) $key] = $tile;
				}
			}
		}
		$entities = [];
		foreach($structure->getListTag("entities")?->getValue() ?? [] as $entity){
			if($entity instanceof CompoundTag){
				$entities[] = $this->importEntity($entity, $root);
			}
		}
		return ["size" => $size, "palette" => $palette, "blocks" => $indices, "tiles" => $tiles, "entities" => $entities, "mode" => self::MODE_WORLD, "jigsaw" => $jigsaw];
	}

	/**
	 * Returns the jigsaw blocks of a structure, in structure coordinates.
	 *
	 * @return list<array{x: int, y: int, z: int, facing: int, name: string, target: string, pool: string, final: string, joint: string}>
	 */
	public function jigsaws(string $id) : array{
		$structure = $this->get($id);
		$jigsaw = $structure["jigsaw"] ?? [];
		if(count($jigsaw) === 0){
			return [];
		}
		[$sx, $sy, $sz] = $structure["size"];
		$result = [];
		foreach($structure["blocks"] as $index => $paletteIndex){
			if(!isset($jigsaw[$paletteIndex])){
				continue;
			}
			$tile = $structure["tiles"][$index] ?? null;
			$x = intdiv($index, $sy * $sz);
			$y = intdiv($index % ($sy * $sz), $sz);
			$z = $index % $sz;
			$result[] = [
				"x" => $x,
				"y" => $y,
				"z" => $z,
				"facing" => $jigsaw[$paletteIndex],
				"name" => $tile?->getString("name", "minecraft:empty") ?? "minecraft:empty",
				"target" => $tile?->getString("target", "minecraft:empty") ?? "minecraft:empty",
				"pool" => $tile?->getString("target_pool", "minecraft:empty") ?? "minecraft:empty",
				"final" => $tile?->getString("final_state", "minecraft:air") ?? "minecraft:air",
				"joint" => $tile?->getString("joint", "rollable") ?? "rollable"
			];
		}
		return $result;
	}

	/**
	 * Makes the position of a structure entity relative to the structure.
	 */
	private function importEntity(CompoundTag $entity, CompoundTag $root) : CompoundTag{
		$origin = $root->getListTag("structure_world_origin");
		$ox = 0.0;
		$oy = 0.0;
		$oz = 0.0;
		if($origin !== null && count($origin) === 3){
			$values = $origin->getValue();
			$ox = (float) $values[0]->getValue();
			$oy = (float) $values[1]->getValue();
			$oz = (float) $values[2]->getValue();
		}
		$pos = $entity->getListTag(Entity::TAG_POS);
		if($pos !== null && count($pos) === 3){
			$values = $pos->getValue();
			$entity->setTag(Entity::TAG_POS, new ListTag([
				new DoubleTag((float) $values[0]->getValue() - $ox),
				new DoubleTag((float) $values[1]->getValue() - $oy),
				new DoubleTag((float) $values[2]->getValue() - $oz)
			]));
		}
		return $entity;
	}

	/**
	 * @param array{size: array{int, int, int}, palette: list<Block>, blocks: list<int>, tiles: array<int, CompoundTag>, entities: list<CompoundTag>, mode: string} $structure
	 */
	private function encode(array $structure) : string{
		$palette = [];
		foreach($structure["palette"] as $block){
			try{
				$palette[] = GlobalBlockStateHandlers::getSerializer()->serializeBlock($block)->toVanillaNbt();
			}catch(Throwable){
				$palette[] = GlobalBlockStateHandlers::getUnknownBlockStateData()->toVanillaNbt();
			}
		}
		$layer0 = [];
		$layer1 = [];
		foreach($structure["blocks"] as $index){
			$layer0[] = new IntTag($index);
			$layer1[] = new IntTag(-1);
		}
		$positionData = CompoundTag::create();
		foreach($structure["tiles"] as $index => $tile){
			$positionData->setTag((string) $index, CompoundTag::create()->setTag("block_entity_data", $tile));
		}
		$root = CompoundTag::create()
			->setInt("format_version", 1)
			->setTag("size", new ListTag([new IntTag($structure["size"][0]), new IntTag($structure["size"][1]), new IntTag($structure["size"][2])]))
			->setTag("structure_world_origin", new ListTag([new IntTag(0), new IntTag(0), new IntTag(0)]))
			->setTag("structure", CompoundTag::create()
				->setTag("block_indices", new ListTag([new ListTag($layer0, NBT::TAG_Int), new ListTag($layer1, NBT::TAG_Int)], NBT::TAG_List))
				->setTag("entities", new ListTag($structure["entities"], NBT::TAG_Compound))
				->setTag("palette", CompoundTag::create()->setTag("default", CompoundTag::create()
					->setTag("block_palette", new ListTag($palette, NBT::TAG_Compound))
					->setTag("block_position_data", $positionData)
				))
			);
		return (new LittleEndianNbtSerializer())->write(new TreeRoot($root));
	}

	/**
	 * Returns the size of a structure.
	 *
	 * @return array{int, int, int}
	 */
	public function size(string $id) : array{
		return $this->get($id)["size"];
	}

	public function mode(string $id) : string{
		return $this->get($id)["mode"];
	}
}
