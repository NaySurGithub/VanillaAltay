<?php

declare(strict_types=1);

namespace behaviorpack\script;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\entity\Entity;
use pocketmine\entity\effect\Effect;
use pocketmine\entity\effect\StringToEffectParser;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Vector3;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\Tag;
use pocketmine\nbt\TreeRoot;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use pocketmine\world\World;
use stdClass;
use Throwable;
use function array_values;
use function base64_decode;
use function base64_encode;
use function count;
use function is_array;
use function is_bool;
use function is_int;
use function is_numeric;
use function is_string;
use function spl_object_id;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strtolower;
use function substr;

/**
 * Converts server objects to the values exchanged with the script runtime,
 * and back.
 */
final class ScriptValues{

	public const OVERWORLD = "minecraft:overworld";
	public const NETHER = "minecraft:nether";
	public const THE_END = "minecraft:the_end";

	/** @var array<int, string>|null */
	private ?array $effectNames = null;

	public function __construct(
		private Server $server
	){}

	public function getServer() : Server{
		return $this->server;
	}

	public static function namespaced(string $id) : string{
		$id = strtolower($id);
		return str_contains($id, ":") ? $id : "minecraft:" . $id;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function entityRef(Entity $entity) : array{
		$ref = ["\$e" => $entity->getId(), "t" => $this->entityTypeId($entity)];
		if($entity instanceof Player){
			$ref["n"] = $entity->getName();
		}
		return $ref;
	}

	public function entityTypeId(Entity $entity) : string{
		if($entity instanceof Player){
			return "minecraft:player";
		}
		return self::namespaced($entity::getNetworkTypeId());
	}

	public function findEntity(mixed $handle) : ?Entity{
		if(is_array($handle)){
			$handle = $handle["\$e"] ?? null;
		}
		if(!is_numeric($handle)){
			return null;
		}
		$entity = $this->server->getWorldManager()->findEntity((int) $handle);
		if($entity === null || $entity->isClosed() || $entity->isFlaggedForDespawn()){
			return null;
		}
		if($entity instanceof Player && !$entity->isConnected()){
			return null;
		}
		return $entity;
	}

	public function entity(mixed $handle) : Entity{
		$entity = $this->findEntity($handle);
		if($entity === null){
			throw new ScriptException("The entity is no longer valid");
		}
		return $entity;
	}

	public function player(mixed $handle) : Player{
		$entity = $this->entity($handle);
		if(!$entity instanceof Player){
			throw new ScriptException("The entity is not a player");
		}
		return $entity;
	}

	/**
	 * Returns the storage scope of an entity: players are keyed by UUID so
	 * their data survives reconnections, other entities by runtime id.
	 */
	public function scope(Entity $entity) : string{
		if($entity instanceof Player){
			return "p:" . $entity->getUniqueId()->toString();
		}
		return "e:" . $entity->getId();
	}

	public function dimensionId(World $world) : string{
		$default = $this->server->getWorldManager()->getDefaultWorld();
		if($default === $world){
			return self::OVERWORLD;
		}
		$name = strtolower($world->getFolderName());
		if($name === "nether" || $name === "dim-1" || str_ends_with($name, "_nether")){
			return self::NETHER;
		}
		if($name === "the_end" || $name === "end" || $name === "dim1" || str_ends_with($name, "_the_end")){
			return self::THE_END;
		}
		return $world->getFolderName();
	}

	public function world(mixed $dimension) : World{
		if(is_array($dimension)){
			$dimension = $dimension["\$d"] ?? null;
		}
		if(!is_string($dimension)){
			throw new ScriptException("Invalid dimension");
		}
		$id = strtolower($dimension);
		if(!str_contains($id, ":") && $this->server->getWorldManager()->getWorldByName($dimension) !== null){
			return $this->server->getWorldManager()->getWorldByName($dimension);
		}
		$id = self::namespaced($id);
		foreach($this->server->getWorldManager()->getWorlds() as $world){
			if($this->dimensionId($world) === $id){
				return $world;
			}
		}
		if($id === self::OVERWORLD){
			$default = $this->server->getWorldManager()->getDefaultWorld();
			if($default !== null){
				return $default;
			}
		}
		throw new ScriptException("Unknown dimension: " . $dimension);
	}

	public function vector(mixed $value) : Vector3{
		if(!is_array($value) || !isset($value["x"], $value["y"], $value["z"]) || !is_numeric($value["x"]) || !is_numeric($value["y"]) || !is_numeric($value["z"])){
			throw new ScriptException("Invalid Vector3");
		}
		return new Vector3((float) $value["x"], (float) $value["y"], (float) $value["z"]);
	}

	/**
	 * @return array{x: float, y: float, z: float}
	 */
	public function vectorOut(Vector3 $vector) : array{
		return ["x" => (float) $vector->x, "y" => (float) $vector->y, "z" => (float) $vector->z];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function blockRef(Block $block) : array{
		$position = $block->getPosition();
		return [
			"\$b" => [$position->getFloorX(), $position->getFloorY(), $position->getFloorZ()],
			"d" => $this->dimensionId($position->getWorld())
		];
	}

	public function blockTypeId(Block $block) : string{
		try{
			return GlobalBlockStateHandlers::getSerializer()->serializeBlock($block)->getName();
		}catch(Throwable){
			return "minecraft:unknown";
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	public function permutation(Block $block) : array{
		try{
			$data = GlobalBlockStateHandlers::getSerializer()->serializeBlock($block);
		}catch(Throwable){
			return ["\$p" => "minecraft:unknown", "s" => new stdClass()];
		}
		$states = [];
		foreach($data->getStates() as $name => $tag){
			$states[$name] = self::stateValue($tag);
		}
		return ["\$p" => $data->getName(), "s" => count($states) === 0 ? new stdClass() : $states];
	}

	/**
	 * Builds a block from a type id or a permutation value, completing the
	 * missing states with the default ones of the type.
	 */
	public function block(mixed $value) : Block{
		$states = [];
		if(is_array($value)){
			$typeId = $value["\$p"] ?? null;
			$states = is_array($value["s"] ?? null) ? $value["s"] : [];
		}else{
			$typeId = $value;
		}
		if(!is_string($typeId)){
			throw new ScriptException("Invalid block type");
		}
		return $this->resolveBlock(self::namespaced($typeId), $states);
	}

	/**
	 * @param array<string, mixed> $states
	 */
	public function resolveBlock(string $typeId, array $states) : Block{
		if($typeId === "minecraft:air"){
			return VanillaBlocks::AIR();
		}
		$base = null;
		$item = StringToItemParser::getInstance()->parse($typeId);
		if($item !== null){
			$candidate = $item->getBlock();
			if($this->blockTypeId($candidate) === $typeId){
				$base = $candidate;
			}
		}
		$serializer = GlobalBlockStateHandlers::getSerializer();
		$deserializer = GlobalBlockStateHandlers::getDeserializer();
		if($base === null){
			try{
				$base = $deserializer->deserializeBlock(BlockStateData::current($typeId, []));
			}catch(Throwable){
				throw new ScriptException("Unknown block type: " . $typeId);
			}
		}
		if(count($states) === 0){
			return $base;
		}
		$data = $serializer->serializeBlock($base);
		$tags = $data->getStates();
		foreach($states as $name => $stateValue){
			$template = $tags[$name] ?? null;
			if($template === null){
				throw new ScriptException("Block " . $typeId . " has no state " . $name);
			}
			$tags[$name] = self::stateTag($template, $stateValue);
		}
		try{
			return $deserializer->deserializeBlock(BlockStateData::current($data->getName(), $tags));
		}catch(Throwable){
			throw new ScriptException("Invalid states for block " . $typeId);
		}
	}

	private static function stateValue(Tag $tag) : mixed{
		if($tag instanceof ByteTag){
			return $tag->getValue() !== 0;
		}
		if($tag instanceof IntTag || $tag instanceof StringTag){
			return $tag->getValue();
		}
		return null;
	}

	private static function stateTag(Tag $template, mixed $value) : Tag{
		if($template instanceof ByteTag){
			return new ByteTag(($value === true || $value === 1 || $value === "true") ? 1 : 0);
		}
		if($template instanceof IntTag){
			if(!is_numeric($value)){
				throw new ScriptException("Expected a number for an integer block state");
			}
			return new IntTag((int) $value);
		}
		if(!is_string($value)){
			throw new ScriptException("Expected a string for a string block state");
		}
		return new StringTag($value);
	}

	public function itemTypeId(Item $item) : string{
		try{
			return GlobalItemDataHandlers::getSerializer()->serializeType($item)->getName();
		}catch(Throwable){
			return "minecraft:unknown";
		}
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function item(Item $item) : ?array{
		if($item->isNull()){
			return null;
		}
		$data = [
			"t" => $this->itemTypeId($item),
			"c" => $item->getCount(),
			"m" => $item->getMaxStackSize(),
			"l" => array_values($item->getLore())
		];
		if($item->hasCustomName()){
			$data["n"] = $item->getCustomName();
		}
		if($item instanceof Durable){
			$data["d"] = $item->getDamage();
			$data["md"] = $item->getMaxDurability();
		}
		try{
			$data["nbt"] = base64_encode((new LittleEndianNbtSerializer())->write(new TreeRoot($item->nbtSerialize())));
		}catch(Throwable){
			unset($data["nbt"]);
		}
		return ["\$i" => $data];
	}

	public function decodeItem(mixed $value) : Item{
		if($value === null){
			return VanillaBlocks::AIR()->asItem();
		}
		$data = is_array($value) && is_array($value["\$i"] ?? null) ? $value["\$i"] : null;
		if($data === null || !is_string($data["t"] ?? null)){
			throw new ScriptException("Invalid ItemStack");
		}
		$typeId = self::namespaced($data["t"]);
		$item = null;
		if(is_string($data["nbt"] ?? null)){
			try{
				$decoded = base64_decode($data["nbt"], true);
				if($decoded !== false){
					$candidate = Item::nbtDeserialize((new LittleEndianNbtSerializer())->read($decoded)->mustGetCompoundTag());
					if($this->itemTypeId($candidate) === $typeId){
						$item = $candidate;
					}
				}
			}catch(Throwable){
				$item = null;
			}
		}
		if($item === null){
			$item = StringToItemParser::getInstance()->parse($typeId);
			if($item === null){
				throw new ScriptException("Unknown item type: " . $typeId);
			}
		}
		if(is_int($data["c"] ?? null)){
			$item->setCount($data["c"]);
		}
		if(is_string($data["n"] ?? null) && $data["n"] !== ""){
			$item->setCustomName($data["n"]);
		}else{
			$item->clearCustomName();
		}
		if(is_array($data["l"] ?? null)){
			$lore = [];
			foreach($data["l"] as $line){
				if(is_string($line)){
					$lore[] = $line;
				}
			}
			$item->setLore($lore);
		}
		if($item instanceof Durable && is_int($data["d"] ?? null)){
			$item->setDamage($data["d"]);
		}
		return $item;
	}

	public function effect(mixed $type) : Effect{
		if(is_array($type)){
			$type = $type["id"] ?? null;
		}
		if(!is_string($type)){
			throw new ScriptException("Invalid effect type");
		}
		$effect = StringToEffectParser::getInstance()->parse($type);
		if($effect === null){
			throw new ScriptException("Unknown effect type: " . $type);
		}
		return $effect;
	}

	public function effectName(Effect $effect) : string{
		if($this->effectNames === null){
			$this->effectNames = [];
			$parser = StringToEffectParser::getInstance();
			foreach($parser->getKnownAliases() as $alias){
				$alias = (string) $alias;
				if(str_contains($alias, " ")){
					continue;
				}
				$candidate = $parser->parse($alias);
				if($candidate !== null && !isset($this->effectNames[spl_object_id($candidate)])){
					$this->effectNames[spl_object_id($candidate)] = $alias;
				}
			}
		}
		$name = $this->effectNames[spl_object_id($effect)] ?? "unknown";
		return str_starts_with($name, "minecraft:") ? substr($name, 10) : $name;
	}

	public static function bool(mixed $value, bool $default = false) : bool{
		return is_bool($value) ? $value : $default;
	}
}
