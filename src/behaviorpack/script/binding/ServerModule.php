<?php

declare(strict_types=1);

namespace behaviorpack\script\binding;

use behaviorpack\script\js\Interpreter;
use behaviorpack\script\js\JsArray;
use behaviorpack\script\js\JsCallable;
use behaviorpack\script\js\JsNull;
use behaviorpack\script\js\JsObject;
use behaviorpack\script\js\JsSymbol;
use behaviorpack\script\js\JsThrow;
use behaviorpack\script\js\NativeFunction;
use behaviorpack\script\ScriptException;
use behaviorpack\script\ScriptRuntime;
use stdClass;
use function array_is_list;
use function array_key_exists;
use function count;
use function floor;
use function get_object_vars;
use function in_array;
use function intdiv;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function json_encode;
use function max;
use function min;
use function str_contains;
use function strlen;
use function strtolower;
use function substr;
use const JSON_PARTIAL_OUTPUT_ON_ERROR;

/**
 * The @minecraft/server module: its classes, enums, world and system. Host
 * objects keep an array with a "kind" key as their payload; every call to
 * the server goes through ScriptApi with the value encoding of ScriptValues.
 */
final class ServerModule{

	public const DIRECTIONS = ["Down", "Up", "North", "South", "West", "East"];

	private const BLOCK_HOOKS = ["onPlayerInteract", "onPlace", "onPlayerBreak"];
	private const ITEM_HOOKS = ["onUse", "onUseOn"];

	private Interpreter $js;

	public HostClass $blockType;
	public HostClass $itemType;
	public HostClass $entityType;
	public HostClass $effectType;
	public HostClass $effect;
	public HostClass $permutation;
	public HostClass $itemStack;
	public HostClass $component;
	public HostClass $itemComponent;
	public HostClass $durability;
	public HostClass $container;
	public HostClass $entityComponent;
	public HostClass $attributeComponent;
	public HostClass $health;
	public HostClass $inventory;
	public HostClass $equippable;
	public HostClass $blockComponent;
	public HostClass $blockInventory;
	public HostClass $entity;
	public HostClass $player;
	public HostClass $screenDisplay;
	public HostClass $block;
	public HostClass $dimension;
	public HostClass $signal;
	public HostClass $scoreboard;
	public HostClass $objective;
	public HostClass $identity;
	public HostClass $blockRegistry;
	public HostClass $itemRegistry;
	public HostClass $commandRegistry;
	public HostClass $world;
	public HostClass $system;

	public JsObject $worldObject;
	public JsObject $systemObject;

	/** @var array<string, mixed> */
	private array $exports = [];

	/** @var array<int, JsObject> */
	private array $entities = [];

	/** @var array<string, JsObject> */
	private array $dimensions = [];

	/** @var array<string, HostClass> */
	private array $eventClasses = [];

	/** @var array<string, JsObject> */
	private array $signals = [];

	/** @var array<string, JsObject> */
	private array $stubs = [];

	/** @var array{next: int, objectives: array<string, array{displayName: string, scores: array<string, int>}>, participants: array<string, array{id: int, displayName: string, type: string}>} */
	public array $scoreboardData = ["next" => 1, "objectives" => [], "participants" => []];

	public function __construct(
		private ScriptRuntime $runtime,
		private ClassFactory $f
	){
		$this->js = $runtime->js;
		$this->loadScoreboard($runtime->storage->getScoreboard());
		$this->defineTypes();
		$this->defineItems();
		$this->defineContainers();
		$this->defineEntities();
		$this->defineBlocks();
		$this->defineScoreboard();
		$this->defineEvents();
		$this->defineWorld();
		$this->defineSystem();
		$this->defineEnums();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function exports() : array{
		return $this->exports;
	}

	private function export(string $name, mixed $value) : void{
		$this->exports[$name] = $value instanceof HostClass ? $value->constructor : $value;
	}

	/**
	 * Returns a stand-in for an export that is not implemented: using it in
	 * any way records it.
	 */
	public function stub(string $name) : JsObject{
		if(isset($this->stubs[$name])){
			return $this->stubs[$name];
		}
		$js = $this->js;
		$stub = new NativeFunction($js->functionPrototype, $name, 0, function(mixed $thisValue, array $args) use ($name) : mixed{
			$this->runtime->recordMissing($name);
			return null;
		}, function(array $args, JsObject $newTarget) use ($name, $js) : JsObject{
			$this->runtime->recordMissing($name);
			return $js->newObject();
		});
		$stub->miss = function(JsObject $receiver, string $key) use ($name) : mixed{
			if(!$this->f->isIgnored($key)){
				$this->runtime->recordMissing($name);
			}
			return null;
		};
		$js->defineHidden($stub, "prototype", $js->newObject());
		$this->stubs[$name] = $stub;
		return $stub;
	}

	private function api(string $method, mixed ...$args) : mixed{
		return $this->toJs($this->runtime->api->handle($method, $args));
	}

	private function raw(string $method, mixed ...$args) : mixed{
		return $this->runtime->api->handle($method, $args);
	}

	public static function namespaced(string $id) : string{
		$id = strtolower($id);
		return str_contains($id, ":") ? $id : "minecraft:" . $id;
	}

	private function string(mixed $value) : string{
		return $this->js->toString($value);
	}

	/**
	 * Flattens a string, a RawMessage or an array of them.
	 */
	public function text(mixed $message) : string{
		if($message === null || $message instanceof JsNull){
			return "";
		}
		if(is_string($message)){
			return $message;
		}
		if($message instanceof JsArray){
			$result = "";
			foreach($message->items as $item){
				$result .= $this->text($item);
			}
			return $result;
		}
		if($message instanceof JsObject && !$message instanceof JsCallable){
			$result = "";
			$text = $this->js->get($message, "text");
			if(is_string($text)){
				$result .= $text;
			}
			$translate = $this->js->get($message, "translate");
			if(is_string($translate)){
				$result .= "%" . $translate;
			}
			$raw = $this->js->get($message, "rawtext");
			if($raw instanceof JsArray){
				$result .= $this->text($raw);
			}
			return $result;
		}
		return $this->js->toString($message);
	}

	/**
	 * @return array{x: float, y: float, z: float}
	 */
	public function vector(mixed $value) : array{
		if(!$value instanceof JsObject){
			$this->js->throwError("TypeError", "Expected a Vector3");
		}
		return [
			"x" => (float) $this->js->toNumber($this->js->get($value, "x")),
			"y" => (float) $this->js->toNumber($this->js->get($value, "y")),
			"z" => (float) $this->js->toNumber($this->js->get($value, "z"))
		];
	}

	public function toJs(mixed $value) : mixed{
		if($value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value) || $value instanceof JsObject || $value instanceof JsNull || $value instanceof JsSymbol){
			return $value;
		}
		if($value instanceof stdClass){
			$object = $this->js->newObject();
			foreach(get_object_vars($value) as $key => $item){
				$object->props[$key] = $this->toJs($item);
			}
			return $object;
		}
		if(!is_array($value)){
			return null;
		}
		if(isset($value['$e'])){
			return $this->entityObject($value);
		}
		if(isset($value['$b'])){
			return $this->blockObject((string) $value["d"], (int) $value['$b'][0], (int) $value['$b'][1], (int) $value['$b'][2]);
		}
		if(array_key_exists('$p', $value)){
			return $this->permutationObject((string) $value['$p'], is_array($value["s"] ?? null) ? $value["s"] : []);
		}
		if(isset($value['$i'])){
			return $this->itemObject($value['$i']);
		}
		if(isset($value['$d'])){
			return $this->dimensionObject((string) $value['$d']);
		}
		if(array_is_list($value)){
			$items = [];
			foreach($value as $item){
				$items[] = $this->toJs($item);
			}
			return $this->js->newArray($items);
		}
		$object = $this->js->newObject();
		foreach($value as $key => $item){
			$object->props[$key] = $this->toJs($item);
		}
		return $object;
	}

	public function fromJs(mixed $value, int $depth = 0) : mixed{
		if($value === null || $value instanceof JsNull || $value instanceof JsSymbol || $value instanceof JsCallable){
			return null;
		}
		if(!$value instanceof JsObject){
			return $value;
		}
		if(is_array($value->host) && isset($value->host["kind"])){
			$host = $value->host;
			switch($host["kind"]){
				case "entity":
					return ['$e' => $host["id"]];
				case "block":
					return ['$b' => [$host["x"], $host["y"], $host["z"]], "d" => $host["d"]];
				case "permutation":
					return ['$p' => $host["type"], "s" => $host["states"]];
				case "item":
					return ['$i' => $this->itemData($host)];
				case "dimension":
					return ['$d' => $host["id"]];
				case "container":
					return ['$c' => $host["ref"]];
				case "type":
					return $host["id"];
				case "effecttype":
					return $host["id"];
			}
		}
		if($depth > 16){
			return null;
		}
		if($value instanceof JsArray){
			$items = [];
			foreach($value->items as $item){
				$items[] = $this->fromJs($item, $depth + 1);
			}
			return $items;
		}
		$result = [];
		foreach($this->js->ownKeys($value) as $key){
			$result[$key] = $this->fromJs($this->js->getFrom($value, $key, $value), $depth + 1);
		}
		return $result;
	}

	/**
	 * @param array<string, mixed> $ref
	 */
	public function entityObject(array $ref) : JsObject{
		$id = (int) $ref['$e'];
		$typeId = (string) ($ref["t"] ?? "minecraft:unknown");
		$existing = $this->entities[$id] ?? null;
		if($existing !== null && $existing->host["typeId"] === $typeId){
			return $existing;
		}
		if(count($this->entities) > 20000){
			$this->entities = [];
		}
		$isPlayer = $typeId === "minecraft:player";
		$entity = $this->f->instance($isPlayer ? $this->player : $this->entity, [
			"kind" => "entity",
			"id" => $id,
			"typeId" => $typeId,
			"name" => (string) ($ref["n"] ?? "")
		]);
		$this->entities[$id] = $entity;
		return $entity;
	}

	public function forgetEntity(int $id) : void{
		unset($this->entities[$id]);
	}

	public function dimensionObject(string $id) : JsObject{
		$key = str_contains($id, ":") || !in_array($id, ["overworld", "nether", "the_end"], true) ? $id : "minecraft:" . $id;
		return $this->dimensions[$key] ??= $this->f->instance($this->dimension, ["kind" => "dimension", "id" => $key]);
	}

	public function blockObject(string $dimension, int $x, int $y, int $z) : JsObject{
		return $this->f->instance($this->block, ["kind" => "block", "d" => $dimension, "x" => $x, "y" => $y, "z" => $z]);
	}

	/**
	 * @param array<string, mixed> $states
	 */
	public function permutationObject(string $type, array $states) : JsObject{
		return $this->f->instance($this->permutation, ["kind" => "permutation", "type" => $type, "states" => $states]);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function itemObject(array $data) : JsObject{
		return $this->f->instance($this->itemStack, [
			"kind" => "item",
			"t" => (string) ($data["t"] ?? "minecraft:air"),
			"c" => (int) ($data["c"] ?? 1),
			"m" => (int) ($data["m"] ?? 64),
			"n" => isset($data["n"]) && is_string($data["n"]) ? $data["n"] : null,
			"l" => is_array($data["l"] ?? null) ? $data["l"] : [],
			"d" => isset($data["d"]) && is_int($data["d"]) ? $data["d"] : null,
			"md" => (int) ($data["md"] ?? 0),
			"nbt" => isset($data["nbt"]) && is_string($data["nbt"]) ? $data["nbt"] : null
		]);
	}

	/**
	 * @param array<string, mixed> $host
	 * @return array<string, mixed>
	 */
	private function itemData(array $host) : array{
		$data = ["t" => $host["t"], "c" => $host["c"], "l" => $host["l"]];
		if($host["n"] !== null){
			$data["n"] = $host["n"];
		}
		if($host["d"] !== null){
			$data["d"] = $host["d"];
		}
		if($host["nbt"] !== null){
			$data["nbt"] = $host["nbt"];
		}
		return $data;
	}

	private function entityId(mixed $thisValue) : int{
		return $this->f->host($thisValue, "entity")["id"];
	}

	private function typeName(mixed $value) : string{
		if($value instanceof JsObject && is_array($value->host) && isset($value->host["id"]) && in_array($value->host["kind"] ?? null, ["type", "effecttype"], true)){
			return $value->host["id"];
		}
		return $this->string($value);
	}

	private function defineTypes() : void{
		$f = $this->f;
		$typeGetter = function(mixed $thisValue) : mixed{
			return $this->f->host($thisValue, "type")["id"];
		};
		$this->blockType = $f->define("BlockType");
		$f->getter($this->blockType, "id", $typeGetter);
		$this->itemType = $f->define("ItemType");
		$f->getter($this->itemType, "id", $typeGetter);
		$this->entityType = $f->define("EntityType");
		$f->getter($this->entityType, "id", $typeGetter);
		$this->effectType = $f->define("EffectType");
		$f->method($this->effectType, "getName", function(mixed $thisValue, array $args) : mixed{
			return $this->f->host($thisValue, "effecttype")["id"];
		});
		foreach(["BlockType" => $this->blockType, "ItemType" => $this->itemType, "EntityType" => $this->entityType, "EffectType" => $this->effectType] as $name => $class){
			$this->export($name, $class);
		}

		$blockTypes = $f->define("BlockTypes");
		$f->staticMethod($blockTypes, "get", function(mixed $thisValue, array $args) : mixed{
			return $this->f->instance($this->blockType, ["kind" => "type", "id" => self::namespaced($this->string($args[0] ?? ""))]);
		});
		$this->export("BlockTypes", $blockTypes);
		$itemTypes = $f->define("ItemTypes");
		$f->staticMethod($itemTypes, "get", function(mixed $thisValue, array $args) : mixed{
			$id = self::namespaced($this->string($args[0] ?? ""));
			try{
				$info = $this->raw("item.info", $id);
			}catch(ScriptException){
				return null;
			}
			return $this->f->instance($this->itemType, ["kind" => "type", "id" => (string) ($info["t"] ?? $id)]);
		});
		$this->export("ItemTypes", $itemTypes);
		$entityTypes = $f->define("EntityTypes");
		$f->staticMethod($entityTypes, "get", function(mixed $thisValue, array $args) : mixed{
			return $this->f->instance($this->entityType, ["kind" => "type", "id" => self::namespaced($this->string($args[0] ?? ""))]);
		});
		$this->export("EntityTypes", $entityTypes);
		$effectTypes = $f->define("EffectTypes");
		$f->staticMethod($effectTypes, "get", function(mixed $thisValue, array $args) : mixed{
			$name = $this->string($args[0] ?? "");
			return $this->f->instance($this->effectType, ["kind" => "effecttype", "id" => str_contains($name, ":") ? substr($name, (int) \strpos($name, ":") + 1) : $name]);
		});
		$this->export("EffectTypes", $effectTypes);

		$this->effect = $f->define("Effect");
		foreach(["typeId" => "t", "duration" => "d", "amplifier" => "a", "displayName" => "n"] as $name => $key){
			$f->getter($this->effect, $name, function(mixed $thisValue) use ($key) : mixed{
				return $this->f->host($thisValue, "effect")[$key];
			});
		}
		$f->getter($this->effect, "isValid", function(mixed $thisValue) : mixed{
			$host = $this->f->host($thisValue, "effect");
			return $this->raw("ent.valid", $host["entity"]) === true && $this->raw("ent.effect.get", $host["entity"], $host["t"]) !== null;
		});
		$this->export("Effect", $this->effect);

		$this->permutation = $f->define("BlockPermutation");
		$f->staticMethod($this->permutation, "resolve", function(mixed $thisValue, array $args) : mixed{
			$states = $args[1] ?? null;
			return $this->api("block.resolve", self::namespaced($this->string($args[0] ?? "")), $states instanceof JsObject ? $this->fromJs($states) : []);
		}, 2);
		$f->getter($this->permutation, "type", function(mixed $thisValue) : mixed{
			return $this->f->instance($this->blockType, ["kind" => "type", "id" => $this->f->host($thisValue, "permutation")["type"]]);
		});
		$f->method($this->permutation, "getState", function(mixed $thisValue, array $args) : mixed{
			return $this->f->host($thisValue, "permutation")["states"][$this->string($args[0] ?? "")] ?? null;
		});
		$f->method($this->permutation, "getAllStates", function(mixed $thisValue, array $args) : mixed{
			$object = $this->js->newObject();
			foreach($this->f->host($thisValue, "permutation")["states"] as $key => $value){
				$object->props[$key] = $value;
			}
			return $object;
		});
		$f->method($this->permutation, "withState", function(mixed $thisValue, array $args) : mixed{
			$host = $this->f->host($thisValue, "permutation");
			$states = $host["states"];
			$states[$this->string($args[0] ?? "")] = $this->fromJs($args[1] ?? null);
			return $this->api("block.resolve", $host["type"], $states);
		}, 2);
		$f->method($this->permutation, "matches", function(mixed $thisValue, array $args) : mixed{
			$host = $this->f->host($thisValue, "permutation");
			return $this->matchesPermutation($host["type"], $host["states"], $args[0] ?? null, $args[1] ?? null);
		}, 2);
		$f->method($this->permutation, "getItemStack", function(mixed $thisValue, array $args) : mixed{
			return $this->api("block.item", $this->fromJs($thisValue), $args[0] ?? 1);
		});
		$this->missingMethods($this->permutation, ["getTags" => [], "hasTag" => false]);
		$this->export("BlockPermutation", $this->permutation);
	}

	/**
	 * @param array<string, mixed> $states
	 */
	private function matchesPermutation(string $type, array $states, mixed $name, mixed $expected) : bool{
		if(self::namespaced($this->string($name)) !== $type){
			return false;
		}
		if($expected instanceof JsObject){
			foreach($this->js->ownKeys($expected) as $key){
				$value = $this->fromJs($this->js->getFrom($expected, $key, $expected));
				if(($states[$key] ?? null) !== $value && !(is_numeric($value) && is_numeric($states[$key] ?? null) && $value == $states[$key])){
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Defines methods that are not implemented: they are recorded and return
	 * the given default.
	 *
	 * @param array<string, mixed> $methods
	 */
	private function missingMethods(HostClass $class, array $methods) : void{
		foreach($methods as $name => $default){
			$label = $class->name . "." . $name;
			$this->f->method($class, $name, function(mixed $thisValue, array $args) use ($label, $default) : mixed{
				$this->runtime->recordMissing($label);
				return is_array($default) ? $this->js->newArray($default) : $default;
			});
		}
	}

	private function defineItems() : void{
		$f = $this->f;
		$js = $this->js;
		$this->itemStack = $f->define("ItemStack", null, function(array $args, JsObject $newTarget) use ($js) : JsObject{
			$type = $args[0] ?? null;
			$amount = $args[1] ?? 1;
			$id = $this->typeName($type);
			if(!is_int($amount) || $amount < 1 || $amount > 255){
				$js->throwError("RangeError", "Amount must be an integer between 1 and 255");
			}
			$info = $this->raw("item.info", self::namespaced($id));
			$item = $this->itemObject(["t" => $info["t"], "c" => $amount, "m" => $info["m"], "l" => [], "d" => $info["md"] > 0 ? 0 : null, "md" => $info["md"]]);
			$item->proto = $js->prototypeFor($newTarget, $this->itemStack->prototype);
			return $item;
		}, 2);
		$item = $this->itemStack;
		$f->getter($item, "typeId", function(mixed $thisValue) : mixed{
			return $this->f->host($thisValue, "item")["t"];
		});
		$f->getter($item, "type", function(mixed $thisValue) : mixed{
			return $this->f->instance($this->itemType, ["kind" => "type", "id" => $this->f->host($thisValue, "item")["t"]]);
		});
		$f->getter($item, "amount", function(mixed $thisValue) : mixed{
			return $this->f->host($thisValue, "item")["c"];
		}, function(mixed $thisValue, mixed $value) use ($js) : void{
			$this->f->host($thisValue, "item");
			if(!is_int($value) || $value < 1 || $value > 255){
				$js->throwError("RangeError", "Amount must be an integer between 1 and 255");
			}
			$thisValue->host["c"] = $value;
		});
		$f->getter($item, "maxAmount", function(mixed $thisValue) : mixed{
			return $this->f->host($thisValue, "item")["m"];
		});
		$f->getter($item, "isStackable", function(mixed $thisValue) : mixed{
			return $this->f->host($thisValue, "item")["m"] > 1;
		});
		$f->getter($item, "nameTag", function(mixed $thisValue) : mixed{
			return $this->f->host($thisValue, "item")["n"];
		}, function(mixed $thisValue, mixed $value) : void{
			$this->f->host($thisValue, "item");
			$thisValue->host["n"] = ($value === null || $value === "") ? null : $this->string($value);
		});
		$f->getter($item, "keepOnDeath", function(mixed $thisValue) : mixed{
			return false;
		});
		$f->getter($item, "lockMode", function(mixed $thisValue) : mixed{
			return "none";
		});
		$f->method($item, "getLore", function(mixed $thisValue, array $args) use ($js) : mixed{
			return $js->newArray($this->f->host($thisValue, "item")["l"]);
		});
		$f->method($item, "setLore", function(mixed $thisValue, array $args) : mixed{
			$this->f->host($thisValue, "item");
			$lore = [];
			$list = $args[0] ?? null;
			if($list instanceof JsArray){
				foreach($list->items as $line){
					$lore[] = $this->text($line);
				}
			}
			$thisValue->host["l"] = $lore;
			return null;
		}, 1);
		$f->method($item, "clone", function(mixed $thisValue, array $args) : mixed{
			$host = $this->f->host($thisValue, "item");
			return $this->f->instance($this->itemStack, $host);
		});
		$f->method($item, "matches", function(mixed $thisValue, array $args) : mixed{
			return self::namespaced($this->string($args[0] ?? "")) === $this->f->host($thisValue, "item")["t"];
		}, 1);
		$f->method($item, "isStackableWith", function(mixed $thisValue, array $args) : mixed{
			$a = $this->f->host($thisValue, "item");
			$other = $args[0] ?? null;
			if(!$other instanceof JsObject || ($other->host["kind"] ?? null) !== "item"){
				return false;
			}
			$b = $other->host;
			return $a["m"] > 1 && $a["t"] === $b["t"] && $a["n"] === $b["n"] && $a["d"] === $b["d"] && $a["l"] === $b["l"];
		}, 1);
		$f->method($item, "getComponent", function(mixed $thisValue, array $args) : mixed{
			$host = $this->f->host($thisValue, "item");
			$id = self::namespaced($this->string($args[0] ?? ""));
			if($id === "minecraft:durability"){
				return $host["md"] > 0 ? $this->f->instance($this->durability, ["kind" => "component", "owner" => $thisValue, "typeId" => $id]) : null;
			}
			$this->runtime->recordMissing("ItemStack.getComponent(" . $id . ")");
			return null;
		}, 1);
		$f->method($item, "hasComponent", function(mixed $thisValue, array $args) : mixed{
			$host = $this->f->host($thisValue, "item");
			return self::namespaced($this->string($args[0] ?? "")) === "minecraft:durability" && $host["md"] > 0;
		}, 1);
		$f->method($item, "getComponents", function(mixed $thisValue, array $args) use ($js) : mixed{
			$host = $this->f->host($thisValue, "item");
			if($host["md"] <= 0){
				return $js->newArray();
			}
			return $js->newArray([$this->f->instance($this->durability, ["kind" => "component", "owner" => $thisValue, "typeId" => "minecraft:durability"])]);
		});
		$this->missingMethods($item, ["getTags" => [], "hasTag" => false, "getDynamicProperty" => null, "setDynamicProperty" => null, "getCanDestroy" => [], "getCanPlaceOn" => []]);
		$this->export("ItemStack", $item);

		$this->component = $f->define("Component");
		$f->getter($this->component, "typeId", function(mixed $thisValue) : mixed{
			return $this->f->host($thisValue, "component")["typeId"];
		});
		$f->getter($this->component, "isValid", function(mixed $thisValue) : mixed{
			$owner = $this->f->host($thisValue, "component")["owner"];
			if($owner instanceof JsObject && ($owner->host["kind"] ?? null) === "entity"){
				return $this->raw("ent.valid", $owner->host["id"]) === true;
			}
			return true;
		});
		$this->export("Component", $this->component);
		$this->itemComponent = $f->define("ItemComponent", $this->component);
		$this->export("ItemComponent", $this->itemComponent);
		$this->durability = $f->define("ItemDurabilityComponent", $this->itemComponent);
		$f->staticValue($this->durability, "componentId", "minecraft:durability");
		$f->getter($this->durability, "damage", function(mixed $thisValue) : mixed{
			$owner = $this->f->host($thisValue, "component")["owner"];
			return $owner->host["d"] ?? 0;
		}, function(mixed $thisValue, mixed $value) : void{
			$owner = $this->f->host($thisValue, "component")["owner"];
			$owner->host["d"] = (int) max(0, min($owner->host["md"], floor((float) $this->js->toNumber($value))));
		});
		$f->getter($this->durability, "maxDurability", function(mixed $thisValue) : mixed{
			return $this->f->host($thisValue, "component")["owner"]->host["md"];
		});
		$f->method($this->durability, "getDamageChance", function(mixed $thisValue, array $args) : mixed{
			$level = (int) $this->js->toNumber($args[0] ?? 0);
			return Interpreter::intOrFloat(100 / ($level + 1));
		});
		$f->method($this->durability, "getDamageChanceRange", function(mixed $thisValue, array $args) : mixed{
			return $this->toJs(["min" => 0, "max" => 100]);
		});
		$this->export("ItemDurabilityComponent", $this->durability);
	}

	private function defineContainers() : void{
		$f = $this->f;
		$js = $this->js;
		$this->container = $f->define("Container");
		$container = $this->container;
		$ref = function(mixed $thisValue) : array{
			return $this->f->host($thisValue, "container")["ref"];
		};
		$f->getter($container, "isValid", function(mixed $thisValue) use ($ref) : mixed{
			return $this->raw("cont.valid", ['$c' => $ref($thisValue)]);
		});
		$f->getter($container, "size", function(mixed $thisValue) use ($ref) : mixed{
			return $this->raw("cont.size", ['$c' => $ref($thisValue)]);
		});
		$f->getter($container, "emptySlotsCount", function(mixed $thisValue) use ($ref) : mixed{
			return $this->raw("cont.empty", ['$c' => $ref($thisValue)]);
		});
		$f->method($container, "getItem", function(mixed $thisValue, array $args) use ($ref) : mixed{
			return $this->api("cont.get", ['$c' => $ref($thisValue)], $args[0] ?? 0);
		}, 1);
		$f->method($container, "setItem", function(mixed $thisValue, array $args) use ($ref) : mixed{
			$this->raw("cont.set", ['$c' => $ref($thisValue)], $args[0] ?? 0, $this->fromJs($args[1] ?? null));
			return null;
		}, 2);
		$f->method($container, "addItem", function(mixed $thisValue, array $args) use ($ref) : mixed{
			return $this->api("cont.add", ['$c' => $ref($thisValue)], $this->fromJs($args[0] ?? null));
		}, 1);
		$f->method($container, "clearAll", function(mixed $thisValue, array $args) use ($ref) : mixed{
			$this->raw("cont.clear", ['$c' => $ref($thisValue)]);
			return null;
		});
		$f->method($container, "moveItem", function(mixed $thisValue, array $args) use ($ref, $js) : mixed{
			$item = $this->raw("cont.get", ['$c' => $ref($thisValue)], $args[0] ?? 0);
			if($item === null){
				return null;
			}
			$this->raw("cont.set", ['$c' => $ref($args[2] ?? null)], $args[1] ?? 0, $item);
			$this->raw("cont.set", ['$c' => $ref($thisValue)], $args[0] ?? 0, null);
			return null;
		}, 3);
		$f->method($container, "swapItems", function(mixed $thisValue, array $args) use ($ref) : mixed{
			$first = $this->raw("cont.get", ['$c' => $ref($thisValue)], $args[0] ?? 0);
			$second = $this->raw("cont.get", ['$c' => $ref($args[2] ?? null)], $args[1] ?? 0);
			$this->raw("cont.set", ['$c' => $ref($thisValue)], $args[0] ?? 0, $second);
			$this->raw("cont.set", ['$c' => $ref($args[2] ?? null)], $args[1] ?? 0, $first);
			return null;
		}, 3);
		$f->method($container, "transferItem", function(mixed $thisValue, array $args) use ($ref) : mixed{
			$item = $this->raw("cont.get", ['$c' => $ref($thisValue)], $args[0] ?? 0);
			if($item === null){
				return null;
			}
			$leftover = $this->raw("cont.add", ['$c' => $ref($args[1] ?? null)], $item);
			$this->raw("cont.set", ['$c' => $ref($thisValue)], $args[0] ?? 0, $leftover);
			return $this->toJs($leftover);
		}, 2);
		$this->missingMethods($container, ["getSlot" => null, "contains" => false, "find" => null, "firstEmptySlot" => null, "firstItem" => null]);
		$this->export("Container", $container);
	}

	private function entityDynamicProperties(HostClass $class, \Closure $owner) : void{
		$f = $this->f;
		$f->method($class, "getDynamicProperty", function(mixed $thisValue, array $args) use ($owner) : mixed{
			return $this->api("dp.get", $owner($thisValue), $this->string($args[0] ?? ""));
		}, 1);
		$f->method($class, "setDynamicProperty", function(mixed $thisValue, array $args) use ($owner) : mixed{
			$value = $args[1] ?? null;
			if(!($value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value) || $value instanceof JsObject)){
				$this->js->throwError("TypeError", "Dynamic property values must be boolean, number, string or Vector3");
			}
			$stored = $value instanceof JsObject ? $this->vector($value) : $value;
			$this->raw("dp.set", $owner($thisValue), $this->string($args[0] ?? ""), $stored);
			return null;
		}, 2);
		$f->method($class, "getDynamicPropertyIds", function(mixed $thisValue, array $args) use ($owner) : mixed{
			return $this->api("dp.ids", $owner($thisValue));
		});
		$f->method($class, "clearDynamicProperties", function(mixed $thisValue, array $args) use ($owner) : mixed{
			$this->raw("dp.clear", $owner($thisValue));
			return null;
		});
		$f->method($class, "getDynamicPropertyTotalByteCount", function(mixed $thisValue, array $args) use ($owner) : mixed{
			$total = 0;
			foreach($this->raw("dp.ids", $owner($thisValue)) as $id){
				$total += strlen((string) $id) + strlen((string) json_encode($this->raw("dp.get", $owner($thisValue), $id), JSON_PARTIAL_OUTPUT_ON_ERROR));
			}
			return $total;
		});
	}

	private function defineEntities() : void{
		$f = $this->f;
		$js = $this->js;

		$this->entityComponent = $f->define("EntityComponent", $this->component);
		$f->getter($this->entityComponent, "entity", function(mixed $thisValue) : mixed{
			return $this->f->host($thisValue, "component")["owner"];
		});
		$this->export("EntityComponent", $this->entityComponent);
		$this->attributeComponent = $f->define("EntityAttributeComponent", $this->entityComponent);
		$this->export("EntityAttributeComponent", $this->attributeComponent);
		$this->health = $f->define("EntityHealthComponent", $this->attributeComponent);
		$f->staticValue($this->health, "componentId", "minecraft:health");
		$ownerId = function(mixed $thisValue) : int{
			return $this->f->host($thisValue, "component")["owner"]->host["id"];
		};
		$f->getter($this->health, "currentValue", function(mixed $thisValue) use ($ownerId) : mixed{
			return Interpreter::intOrFloat((float) $this->raw("ent.health", $ownerId($thisValue))["c"]);
		});
		$f->getter($this->health, "effectiveMax", function(mixed $thisValue) use ($ownerId) : mixed{
			return Interpreter::intOrFloat((float) $this->raw("ent.health", $ownerId($thisValue))["m"]);
		});
		$f->getter($this->health, "defaultValue", function(mixed $thisValue) use ($ownerId) : mixed{
			return Interpreter::intOrFloat((float) $this->raw("ent.health", $ownerId($thisValue))["m"]);
		});
		$f->getter($this->health, "effectiveMin", function(mixed $thisValue) : mixed{
			return 0;
		});
		$f->method($this->health, "setCurrentValue", function(mixed $thisValue, array $args) use ($ownerId) : mixed{
			return $this->raw("ent.setHealth", $ownerId($thisValue), $this->js->toNumber($args[0] ?? 0));
		}, 1);
		foreach(["resetToDefaultValue", "resetToMaxValue"] as $name){
			$f->method($this->health, $name, function(mixed $thisValue, array $args) use ($ownerId) : mixed{
				$this->raw("ent.setHealth", $ownerId($thisValue), $this->raw("ent.health", $ownerId($thisValue))["m"]);
				return null;
			});
		}
		$f->method($this->health, "resetToMinValue", function(mixed $thisValue, array $args) use ($ownerId) : mixed{
			$this->raw("ent.setHealth", $ownerId($thisValue), 0);
			return null;
		});
		$this->export("EntityHealthComponent", $this->health);

		$this->inventory = $f->define("EntityInventoryComponent", $this->entityComponent);
		$f->staticValue($this->inventory, "componentId", "minecraft:inventory");
		$f->getter($this->inventory, "container", function(mixed $thisValue) use ($ownerId) : mixed{
			return $this->f->instance($this->container, ["kind" => "container", "ref" => ["e" => $ownerId($thisValue)]]);
		});
		$f->getter($this->inventory, "inventorySize", function(mixed $thisValue) use ($ownerId) : mixed{
			return $this->raw("cont.size", ['$c' => ["e" => $ownerId($thisValue)]]);
		});
		foreach(["containerType" => "inventory", "canBeSiphonedFrom" => false, "private" => false, "restrictToOwner" => false, "additionalSlotsPerStrength" => 0] as $name => $value){
			$f->getter($this->inventory, $name, function(mixed $thisValue) use ($value) : mixed{
				return $value;
			});
		}
		$this->export("EntityInventoryComponent", $this->inventory);

		$this->equippable = $f->define("EntityEquippableComponent", $this->entityComponent);
		$f->staticValue($this->equippable, "componentId", "minecraft:equippable");
		$f->method($this->equippable, "getEquipment", function(mixed $thisValue, array $args) use ($ownerId) : mixed{
			return $this->api("ent.equip.get", $ownerId($thisValue), $this->string($args[0] ?? ""));
		}, 1);
		$f->method($this->equippable, "setEquipment", function(mixed $thisValue, array $args) use ($ownerId) : mixed{
			return $this->raw("ent.equip.set", $ownerId($thisValue), $this->string($args[0] ?? ""), $this->fromJs($args[1] ?? null));
		}, 2);
		$this->missingMethods($this->equippable, ["getEquipmentSlot" => null]);
		$this->export("EntityEquippableComponent", $this->equippable);

		$components = [
			"minecraft:health" => $this->health,
			"minecraft:inventory" => $this->inventory,
			"minecraft:equippable" => $this->equippable
		];

		$this->entity = $f->define("Entity");
		$entity = $this->entity;
		$f->getter($entity, "id", function(mixed $thisValue) : mixed{
			return (string) $this->entityId($thisValue);
		});
		$f->getter($entity, "typeId", function(mixed $thisValue) : mixed{
			return $this->f->host($thisValue, "entity")["typeId"];
		});
		$f->getter($entity, "isValid", function(mixed $thisValue) : mixed{
			return $this->raw("ent.valid", $this->entityId($thisValue));
		});
		$f->getter($entity, "location", function(mixed $thisValue) : mixed{
			return $this->api("ent.get", $this->entityId($thisValue), "location");
		});
		$f->getter($entity, "dimension", function(mixed $thisValue) : mixed{
			return $this->dimensionObject((string) $this->raw("ent.get", $this->entityId($thisValue), "dimension"));
		});
		$f->getter($entity, "nameTag", function(mixed $thisValue) : mixed{
			return $this->raw("ent.get", $this->entityId($thisValue), "nameTag");
		}, function(mixed $thisValue, mixed $value) : void{
			$this->raw("ent.set", $this->entityId($thisValue), "nameTag", $this->string($value));
		});
		$f->getter($entity, "isSneaking", function(mixed $thisValue) : mixed{
			return $this->raw("ent.get", $this->entityId($thisValue), "sneaking");
		}, function(mixed $thisValue, mixed $value) : void{
			$this->raw("ent.set", $this->entityId($thisValue), "sneaking", $this->js->toBoolean($value));
		});
		foreach(["isOnGround" => "onGround", "isSprinting" => "sprinting", "isSwimming" => "swimming", "isGliding" => "gliding", "isInWater" => "underwater"] as $name => $field){
			$f->getter($entity, $name, function(mixed $thisValue) use ($field) : mixed{
				return $this->raw("ent.get", $this->entityId($thisValue), $field);
			});
		}
		foreach(["getRotation" => "rotation", "getVelocity" => "velocity", "getHeadLocation" => "head", "getViewDirection" => "view"] as $name => $field){
			$f->method($entity, $name, function(mixed $thisValue, array $args) use ($field) : mixed{
				return $this->api("ent.get", $this->entityId($thisValue), $field);
			});
		}
		$f->method($entity, "setRotation", function(mixed $thisValue, array $args) : mixed{
			$id = $this->entityId($thisValue);
			$this->raw("ent.teleport", $id, $this->raw("ent.get", $id, "location"), ["rotation" => $this->fromJs($args[0] ?? null)]);
			return null;
		}, 1);
		$f->method($entity, "teleport", function(mixed $thisValue, array $args) : mixed{
			$this->raw("ent.teleport", $this->entityId($thisValue), $this->vector($args[0] ?? null), $this->fromJs($args[1] ?? null) ?? []);
			return null;
		}, 2);
		$f->method($entity, "tryTeleport", function(mixed $thisValue, array $args) : mixed{
			return $this->raw("ent.teleport", $this->entityId($thisValue), $this->vector($args[0] ?? null), $this->fromJs($args[1] ?? null) ?? []);
		}, 2);
		$f->method($entity, "kill", function(mixed $thisValue, array $args) : mixed{
			return $this->raw("ent.kill", $this->entityId($thisValue));
		});
		$f->method($entity, "remove", function(mixed $thisValue, array $args) : mixed{
			$this->raw("ent.remove", $this->entityId($thisValue));
			return null;
		});
		$f->method($entity, "triggerEvent", function(mixed $thisValue, array $args) : mixed{
			$this->raw("ent.trigger", $this->entityId($thisValue), $this->string($args[0] ?? ""));
			return null;
		}, 1);
		$f->method($entity, "applyDamage", function(mixed $thisValue, array $args) : mixed{
			return $this->raw("ent.damage", $this->entityId($thisValue), $this->js->toNumber($args[0] ?? 0), $this->fromJs($args[1] ?? null) ?? []);
		}, 2);
		$f->method($entity, "applyImpulse", function(mixed $thisValue, array $args) : mixed{
			$this->raw("ent.impulse", $this->entityId($thisValue), $this->vector($args[0] ?? null));
			return null;
		}, 1);
		$f->method($entity, "applyKnockback", function(mixed $thisValue, array $args) : mixed{
			$first = $args[0] ?? null;
			if($first instanceof JsObject){
				$velocity = [
					"x" => (float) $this->js->toNumber($this->js->get($first, "x")),
					"y" => (float) $this->js->toNumber($args[1] ?? 0),
					"z" => (float) $this->js->toNumber($this->js->get($first, "z"))
				];
			}else{
				$strength = (float) $this->js->toNumber($args[2] ?? 0);
				$velocity = [
					"x" => (float) $this->js->toNumber($first) * $strength,
					"y" => (float) $this->js->toNumber($args[3] ?? 0),
					"z" => (float) $this->js->toNumber($args[1] ?? 0) * $strength
				];
			}
			$this->raw("ent.setVelocity", $this->entityId($thisValue), $velocity);
			return null;
		}, 2);
		$f->method($entity, "clearVelocity", function(mixed $thisValue, array $args) : mixed{
			$this->raw("ent.setVelocity", $this->entityId($thisValue), ["x" => 0.0, "y" => 0.0, "z" => 0.0]);
			return null;
		});
		$f->method($entity, "addEffect", function(mixed $thisValue, array $args) : mixed{
			$id = $this->entityId($thisValue);
			$type = $this->typeName($args[0] ?? null);
			$options = $args[2] ?? null;
			$amplifier = $options instanceof JsObject ? ($this->js->get($options, "amplifier") ?? 0) : 0;
			$particles = $options instanceof JsObject ? $this->js->get($options, "showParticles") : null;
			$added = $this->raw("ent.effect.add", $id, $type, $this->js->toNumber($args[1] ?? 1), $this->js->toNumber($amplifier), $particles !== false);
			if($added !== true){
				return null;
			}
			$data = $this->raw("ent.effect.get", $id, $type);
			return $data === null ? null : $this->effectObject($id, $data);
		}, 3);
		$f->method($entity, "removeEffect", function(mixed $thisValue, array $args) : mixed{
			return $this->raw("ent.effect.remove", $this->entityId($thisValue), $this->typeName($args[0] ?? null));
		}, 1);
		$f->method($entity, "getEffect", function(mixed $thisValue, array $args) : mixed{
			$id = $this->entityId($thisValue);
			$data = $this->raw("ent.effect.get", $id, $this->typeName($args[0] ?? null));
			return $data === null ? null : $this->effectObject($id, $data);
		}, 1);
		$f->method($entity, "getEffects", function(mixed $thisValue, array $args) use ($js) : mixed{
			$id = $this->entityId($thisValue);
			$effects = [];
			foreach($this->raw("ent.effects", $id) as $data){
				$effects[] = $this->effectObject($id, $data);
			}
			return $js->newArray($effects);
		});
		$f->method($entity, "addTag", function(mixed $thisValue, array $args) : mixed{
			return $this->raw("ent.addTag", $this->entityId($thisValue), $this->string($args[0] ?? ""));
		}, 1);
		$f->method($entity, "removeTag", function(mixed $thisValue, array $args) : mixed{
			return $this->raw("ent.removeTag", $this->entityId($thisValue), $this->string($args[0] ?? ""));
		}, 1);
		$f->method($entity, "hasTag", function(mixed $thisValue, array $args) : mixed{
			return in_array($this->string($args[0] ?? ""), $this->raw("ent.tags", $this->entityId($thisValue)), true);
		}, 1);
		$f->method($entity, "getTags", function(mixed $thisValue, array $args) : mixed{
			return $this->api("ent.tags", $this->entityId($thisValue));
		});
		$f->method($entity, "getComponent", function(mixed $thisValue, array $args) use ($components) : mixed{
			$id = self::namespaced($this->string($args[0] ?? ""));
			$class = $components[$id] ?? null;
			if($class === null){
				$this->runtime->recordMissing("Entity.getComponent(" . $id . ")");
				return null;
			}
			if($this->raw("ent.comp", $this->entityId($thisValue), $id) !== true){
				return null;
			}
			return $this->f->instance($class, ["kind" => "component", "owner" => $thisValue, "typeId" => $id]);
		}, 1);
		$f->method($entity, "hasComponent", function(mixed $thisValue, array $args) use ($components) : mixed{
			$id = self::namespaced($this->string($args[0] ?? ""));
			return isset($components[$id]) && $this->raw("ent.comp", $this->entityId($thisValue), $id) === true;
		}, 1);
		$f->method($entity, "getComponents", function(mixed $thisValue, array $args) use ($components, $js) : mixed{
			$result = [];
			foreach($components as $id => $class){
				if($this->raw("ent.comp", $this->entityId($thisValue), $id) === true){
					$result[] = $this->f->instance($class, ["kind" => "component", "owner" => $thisValue, "typeId" => $id]);
				}
			}
			return $js->newArray($result);
		});
		$f->method($entity, "runCommand", function(mixed $thisValue, array $args) : mixed{
			return $this->toJs(["successCount" => $this->raw("ent.command", $this->entityId($thisValue), $this->string($args[0] ?? ""))]);
		}, 1);
		$f->method($entity, "setOnFire", function(mixed $thisValue, array $args) : mixed{
			return $this->raw("ent.fire", $this->entityId($thisValue), $this->js->toNumber($args[0] ?? 0));
		}, 2);
		$f->method($entity, "extinguishFire", function(mixed $thisValue, array $args) : mixed{
			$this->raw("ent.extinguish", $this->entityId($thisValue));
			return true;
		});
		$this->entityDynamicProperties($entity, function(mixed $thisValue) : int{
			return $this->entityId($thisValue);
		});
		$this->export("Entity", $entity);

		$this->screenDisplay = $f->define("ScreenDisplay");
		$screenPlayer = function(mixed $thisValue) : int{
			return $this->f->host($thisValue, "screen")["player"];
		};
		$f->getter($this->screenDisplay, "isValid", function(mixed $thisValue) use ($screenPlayer) : mixed{
			return $this->raw("ent.valid", $screenPlayer($thisValue));
		});
		$f->method($this->screenDisplay, "setTitle", function(mixed $thisValue, array $args) use ($screenPlayer) : mixed{
			$options = $args[1] ?? null;
			$data = [];
			if($options instanceof JsObject){
				foreach(["fadeInDuration", "stayDuration", "fadeOutDuration"] as $key){
					$value = $this->js->get($options, $key);
					if($value !== null){
						$data[$key] = $this->js->toNumber($value);
					}
				}
				$subtitle = $this->js->get($options, "subtitle");
				if($subtitle !== null){
					$data["subtitle"] = $this->text($subtitle);
				}
			}
			$this->raw("pl.title", $screenPlayer($thisValue), $this->text($args[0] ?? ""), $data);
			return null;
		}, 2);
		$f->method($this->screenDisplay, "updateSubtitle", function(mixed $thisValue, array $args) use ($screenPlayer) : mixed{
			$this->raw("pl.title", $screenPlayer($thisValue), "", ["subtitle" => $this->text($args[0] ?? "")]);
			return null;
		}, 1);
		$f->method($this->screenDisplay, "setActionBar", function(mixed $thisValue, array $args) use ($screenPlayer) : mixed{
			$this->raw("pl.actionbar", $screenPlayer($thisValue), $this->text($args[0] ?? ""));
			return null;
		}, 1);
		$this->missingMethods($this->screenDisplay, ["clearTitle" => null, "resetHudElements" => null, "setHudVisibility" => null, "isForcedHidden" => false]);
		$this->export("ScreenDisplay", $this->screenDisplay);

		$this->player = $f->define("Player", $entity);
		$player = $this->player;
		$f->getter($player, "name", function(mixed $thisValue) : mixed{
			return $this->f->host($thisValue, "entity")["name"];
		});
		$f->getter($player, "onScreenDisplay", function(mixed $thisValue) : mixed{
			return $this->f->instance($this->screenDisplay, ["kind" => "screen", "player" => $this->entityId($thisValue)]);
		});
		$f->getter($player, "level", function(mixed $thisValue) : mixed{
			return $this->raw("pl.xpInfo", $this->entityId($thisValue))["level"];
		});
		$f->getter($player, "xpEarnedAtCurrentLevel", function(mixed $thisValue) : mixed{
			return $this->raw("pl.xpInfo", $this->entityId($thisValue))["remainder"];
		});
		$f->getter($player, "totalXpNeededForNextLevel", function(mixed $thisValue) : mixed{
			$level = (int) $this->raw("pl.xpInfo", $this->entityId($thisValue))["level"];
			if($level >= 30){
				return 112 + ($level - 30) * 9;
			}
			if($level >= 15){
				return 37 + ($level - 15) * 5;
			}
			return 7 + $level * 2;
		});
		$f->getter($player, "selectedSlotIndex", function(mixed $thisValue) : mixed{
			return $this->raw("ent.get", $this->entityId($thisValue), "selectedSlot");
		}, function(mixed $thisValue, mixed $value) : void{
			$this->raw("pl.selectedSlot", $this->entityId($thisValue), $this->js->toNumber($value));
		});
		$f->getter($player, "isFlying", function(mixed $thisValue) : mixed{
			return $this->raw("ent.get", $this->entityId($thisValue), "flying");
		});
		$f->method($player, "getTotalXp", function(mixed $thisValue, array $args) : mixed{
			return $this->raw("pl.xpInfo", $this->entityId($thisValue))["total"];
		});
		$f->method($player, "addExperience", function(mixed $thisValue, array $args) : mixed{
			return $this->raw("pl.xp", $this->entityId($thisValue), $this->js->toNumber($args[0] ?? 0));
		}, 1);
		$f->method($player, "addLevels", function(mixed $thisValue, array $args) : mixed{
			return $this->raw("pl.levels", $this->entityId($thisValue), $this->js->toNumber($args[0] ?? 0));
		}, 1);
		$f->method($player, "resetLevel", function(mixed $thisValue, array $args) : mixed{
			$id = $this->entityId($thisValue);
			$this->raw("pl.xp", $id, -$this->raw("pl.xpInfo", $id)["total"]);
			return null;
		});
		$f->method($player, "sendMessage", function(mixed $thisValue, array $args) : mixed{
			$this->raw("pl.message", $this->entityId($thisValue), $this->text($args[0] ?? ""));
			return null;
		}, 1);
		$f->method($player, "playSound", function(mixed $thisValue, array $args) : mixed{
			$this->raw("pl.sound", $this->entityId($thisValue), $this->string($args[0] ?? ""), $this->fromJs($args[1] ?? null) ?? []);
			return null;
		}, 2);
		$f->method($player, "getGameMode", function(mixed $thisValue, array $args) : mixed{
			return $this->raw("pl.gamemode", $this->entityId($thisValue));
		});
		$f->method($player, "setGameMode", function(mixed $thisValue, array $args) : mixed{
			$mode = $args[0] ?? null;
			$this->raw("pl.setGamemode", $this->entityId($thisValue), $mode === null ? "Survival" : $this->string($mode));
			return null;
		}, 1);
		$f->method($player, "isOp", function(mixed $thisValue, array $args) : mixed{
			return $this->raw("ent.get", $this->entityId($thisValue), "op");
		});
		$f->method($player, "getSpawnPoint", function(mixed $thisValue, array $args) : mixed{
			return $this->api("pl.spawnPoint", $this->entityId($thisValue));
		});
		$f->method($player, "setSpawnPoint", function(mixed $thisValue, array $args) : mixed{
			$this->raw("pl.setSpawnPoint", $this->entityId($thisValue), $this->fromJs($args[0] ?? null));
			return null;
		}, 1);
		$this->export("Player", $player);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function effectObject(int $entity, array $data) : JsObject{
		return $this->f->instance($this->effect, [
			"kind" => "effect",
			"entity" => $entity,
			"t" => (string) ($data["t"] ?? ""),
			"d" => (int) ($data["d"] ?? 0),
			"a" => (int) ($data["a"] ?? 0),
			"n" => (string) ($data["n"] ?? "")
		]);
	}

	private function defineBlocks() : void{
		$f = $this->f;
		$js = $this->js;

		$this->blockComponent = $f->define("BlockComponent", $this->component);
		$f->getter($this->blockComponent, "block", function(mixed $thisValue) : mixed{
			return $this->f->host($thisValue, "component")["owner"];
		});
		$this->export("BlockComponent", $this->blockComponent);
		$this->blockInventory = $f->define("BlockInventoryComponent", $this->blockComponent);
		$f->staticValue($this->blockInventory, "componentId", "minecraft:inventory");
		$f->getter($this->blockInventory, "container", function(mixed $thisValue) : mixed{
			$block = $this->f->host($thisValue, "component")["owner"]->host;
			return $this->f->instance($this->container, ["kind" => "container", "ref" => ["b" => [$block["d"], $block["x"], $block["y"], $block["z"]]]]);
		});
		$this->export("BlockInventoryComponent", $this->blockInventory);

		$this->block = $f->define("Block");
		$block = $this->block;
		$info = function(mixed $thisValue) use ($js) : array{
			$host = $this->f->host($thisValue, "block");
			$result = $this->raw("block.get", $host["d"], ["x" => $host["x"], "y" => $host["y"], "z" => $host["z"]]);
			if($result === null){
				$js->throwError("Error", "The block is in an unloaded chunk");
			}
			return $result;
		};
		$f->getter($block, "dimension", function(mixed $thisValue) : mixed{
			return $this->dimensionObject($this->f->host($thisValue, "block")["d"]);
		});
		foreach(["x", "y", "z"] as $axis){
			$f->getter($block, $axis, function(mixed $thisValue) use ($axis) : mixed{
				return $this->f->host($thisValue, "block")[$axis];
			});
		}
		$f->getter($block, "location", function(mixed $thisValue) : mixed{
			$host = $this->f->host($thisValue, "block");
			return $this->toJs(["x" => $host["x"], "y" => $host["y"], "z" => $host["z"]]);
		});
		$f->getter($block, "isValid", function(mixed $thisValue) : mixed{
			$host = $this->f->host($thisValue, "block");
			return $this->raw("block.get", $host["d"], ["x" => $host["x"], "y" => $host["y"], "z" => $host["z"]]) !== null;
		});
		$f->getter($block, "permutation", function(mixed $thisValue) use ($info) : mixed{
			return $this->toJs($info($thisValue)["p"]);
		});
		$f->getter($block, "typeId", function(mixed $thisValue) use ($info) : mixed{
			return $info($thisValue)["p"]['$p'];
		});
		$f->getter($block, "type", function(mixed $thisValue) use ($info) : mixed{
			return $this->f->instance($this->blockType, ["kind" => "type", "id" => $info($thisValue)["p"]['$p']]);
		});
		$f->getter($block, "isAir", function(mixed $thisValue) use ($info) : mixed{
			return $info($thisValue)["p"]['$p'] === "minecraft:air";
		});
		$f->getter($block, "isLiquid", function(mixed $thisValue) use ($info) : mixed{
			return $info($thisValue)["l"];
		});
		$f->getter($block, "isSolid", function(mixed $thisValue) use ($info) : mixed{
			return $info($thisValue)["s"];
		});
		$f->getter($block, "isWaterlogged", function(mixed $thisValue) : mixed{
			return false;
		});
		$location = function(mixed $thisValue) : array{
			$host = $this->f->host($thisValue, "block");
			return ["x" => $host["x"], "y" => $host["y"], "z" => $host["z"]];
		};
		$f->method($block, "setType", function(mixed $thisValue, array $args) use ($location) : mixed{
			$this->raw("dim.setBlock", $this->f->host($thisValue, "block")["d"], $location($thisValue), $this->typeName($args[0] ?? null));
			return null;
		}, 1);
		$f->method($block, "setPermutation", function(mixed $thisValue, array $args) use ($location) : mixed{
			$this->raw("dim.setBlock", $this->f->host($thisValue, "block")["d"], $location($thisValue), $this->fromJs($args[0] ?? null));
			return null;
		}, 1);
		$offset = function(mixed $thisValue, int $dx, int $dy, int $dz) : mixed{
			$host = $this->f->host($thisValue, "block");
			return $this->api("dim.block", $host["d"], ["x" => $host["x"] + $dx, "y" => $host["y"] + $dy, "z" => $host["z"] + $dz]);
		};
		$f->method($block, "offset", function(mixed $thisValue, array $args) use ($offset) : mixed{
			$vector = $this->vector($args[0] ?? null);
			return $offset($thisValue, (int) $vector["x"], (int) $vector["y"], (int) $vector["z"]);
		}, 1);
		foreach(["above" => [0, 1, 0], "below" => [0, -1, 0], "north" => [0, 0, -1], "south" => [0, 0, 1], "east" => [1, 0, 0], "west" => [-1, 0, 0]] as $name => [$dx, $dy, $dz]){
			$f->method($block, $name, function(mixed $thisValue, array $args) use ($offset, $dx, $dy, $dz) : mixed{
				$steps = ($args[0] ?? null) === null ? 1 : (int) $this->js->toNumber($args[0]);
				return $offset($thisValue, $dx * $steps, $dy * $steps, $dz * $steps);
			});
		}
		$f->method($block, "center", function(mixed $thisValue, array $args) : mixed{
			$host = $this->f->host($thisValue, "block");
			return $this->toJs(["x" => $host["x"] + 0.5, "y" => $host["y"] + 0.5, "z" => $host["z"] + 0.5]);
		});
		$f->method($block, "bottomCenter", function(mixed $thisValue, array $args) : mixed{
			$host = $this->f->host($thisValue, "block");
			return $this->toJs(["x" => $host["x"] + 0.5, "y" => $host["y"], "z" => $host["z"] + 0.5]);
		});
		$f->method($block, "matches", function(mixed $thisValue, array $args) use ($info) : mixed{
			$permutation = $info($thisValue)["p"];
			$states = $permutation["s"];
			return $this->matchesPermutation($permutation['$p'], is_array($states) ? $states : [], $args[0] ?? null, $args[1] ?? null);
		}, 2);
		$f->method($block, "getItemStack", function(mixed $thisValue, array $args) use ($info) : mixed{
			return $this->api("block.item", $info($thisValue)["p"], $args[0] ?? 1);
		});
		$f->method($block, "getComponent", function(mixed $thisValue, array $args) use ($info) : mixed{
			$id = self::namespaced($this->string($args[0] ?? ""));
			if($id !== "minecraft:inventory"){
				$this->runtime->recordMissing("Block.getComponent(" . $id . ")");
				return null;
			}
			return $info($thisValue)["i"] ? $this->f->instance($this->blockInventory, ["kind" => "component", "owner" => $thisValue, "typeId" => $id]) : null;
		}, 1);
		$this->missingMethods($block, ["getTags" => [], "hasTag" => false, "getRedstonePower" => null, "canPlace" => false]);
		$this->export("Block", $block);

		$this->dimension = $f->define("Dimension");
		$dimension = $this->dimension;
		$dimensionId = function(mixed $thisValue) : string{
			return $this->f->host($thisValue, "dimension")["id"];
		};
		$f->getter($dimension, "id", $dimensionId);
		$f->getter($dimension, "heightRange", function(mixed $thisValue) : mixed{
			return $this->toJs(["min" => -64, "max" => 320]);
		});
		$f->method($dimension, "getBlock", function(mixed $thisValue, array $args) use ($dimensionId) : mixed{
			return $this->api("dim.block", $dimensionId($thisValue), $this->vector($args[0] ?? null));
		}, 1);
		$f->method($dimension, "setBlockType", function(mixed $thisValue, array $args) use ($dimensionId) : mixed{
			$this->raw("dim.setBlock", $dimensionId($thisValue), $this->vector($args[0] ?? null), $this->typeName($args[1] ?? null));
			return null;
		}, 2);
		$f->method($dimension, "setBlockPermutation", function(mixed $thisValue, array $args) use ($dimensionId) : mixed{
			$this->raw("dim.setBlock", $dimensionId($thisValue), $this->vector($args[0] ?? null), $this->fromJs($args[1] ?? null));
			return null;
		}, 2);
		$f->method($dimension, "getEntities", function(mixed $thisValue, array $args) use ($dimensionId) : mixed{
			return $this->api("dim.entities", $dimensionId($thisValue), $this->queryOptions($args[0] ?? null));
		}, 1);
		$f->method($dimension, "getEntitiesAtBlockLocation", function(mixed $thisValue, array $args) use ($dimensionId) : mixed{
			return $this->api("dim.entities", $dimensionId($thisValue), ["_block" => $this->vector($args[0] ?? null)]);
		}, 1);
		$f->method($dimension, "getPlayers", function(mixed $thisValue, array $args) use ($dimensionId) : mixed{
			$options = $this->queryOptions($args[0] ?? null);
			$options["_players"] = true;
			return $this->api("dim.entities", $dimensionId($thisValue), $options);
		}, 1);
		$f->method($dimension, "spawnEntity", function(mixed $thisValue, array $args) use ($dimensionId) : mixed{
			return $this->api("dim.spawn", $dimensionId($thisValue), $this->typeName($args[0] ?? null), $this->vector($args[1] ?? null));
		}, 3);
		$f->method($dimension, "spawnItem", function(mixed $thisValue, array $args) use ($dimensionId) : mixed{
			return $this->api("dim.item", $dimensionId($thisValue), $this->fromJs($args[0] ?? null), $this->vector($args[1] ?? null));
		}, 2);
		$f->method($dimension, "runCommand", function(mixed $thisValue, array $args) use ($dimensionId) : mixed{
			return $this->toJs(["successCount" => $this->raw("dim.command", $dimensionId($thisValue), $this->string($args[0] ?? ""))]);
		}, 1);
		$f->method($dimension, "playSound", function(mixed $thisValue, array $args) use ($dimensionId) : mixed{
			$this->raw("dim.sound", $dimensionId($thisValue), $this->string($args[0] ?? ""), $this->vector($args[1] ?? null), $this->fromJs($args[2] ?? null) ?? []);
			return null;
		}, 3);
		$f->method($dimension, "createExplosion", function(mixed $thisValue, array $args) use ($dimensionId) : mixed{
			return $this->raw("dim.explode", $dimensionId($thisValue), $this->vector($args[0] ?? null), $this->js->toNumber($args[1] ?? 1), $this->fromJs($args[2] ?? null) ?? []);
		}, 3);
		$f->method($dimension, "getTopmostBlock", function(mixed $thisValue, array $args) use ($dimensionId) : mixed{
			$location = $args[0] ?? null;
			if(!$location instanceof JsObject){
				$this->js->throwError("TypeError", "Expected a VectorXZ");
			}
			return $this->api("dim.top", $dimensionId($thisValue), $this->js->toNumber($this->js->get($location, "x")), $this->js->toNumber($this->js->get($location, "z")));
		}, 2);
		$this->missingMethods($dimension, ["getBlockAbove" => null, "getBlockBelow" => null, "getBlockFromRay" => null, "getEntitiesFromRay" => [], "fillBlocks" => null, "spawnParticle" => null, "getWeather" => "Clear", "setWeather" => null, "containsBlock" => false, "getBlocks" => null]);
		$this->export("Dimension", $dimension);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function queryOptions(mixed $options) : array{
		if(!$options instanceof JsObject){
			return [];
		}
		$result = $this->fromJs($options);
		if(!is_array($result)){
			return [];
		}
		foreach(["families", "excludeFamilies", "volume", "scoreOptions", "propertyOptions"] as $unsupported){
			if(isset($result[$unsupported])){
				$this->runtime->recordMissing("EntityQueryOptions." . $unsupported);
			}
		}
		return $result;
	}

	private function loadScoreboard(mixed $data) : void{
		if(!is_array($data)){
			return;
		}
		$this->scoreboardData = [
			"next" => (int) ($data["next"] ?? 1),
			"objectives" => [],
			"participants" => []
		];
		foreach(is_array($data["objectives"] ?? null) ? $data["objectives"] : [] as $id => $objective){
			if(is_array($objective)){
				$scores = [];
				foreach(is_array($objective["scores"] ?? null) ? $objective["scores"] : [] as $key => $score){
					$scores[(string) $key] = (int) $score;
				}
				$this->scoreboardData["objectives"][(string) $id] = ["displayName" => (string) ($objective["displayName"] ?? $id), "scores" => $scores];
			}
		}
		foreach(is_array($data["participants"] ?? null) ? $data["participants"] : [] as $key => $participant){
			if(is_array($participant)){
				$this->scoreboardData["participants"][(string) $key] = [
					"id" => (int) ($participant["id"] ?? 0),
					"displayName" => (string) ($participant["displayName"] ?? ""),
					"type" => (string) ($participant["type"] ?? "FakePlayer")
				];
			}
		}
	}

	private function participantKey(mixed $participant) : string{
		if($participant instanceof JsObject && is_array($participant->host)){
			$host = $participant->host;
			if(($host["kind"] ?? null) === "identity"){
				return $host["key"];
			}
			if(($host["kind"] ?? null) === "entity"){
				return $host["typeId"] === "minecraft:player" ? "p:" . $host["name"] : "e:" . $host["id"];
			}
		}
		if(is_string($participant)){
			return "f:" . $participant;
		}
		$this->js->throwError("TypeError", "Invalid scoreboard participant");
	}

	private function ensureParticipant(mixed $participant) : string{
		$key = $this->participantKey($participant);
		if(!isset($this->scoreboardData["participants"][$key])){
			$type = "FakePlayer";
			$name = is_string($participant) ? $participant : $key;
			if($participant instanceof JsObject && ($participant->host["kind"] ?? null) === "entity"){
				$type = $participant->host["typeId"] === "minecraft:player" ? "Player" : "Entity";
				$name = $type === "Player" ? $participant->host["name"] : $participant->host["typeId"];
			}
			$this->scoreboardData["participants"][$key] = ["id" => $this->scoreboardData["next"]++, "displayName" => $name, "type" => $type];
			$this->runtime->scoreboardDirty = true;
		}
		return $key;
	}

	private function identityObject(string $key) : JsObject{
		return $this->f->instance($this->identity, ["kind" => "identity", "key" => $key]);
	}

	/**
	 * @return array{displayName: string, scores: array<string, int>}
	 */
	private function objectiveData(mixed $thisValue) : array{
		$id = $this->f->host($thisValue, "objective")["id"];
		$data = $this->scoreboardData["objectives"][$id] ?? null;
		if($data === null){
			$this->js->throwError("Error", "The objective " . $id . " was removed");
		}
		return $data;
	}

	private function defineScoreboard() : void{
		$f = $this->f;
		$js = $this->js;
		$this->identity = $f->define("ScoreboardIdentity");
		$participant = function(mixed $thisValue) : array{
			$key = $this->f->host($thisValue, "identity")["key"];
			return $this->scoreboardData["participants"][$key] ?? ["id" => 0, "displayName" => "", "type" => "FakePlayer"];
		};
		$f->getter($this->identity, "id", function(mixed $thisValue) use ($participant) : mixed{
			return $participant($thisValue)["id"];
		});
		$f->getter($this->identity, "displayName", function(mixed $thisValue) use ($participant) : mixed{
			return $participant($thisValue)["displayName"];
		});
		$f->getter($this->identity, "type", function(mixed $thisValue) use ($participant) : mixed{
			return $participant($thisValue)["type"];
		});
		$f->getter($this->identity, "isValid", function(mixed $thisValue) : mixed{
			return isset($this->scoreboardData["participants"][$this->f->host($thisValue, "identity")["key"]]);
		});
		$f->method($this->identity, "getEntity", function(mixed $thisValue, array $args) : mixed{
			$key = $this->f->host($thisValue, "identity")["key"];
			if(substr($key, 0, 2) === "e:"){
				return $this->api("world.entity", (int) substr($key, 2));
			}
			if(substr($key, 0, 2) === "p:"){
				$name = substr($key, 2);
				foreach($this->raw("world.players", ["name" => $name]) as $ref){
					return $this->toJs($ref);
				}
			}
			return null;
		});
		$this->export("ScoreboardIdentity", $this->identity);

		$this->objective = $f->define("ScoreboardObjective");
		$f->getter($this->objective, "id", function(mixed $thisValue) : mixed{
			return $this->f->host($thisValue, "objective")["id"];
		});
		$f->getter($this->objective, "displayName", function(mixed $thisValue) : mixed{
			return $this->objectiveData($thisValue)["displayName"];
		});
		$f->getter($this->objective, "isValid", function(mixed $thisValue) : mixed{
			return isset($this->scoreboardData["objectives"][$this->f->host($thisValue, "objective")["id"]]);
		});
		$f->method($this->objective, "getScore", function(mixed $thisValue, array $args) : mixed{
			return $this->objectiveData($thisValue)["scores"][$this->participantKey($args[0] ?? null)] ?? null;
		}, 1);
		$f->method($this->objective, "setScore", function(mixed $thisValue, array $args) : mixed{
			$this->objectiveData($thisValue);
			$id = $thisValue->host["id"];
			$this->scoreboardData["objectives"][$id]["scores"][$this->ensureParticipant($args[0] ?? null)] = (int) $this->js->toNumber($args[1] ?? 0);
			$this->runtime->scoreboardDirty = true;
			return null;
		}, 2);
		$f->method($this->objective, "addScore", function(mixed $thisValue, array $args) : mixed{
			$this->objectiveData($thisValue);
			$id = $thisValue->host["id"];
			$key = $this->ensureParticipant($args[0] ?? null);
			$score = ($this->scoreboardData["objectives"][$id]["scores"][$key] ?? 0) + (int) $this->js->toNumber($args[1] ?? 0);
			$this->scoreboardData["objectives"][$id]["scores"][$key] = $score;
			$this->runtime->scoreboardDirty = true;
			return $score;
		}, 2);
		$f->method($this->objective, "hasParticipant", function(mixed $thisValue, array $args) : mixed{
			return isset($this->objectiveData($thisValue)["scores"][$this->participantKey($args[0] ?? null)]);
		}, 1);
		$f->method($this->objective, "removeParticipant", function(mixed $thisValue, array $args) : mixed{
			$this->objectiveData($thisValue);
			$id = $thisValue->host["id"];
			$key = $this->participantKey($args[0] ?? null);
			if(!isset($this->scoreboardData["objectives"][$id]["scores"][$key])){
				return false;
			}
			unset($this->scoreboardData["objectives"][$id]["scores"][$key]);
			$this->runtime->scoreboardDirty = true;
			return true;
		}, 1);
		$f->method($this->objective, "getParticipants", function(mixed $thisValue, array $args) use ($js) : mixed{
			$result = [];
			foreach($this->objectiveData($thisValue)["scores"] as $key => $score){
				$result[] = $this->identityObject((string) $key);
			}
			return $js->newArray($result);
		});
		$f->method($this->objective, "getScores", function(mixed $thisValue, array $args) use ($js) : mixed{
			$result = [];
			foreach($this->objectiveData($thisValue)["scores"] as $key => $score){
				$entry = $js->newObject();
				$entry->props = ["participant" => $this->identityObject((string) $key), "score" => $score];
				$result[] = $entry;
			}
			return $js->newArray($result);
		});
		$this->export("ScoreboardObjective", $this->objective);

		$this->scoreboard = $f->define("Scoreboard");
		$f->method($this->scoreboard, "addObjective", function(mixed $thisValue, array $args) use ($js) : mixed{
			$id = $this->string($args[0] ?? "");
			if(isset($this->scoreboardData["objectives"][$id])){
				$js->throwError("Error", "The objective " . $id . " already exists");
			}
			$displayName = ($args[1] ?? null) === null ? $id : $this->string($args[1]);
			$this->scoreboardData["objectives"][$id] = ["displayName" => $displayName, "scores" => []];
			$this->runtime->scoreboardDirty = true;
			return $this->f->instance($this->objective, ["kind" => "objective", "id" => $id]);
		}, 2);
		$f->method($this->scoreboard, "getObjective", function(mixed $thisValue, array $args) : mixed{
			$id = $this->string($args[0] ?? "");
			return isset($this->scoreboardData["objectives"][$id]) ? $this->f->instance($this->objective, ["kind" => "objective", "id" => $id]) : null;
		}, 1);
		$f->method($this->scoreboard, "getObjectives", function(mixed $thisValue, array $args) use ($js) : mixed{
			$result = [];
			foreach($this->scoreboardData["objectives"] as $id => $unused){
				$result[] = $this->f->instance($this->objective, ["kind" => "objective", "id" => (string) $id]);
			}
			return $js->newArray($result);
		});
		$f->method($this->scoreboard, "removeObjective", function(mixed $thisValue, array $args) : mixed{
			$value = $args[0] ?? null;
			$id = $value instanceof JsObject && ($value->host["kind"] ?? null) === "objective" ? $value->host["id"] : $this->string($value);
			if(!isset($this->scoreboardData["objectives"][$id])){
				return false;
			}
			unset($this->scoreboardData["objectives"][$id]);
			$this->runtime->scoreboardDirty = true;
			return true;
		}, 1);
		$f->method($this->scoreboard, "getParticipants", function(mixed $thisValue, array $args) use ($js) : mixed{
			$result = [];
			foreach($this->scoreboardData["participants"] as $key => $unused){
				$result[] = $this->identityObject((string) $key);
			}
			return $js->newArray($result);
		});
		$this->missingMethods($this->scoreboard, ["setObjectiveAtDisplaySlot" => null, "getObjectiveAtDisplaySlot" => null, "clearObjectiveAtDisplaySlot" => null]);
		$this->export("Scoreboard", $this->scoreboard);
	}

	private function defineEvents() : void{
		$f = $this->f;
		$this->signal = $f->define("EventSignal");
		$f->method($this->signal, "subscribe", function(mixed $thisValue, array $args) : mixed{
			$key = $this->f->host($thisValue, "signal")["key"];
			$callback = $args[0] ?? null;
			if(!$callback instanceof JsCallable){
				$this->js->throwError("TypeError", "The callback must be a function");
			}
			$this->runtime->subscribe($key, $callback);
			return $callback;
		}, 2);
		$f->method($this->signal, "unsubscribe", function(mixed $thisValue, array $args) : mixed{
			$key = $this->f->host($thisValue, "signal")["key"];
			$callback = $args[0] ?? null;
			if($callback instanceof JsCallable){
				$this->runtime->unsubscribe($key, $callback);
			}
			return null;
		}, 1);
		$this->export("EventSignal", $this->signal);

		$this->blockRegistry = $f->define("BlockComponentRegistry");
		$f->method($this->blockRegistry, "registerCustomComponent", function(mixed $thisValue, array $args) : mixed{
			$this->registerComponent("block", $args[0] ?? null, $args[1] ?? null);
			return null;
		}, 2);
		$this->export("BlockComponentRegistry", $this->blockRegistry);
		$this->itemRegistry = $f->define("ItemComponentRegistry");
		$f->method($this->itemRegistry, "registerCustomComponent", function(mixed $thisValue, array $args) : mixed{
			$this->registerComponent("item", $args[0] ?? null, $args[1] ?? null);
			return null;
		}, 2);
		$this->export("ItemComponentRegistry", $this->itemRegistry);
		$this->commandRegistry = $f->define("CustomCommandRegistry");
		$this->missingMethods($this->commandRegistry, ["registerCommand" => null, "registerEnum" => null]);
		$this->export("CustomCommandRegistry", $this->commandRegistry);
	}

	private function registerComponent(string $kind, mixed $name, mixed $component) : void{
		if(!is_string($name) || !str_contains($name, ":")){
			$this->js->throwError("Error", "Custom component names must have a namespace");
		}
		if(!$component instanceof JsObject){
			$this->js->throwError("TypeError", "The custom component must be an object");
		}
		$supported = $kind === "block" ? self::BLOCK_HOOKS : self::ITEM_HOOKS;
		$label = $kind === "block" ? "BlockCustomComponent" : "ItemCustomComponent";
		foreach($this->js->ownKeys($component) as $key){
			if(!in_array($key, $supported, true)){
				$this->runtime->recordMissing($label . "." . $key);
			}
		}
		$this->runtime->registerComponent($kind, $name, $component);
	}

	public function signalObject(string $key) : JsObject{
		return $this->signals[$key] ??= $this->f->instance($this->signal, ["kind" => "signal", "key" => $key]);
	}

	/**
	 * @param list<string> $names
	 */
	private function container(string $className, string $prefix, array $names) : JsObject{
		$class = $this->f->define($className);
		$container = $this->f->instance($class, ["kind" => "events"]);
		foreach($names as $name){
			$container->props[$name] = $this->signalObject($prefix . "." . $name);
		}
		$container->miss = function(JsObject $receiver, string $key) use ($className) : mixed{
			if($this->f->isIgnored($key)){
				return null;
			}
			$this->runtime->recordMissing($className . "." . $key);
			return $this->signalObject("inert." . $className . "." . $key);
		};
		return $container;
	}

	/**
	 * Creates an event object: its data is converted, block faces become
	 * Direction values, and before-events get a "cancel" property.
	 *
	 * @param array<string, mixed> $data
	 */
	public function createEvent(string $className, array $data, bool $before) : JsObject{
		$class = $this->eventClasses[$className] ??= $this->f->define($className);
		$event = new JsObject($class->prototype);
		$event->className = $className;
		foreach($data as $key => $value){
			if(($key === "blockFace" || $key === "face") && is_int($value)){
				$value = self::DIRECTIONS[$value] ?? "Up";
			}
			$event->props[$key] = $this->toJs($value);
		}
		if($before){
			$event->props["cancel"] = false;
		}
		return $event;
	}

	/**
	 * @return array<string, JsObject>
	 */
	public function createRegistries() : array{
		return [
			"blockComponentRegistry" => $this->f->instance($this->blockRegistry, ["kind" => "registry"]),
			"itemComponentRegistry" => $this->f->instance($this->itemRegistry, ["kind" => "registry"]),
			"customCommandRegistry" => $this->f->instance($this->commandRegistry, ["kind" => "registry"])
		];
	}

	private function defineWorld() : void{
		$f = $this->f;
		$js = $this->js;
		$this->world = $f->define("World");
		$world = $this->world;
		$f->method($world, "getAllPlayers", function(mixed $thisValue, array $args) : mixed{
			return $this->api("world.players", []);
		});
		$f->method($world, "getPlayers", function(mixed $thisValue, array $args) : mixed{
			return $this->api("world.players", $this->queryOptions($args[0] ?? null));
		}, 1);
		$f->method($world, "getDimension", function(mixed $thisValue, array $args) : mixed{
			return $this->dimensionObject($this->string($args[0] ?? "overworld"));
		}, 1);
		$f->method($world, "getEntity", function(mixed $thisValue, array $args) : mixed{
			$id = $args[0] ?? null;
			return is_numeric($id) ? $this->api("world.entity", (int) $id) : null;
		}, 1);
		$f->method($world, "sendMessage", function(mixed $thisValue, array $args) : mixed{
			$this->raw("world.message", $this->text($args[0] ?? ""));
			return null;
		}, 1);
		$f->method($world, "getAbsoluteTime", function(mixed $thisValue, array $args) : mixed{
			return $this->raw("world.time");
		});
		$f->method($world, "setAbsoluteTime", function(mixed $thisValue, array $args) : mixed{
			$this->raw("world.setTime", (int) $this->js->toNumber($args[0] ?? 0));
			return null;
		}, 1);
		$f->method($world, "getTimeOfDay", function(mixed $thisValue, array $args) : mixed{
			return $this->raw("world.time") % 24000;
		});
		$f->method($world, "setTimeOfDay", function(mixed $thisValue, array $args) : mixed{
			$time = (int) $this->raw("world.time");
			$this->raw("world.setTime", $time - ($time % 24000) + ((int) $this->js->toNumber($args[0] ?? 0) % 24000));
			return null;
		}, 1);
		$f->method($world, "getDay", function(mixed $thisValue, array $args) : mixed{
			return intdiv((int) $this->raw("world.time"), 24000);
		});
		$f->method($world, "getMoonPhase", function(mixed $thisValue, array $args) : mixed{
			return intdiv((int) $this->raw("world.time"), 24000) % 8;
		});
		$f->method($world, "getDefaultSpawnLocation", function(mixed $thisValue, array $args) : mixed{
			return $this->api("world.spawn");
		});
		$f->method($world, "setDefaultSpawnLocation", function(mixed $thisValue, array $args) : mixed{
			$this->raw("world.setSpawn", $this->vector($args[0] ?? null));
			return null;
		}, 1);
		$f->method($world, "playSound", function(mixed $thisValue, array $args) : mixed{
			$this->raw("world.sound", $this->string($args[0] ?? ""), $this->vector($args[1] ?? null), $this->fromJs($args[2] ?? null) ?? []);
			return null;
		}, 3);
		$this->missingMethods($world, ["playMusic" => null, "queueMusic" => null, "stopMusic" => null, "getLootTableManager" => null, "broadcastClientMessage" => null]);
		$this->entityDynamicProperties($world, function(mixed $thisValue) : string{
			return "world";
		});
		$this->export("World", $world);

		$this->worldObject = $f->instance($world, ["kind" => "world"]);
		$this->worldObject->props["afterEvents"] = $this->container("WorldAfterEvents", "after", [
			"playerJoin", "playerLeave", "playerSpawn", "playerBreakBlock", "playerPlaceBlock", "playerInteractWithBlock",
			"playerInteractWithEntity", "itemUse", "entityHurt", "entityDie", "entitySpawn", "chatSend", "worldLoad", "worldInitialize"
		]);
		$this->worldObject->props["beforeEvents"] = $this->container("WorldBeforeEvents", "before", [
			"playerBreakBlock", "playerInteractWithBlock", "itemUse", "chatSend", "playerLeave"
		]);
		$this->worldObject->props["scoreboard"] = $f->instance($this->scoreboard, ["kind" => "scoreboard"]);
		foreach(["afterEvents", "beforeEvents", "scoreboard"] as $key){
			$this->worldObject->locked[$key] = true;
		}
		$this->export("world", $this->worldObject);
	}

	private function defineSystem() : void{
		$f = $this->f;
		$js = $this->js;
		$this->system = $f->define("System");
		$system = $this->system;
		$f->getter($system, "currentTick", function(mixed $thisValue) : mixed{
			return $this->runtime->currentTick;
		});
		$f->getter($system, "isEditorWorld", function(mixed $thisValue) : mixed{
			return false;
		});
		$callback = function(mixed $value) use ($js) : JsCallable{
			if(!$value instanceof JsCallable){
				$js->throwError("TypeError", "The callback must be a function");
			}
			return $value;
		};
		$f->method($system, "run", function(mixed $thisValue, array $args) use ($callback) : mixed{
			return $this->runtime->schedule($callback($args[0] ?? null), 1, 0);
		}, 1);
		$f->method($system, "runTimeout", function(mixed $thisValue, array $args) use ($callback) : mixed{
			return $this->runtime->schedule($callback($args[0] ?? null), (int) $this->js->toNumber($args[1] ?? 1), 0);
		}, 2);
		$f->method($system, "runInterval", function(mixed $thisValue, array $args) use ($callback) : mixed{
			$interval = max(1, (int) $this->js->toNumber($args[1] ?? 1));
			return $this->runtime->schedule($callback($args[0] ?? null), $interval, $interval);
		}, 2);
		$f->method($system, "clearRun", function(mixed $thisValue, array $args) : mixed{
			$this->runtime->clearRun((int) $this->js->toNumber($args[0] ?? 0));
			return null;
		}, 1);
		$f->method($system, "runJob", function(mixed $thisValue, array $args) use ($js) : mixed{
			$generator = $args[0] ?? null;
			if(!$generator instanceof JsObject || !$js->get($generator, "next") instanceof JsCallable){
				$js->throwError("TypeError", "runJob expects a generator");
			}
			return $this->runtime->addJob($generator);
		}, 1);
		$f->method($system, "clearJob", function(mixed $thisValue, array $args) : mixed{
			$this->runtime->clearJob((int) $this->js->toNumber($args[0] ?? 0));
			return null;
		}, 1);
		$f->method($system, "waitTicks", function(mixed $thisValue, array $args) use ($js) : mixed{
			$promise = $js->newPromise();
			$resolve = $js->native("", 0, function(mixed $unused, array $none) use ($js, $promise) : mixed{
				$js->resolvePromise($promise, null);
				return null;
			});
			$this->runtime->schedule($resolve, max(1, (int) $js->toNumber($args[0] ?? 1)), 0);
			return $promise;
		}, 1);
		$f->method($system, "sendScriptEvent", function(mixed $thisValue, array $args) : mixed{
			$this->runtime->sendScriptEvent($this->string($args[0] ?? ""), $this->string($args[1] ?? ""));
			return null;
		}, 2);
		$this->export("System", $system);

		$this->systemObject = $f->instance($system, ["kind" => "system"]);
		$this->systemObject->props["afterEvents"] = $this->container("SystemAfterEvents", "after", ["scriptEventReceive"]);
		$this->systemObject->props["beforeEvents"] = $this->container("SystemBeforeEvents", "system", ["startup", "watchdogTerminate", "shutdown"]);
		$this->systemObject->locked["afterEvents"] = true;
		$this->systemObject->locked["beforeEvents"] = true;
		$this->export("system", $this->systemObject);
	}

	private function errorClass(string $name) : void{
		$js = $this->js;
		$errorConstructor = $js->constructors["Error"];
		$proto = new JsObject($js->errorPrototype);
		$proto->className = "Error";
		$constructor = $js->makeClass($name, $proto, function(array $args, JsObject $newTarget) use ($js, $proto, $name) : JsObject{
			$error = $js->makeError("Error", ($args[0] ?? null) === null ? "" : $js->toString($args[0]));
			$error->proto = $js->prototypeFor($newTarget, $proto);
			return $error;
		}, null, 1, $errorConstructor);
		$js->defineHidden($proto, "name", $name);
		$this->export($name, $constructor);
	}

	private function defineEnums() : void{
		$f = $this->f;
		foreach(["LocationInUnloadedChunkError", "LocationOutOfWorldBoundariesError", "InvalidEntityError", "InvalidContainerSlotError", "ContainerRulesError", "CommandError", "InvalidIteratorError", "UnloadedChunksError"] as $name){
			$this->errorClass($name);
		}
		$this->export("TicksPerSecond", 20);
		$enums = [
			"Direction" => ["Down" => "Down", "East" => "East", "North" => "North", "South" => "South", "Up" => "Up", "West" => "West"],
			"GameMode" => ["Adventure" => "Adventure", "Creative" => "Creative", "Spectator" => "Spectator", "Survival" => "Survival"],
			"EquipmentSlot" => ["Chest" => "Chest", "Feet" => "Feet", "Head" => "Head", "Legs" => "Legs", "Mainhand" => "Mainhand", "Offhand" => "Offhand"],
			"ScriptEventSource" => ["Block" => "Block", "Entity" => "Entity", "NPCDialogue" => "NPCDialogue", "Server" => "Server"],
			"DisplaySlotId" => ["BelowName" => "BelowName", "List" => "List", "Sidebar" => "Sidebar"],
			"ObjectiveSortOrder" => ["Ascending" => 0, "Descending" => 1],
			"ScoreboardIdentityType" => ["Entity" => "Entity", "FakePlayer" => "FakePlayer", "Player" => "Player"],
			"EntityInitializationCause" => ["Born" => "Born", "Event" => "Event", "Loaded" => "Loaded", "Spawned" => "Spawned", "Transformed" => "Transformed"],
			"ItemLockMode" => ["inventory" => "inventory", "none" => "none", "slot" => "slot"],
			"TimeOfDay" => ["Day" => 1000, "Midnight" => 18000, "Night" => 13000, "Noon" => 6000, "Sunrise" => 23000, "Sunset" => 12000],
			"MinecraftDimensionTypes" => ["Nether" => "minecraft:nether", "Overworld" => "minecraft:overworld", "TheEnd" => "minecraft:the_end"],
			"EntityComponentTypes" => ["Equippable" => "minecraft:equippable", "Health" => "minecraft:health", "Inventory" => "minecraft:inventory"],
			"ItemComponentTypes" => ["Durability" => "minecraft:durability"],
			"BlockComponentTypes" => ["Inventory" => "minecraft:inventory"],
			"SignSide" => ["Back" => "Back", "Front" => "Front"]
		];
		$causes = [];
		foreach(["anvil", "blockExplosion", "campfire", "charging", "contact", "drowning", "entityAttack", "entityExplosion", "fall", "fallingBlock", "fire", "fireTick", "fireworks", "flyIntoWall", "freezing", "lava", "lightning", "maceSmash", "magic", "magma", "none", "override", "piston", "projectile", "ramAttack", "selfDestruct", "sonicBoom", "soulCampfire", "stalactite", "stalagmite", "starve", "suffocation", "temperature", "thorns", "void", "wither"] as $cause){
			$causes[$cause] = $cause;
		}
		$enums["EntityDamageCause"] = $causes;
		foreach($enums as $name => $values){
			$this->export($name, $f->enum($name, $values));
		}
	}
}
