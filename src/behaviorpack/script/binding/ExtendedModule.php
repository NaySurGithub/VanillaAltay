<?php

declare(strict_types=1);

namespace behaviorpack\script\binding;

use behaviorpack\script\js\Interpreter;
use behaviorpack\script\js\JsArray;
use behaviorpack\script\js\JsNull;
use behaviorpack\script\js\JsObject;
use behaviorpack\script\ScriptException;
use behaviorpack\script\ScriptRuntime;
use function abs;
use function count;
use function floor;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function max;
use function min;

/**
 * The classes and methods of @minecraft/server added on top of ServerModule:
 * entity properties, animations, cameras, input, HUD, raycasts, riding,
 * structures, features, ticking areas, custom dimensions, waypoints,
 * primitive shapes, particles, block volumes, enchantments and cooldowns.
 */
final class ExtendedModule{

	private Interpreter $js;

	private HostClass $camera;
	private HostClass $inputPermissions;
	private HostClass $inputInfo;
	private HostClass $locatorBar;
	private HostClass $waypoint;
	private HostClass $locationWaypoint;
	private HostClass $entityWaypoint;
	private HostClass $containerSlot;
	private HostClass $rideable;
	private HostClass $riding;
	private HostClass $itemComponent;
	private HostClass $enchantable;
	private HostClass $cooldown;
	private HostClass $enchantmentType;
	private HostClass $structure;
	private HostClass $structureManager;
	private HostClass $tickingAreaManager;
	private HostClass $shapesManager;
	private HostClass $textPrimitive;
	private HostClass $molangMap;
	private HostClass $blockVolume;
	private HostClass $biomeType;
	private HostClass $dimensionType;
	private HostClass $dimensionRegistry;

	private int $nextWaypoint = 1;
	private int $nextShape = 1;

	public function __construct(
		private ScriptRuntime $runtime,
		private ClassFactory $f,
		private ServerModule $s
	){
		$this->js = $runtime->js;
	}

	public function define() : void{
		$this->defineEntity();
		$this->definePlayer();
		$this->defineSlots();
		$this->defineItems();
		$this->defineWaypoints();
		$this->defineWorld();
		$this->defineDimensions();
		$this->defineVolumes();
		$this->defineParticles();
	}

	private function string(mixed $value) : string{
		return $this->js->toString($value);
	}

	private function entityId(mixed $thisValue) : int{
		return $this->f->host($thisValue, "entity")["id"];
	}

	private function ownerId(mixed $thisValue) : int{
		return $this->f->host($thisValue, "component")["owner"]->host["id"];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function options(mixed $value) : array{
		if(!$value instanceof JsObject || $value instanceof JsArray){
			return [];
		}
		$result = $this->s->fromJs($value);
		return is_array($result) ? $result : [];
	}

	private function scalar(mixed $value) : mixed{
		return $value instanceof JsNull ? null : $value;
	}

	private function defineEntity() : void{
		$f = $this->f;
		$js = $this->js;
		$entity = $this->s->entity;

		$f->method($entity, "getProperty", function(mixed $thisValue, array $args) : mixed{
			return $this->s->raw("x.prop.get", $this->entityId($thisValue), $this->string($args[0] ?? ""));
		}, 1);
		$f->method($entity, "setProperty", function(mixed $thisValue, array $args) use ($js) : mixed{
			$value = $args[1] ?? null;
			if(!is_bool($value) && !is_int($value) && !is_float($value) && !is_string($value)){
				$js->throwError("TypeError", "Property values must be boolean, number or string");
			}
			try{
				$this->s->raw("x.prop.set", $this->entityId($thisValue), $this->string($args[0] ?? ""), $value);
			}catch(ScriptException $e){
				$js->throwError("Error", $e->getMessage());
			}
			return null;
		}, 2);
		$f->method($entity, "resetProperty", function(mixed $thisValue, array $args) use ($js) : mixed{
			$name = $this->string($args[0] ?? "");
			try{
				$this->s->raw("x.prop.reset", $this->entityId($thisValue), $name);
			}catch(ScriptException $e){
				$js->throwError("Error", $e->getMessage());
			}
			return $this->s->raw("x.prop.get", $this->entityId($thisValue), $name);
		}, 1);
		$f->method($entity, "playAnimation", function(mixed $thisValue, array $args) : mixed{
			$options = $this->options($args[1] ?? null);
			if(isset($options["players"]) && is_array($options["players"])){
				$names = [];
				foreach($options["players"] as $player){
					if(is_string($player)){
						$names[] = $player;
					}
				}
				$options["players"] = $names;
			}
			$this->s->raw("x.anim", $this->entityId($thisValue), $this->string($args[0] ?? ""), $options);
			return null;
		}, 2);
		$f->method($entity, "getAABB", function(mixed $thisValue, array $args) : mixed{
			return $this->s->api("x.aabb", $this->entityId($thisValue));
		});
		$f->method($entity, "getEntitiesFromViewDirection", function(mixed $thisValue, array $args) : mixed{
			return $this->s->api("x.viewEntities", $this->entityId($thisValue), $this->options($args[0] ?? null));
		}, 1);
		$f->method($entity, "getBlockFromViewDirection", function(mixed $thisValue, array $args) : mixed{
			return $this->s->api("x.viewBlock", $this->entityId($thisValue), $this->options($args[0] ?? null));
		}, 1);

		$this->rideable = $f->define("EntityRideableComponent", $this->s->entityComponent);
		$f->staticValue($this->rideable, "componentId", "minecraft:rideable");
		$f->method($this->rideable, "addRider", function(mixed $thisValue, array $args) : mixed{
			return $this->s->raw("x.addRider", $this->ownerId($thisValue), $this->s->fromJs($args[0] ?? null));
		}, 1);
		$f->method($this->rideable, "ejectRider", function(mixed $thisValue, array $args) : mixed{
			$this->s->raw("x.ejectRider", $this->ownerId($thisValue), $this->s->fromJs($args[0] ?? null));
			return null;
		}, 1);
		$f->method($this->rideable, "ejectRiders", function(mixed $thisValue, array $args) : mixed{
			$this->s->raw("x.ejectRiders", $this->ownerId($thisValue));
			return null;
		});
		$f->method($this->rideable, "getRiders", function(mixed $thisValue, array $args) : mixed{
			return $this->s->api("x.riders", $this->ownerId($thisValue));
		});
		$f->method($this->rideable, "getSeats", function(mixed $thisValue, array $args) use ($js) : mixed{
			return $js->newArray([$this->s->toJs(["position" => ["x" => 0, "y" => 0, "z" => 0], "minRiderCount" => 0, "maxRiderCount" => 1, "lockRiderRotation" => 0])]);
		});
		foreach(["controllingSeat" => 0, "crouchingSkipInteract" => true, "interactText" => "", "passengerMaxWidth" => 0, "pullInEntities" => false, "riderCanInteract" => false, "seatCount" => 1] as $name => $value){
			$f->getter($this->rideable, $name, function(mixed $thisValue) use ($value) : mixed{
				return $value;
			});
		}
		$this->s->export("EntityRideableComponent", $this->rideable);

		$this->riding = $f->define("EntityRidingComponent", $this->s->entityComponent);
		$f->staticValue($this->riding, "componentId", "minecraft:riding");
		$f->getter($this->riding, "entityRidingOn", function(mixed $thisValue) : mixed{
			return $this->s->api("x.vehicle", $this->ownerId($thisValue));
		});
		$this->s->export("EntityRidingComponent", $this->riding);

		$this->itemComponent = $f->define("EntityItemComponent", $this->s->entityComponent);
		$f->staticValue($this->itemComponent, "componentId", "minecraft:item");
		$f->getter($this->itemComponent, "itemStack", function(mixed $thisValue) : mixed{
			return $this->s->api("x.itemEntity", $this->ownerId($thisValue));
		});
		$this->s->export("EntityItemComponent", $this->itemComponent);

		$projectile = $f->define("EntityProjectileComponent", $this->s->entityComponent);
		$f->staticValue($projectile, "componentId", "minecraft:projectile");
		foreach(["gravity", "airInertia", "liquidInertia"] as $name){
			$f->getter($projectile, $name, function(mixed $thisValue) use ($name) : mixed{
				return Interpreter::intOrFloat((float) $this->s->raw("x.proj.get", $this->ownerId($thisValue))[$name]);
			}, function(mixed $thisValue, mixed $value) use ($name) : void{
				$this->s->raw("x.proj.set", $this->ownerId($thisValue), $name, $this->js->toNumber($value));
			});
		}
		$f->getter($projectile, "owner", function(mixed $thisValue) : mixed{
			return $this->s->toJs($this->s->raw("x.proj.get", $this->ownerId($thisValue))["owner"]);
		}, function(mixed $thisValue, mixed $value) : void{
			$this->s->raw("x.proj.set", $this->ownerId($thisValue), "owner", $value instanceof JsObject ? $this->s->fromJs($value) : null);
		});
		foreach(["catchFireOnHurt" => false, "critParticlesOnProjectileHurt" => false, "destroyOnProjectileHurt" => false, "hitEntitySound" => null, "hitGroundSound" => null, "hitParticle" => null, "lightningStrikeOnHit" => false, "onFireTime" => 0, "shouldBounceOnHit" => false, "stopOnHit" => false] as $name => $value){
			$f->getter($projectile, $name, function(mixed $thisValue) use ($value) : mixed{
				return $value;
			});
		}
		$f->method($projectile, "shoot", function(mixed $thisValue, array $args) : mixed{
			$options = $this->options($args[1] ?? null);
			$this->s->raw("x.proj.shoot", $this->ownerId($thisValue), $this->s->vector($args[0] ?? null), $options["uncertainty"] ?? 0);
			return null;
		}, 2);
		$this->s->export("EntityProjectileComponent", $projectile);
		$this->s->entityComponents["minecraft:projectile"] = $projectile;
		$this->s->entityComponents["minecraft:rideable"] = $this->rideable;
		$this->s->entityComponents["minecraft:riding"] = $this->riding;

		$typeFamily = $f->define("EntityTypeFamilyComponent", $this->s->entityComponent);
		$f->staticValue($typeFamily, "componentId", "minecraft:type_family");
		$f->method($typeFamily, "getTypeFamilies", function(mixed $thisValue, array $args) : mixed{
			return $this->s->api("x.families", $this->ownerId($thisValue));
		});
		$f->method($typeFamily, "hasTypeFamily", function(mixed $thisValue, array $args) : mixed{
			return \in_array(\strtolower($this->string($args[0] ?? "")), $this->s->raw("x.families", $this->ownerId($thisValue)), true);
		}, 1);
		$this->s->export("EntityTypeFamilyComponent", $typeFamily);
		$this->s->entityComponents["minecraft:type_family"] = $typeFamily;
		$this->s->entityComponents["minecraft:item"] = $this->itemComponent;
	}

	private function definePlayer() : void{
		$f = $this->f;
		$js = $this->js;
		$player = $this->s->player;
		$screen = $this->s->screenDisplay;

		$this->camera = $f->define("Camera");
		$cameraPlayer = function(mixed $thisValue) : int{
			return $this->f->host($thisValue, "camera")["player"];
		};
		$f->getter($this->camera, "isValid", function(mixed $thisValue) use ($cameraPlayer) : mixed{
			return $this->s->raw("ent.valid", $cameraPlayer($thisValue));
		});
		$f->method($this->camera, "setCamera", function(mixed $thisValue, array $args) use ($cameraPlayer, $js) : mixed{
			$options = $this->options($args[1] ?? null);
			try{
				$this->s->raw("x.camera.set", $cameraPlayer($thisValue), $this->string($args[0] ?? ""), $options);
			}catch(ScriptException $e){
				$js->throwError("Error", $e->getMessage());
			}
			return null;
		}, 2);
		$f->method($this->camera, "clear", function(mixed $thisValue, array $args) use ($cameraPlayer) : mixed{
			$this->s->raw("x.camera.clear", $cameraPlayer($thisValue));
			return null;
		});
		$f->method($this->camera, "fade", function(mixed $thisValue, array $args) use ($cameraPlayer) : mixed{
			$this->s->raw("x.camera.fade", $cameraPlayer($thisValue), $this->options($args[0] ?? null));
			return null;
		}, 1);
		$this->s->export("Camera", $this->camera);
		$f->getter($player, "camera", function(mixed $thisValue) : mixed{
			return $this->f->instance($this->camera, ["kind" => "camera", "player" => $this->entityId($thisValue)]);
		});

		$this->inputPermissions = $f->define("PlayerInputPermissions");
		$inputPlayer = function(mixed $thisValue) : int{
			return $this->f->host($thisValue, "input")["player"];
		};
		$f->method($this->inputPermissions, "setPermissionCategory", function(mixed $thisValue, array $args) use ($inputPlayer) : mixed{
			$this->s->raw("x.input.set", $inputPlayer($thisValue), $this->js->toNumber($args[0] ?? 0), $this->js->toBoolean($args[1] ?? true));
			return null;
		}, 2);
		$f->method($this->inputPermissions, "isPermissionCategoryEnabled", function(mixed $thisValue, array $args) use ($inputPlayer) : mixed{
			return $this->s->raw("x.input.get", $inputPlayer($thisValue), $this->js->toNumber($args[0] ?? 0));
		}, 1);
		foreach(["cameraEnabled" => 1, "movementEnabled" => 2] as $name => $category){
			$f->getter($this->inputPermissions, $name, function(mixed $thisValue) use ($inputPlayer, $category) : mixed{
				return $this->s->raw("x.input.get", $inputPlayer($thisValue), $category);
			}, function(mixed $thisValue, mixed $value) use ($inputPlayer, $category) : void{
				$this->s->raw("x.input.set", $inputPlayer($thisValue), $category, $this->js->toBoolean($value));
			});
		}
		$this->s->export("PlayerInputPermissions", $this->inputPermissions);
		$f->getter($player, "inputPermissions", function(mixed $thisValue) : mixed{
			return $this->f->instance($this->inputPermissions, ["kind" => "input", "player" => $this->entityId($thisValue)]);
		});

		$this->inputInfo = $f->define("InputInfo");
		$f->method($this->inputInfo, "getButtonState", function(mixed $thisValue, array $args) use ($inputPlayer) : mixed{
			return $this->s->raw("x.input.button", $inputPlayer($thisValue), $this->string($args[0] ?? ""));
		}, 1);
		$f->method($this->inputInfo, "getMovementVector", function(mixed $thisValue, array $args) use ($inputPlayer) : mixed{
			return $this->s->api("x.input.move", $inputPlayer($thisValue));
		});
		$f->getter($this->inputInfo, "lastInputModeUsed", function(mixed $thisValue) use ($inputPlayer) : mixed{
			return $this->s->raw("x.input.mode", $inputPlayer($thisValue));
		});
		$f->getter($this->inputInfo, "touchOnlyAffectsHotbar", function(mixed $thisValue) : mixed{
			return false;
		});
		$this->s->export("InputInfo", $this->inputInfo);
		$f->getter($player, "inputInfo", function(mixed $thisValue) : mixed{
			return $this->f->instance($this->inputInfo, ["kind" => "input", "player" => $this->entityId($thisValue)]);
		});
		$f->getter($player, "isJumping", function(mixed $thisValue) : mixed{
			return $this->s->raw("x.input.button", $this->entityId($thisValue), "Jump") === "Pressed";
		});
		$f->getter($player, "playerPermissionLevel", function(mixed $thisValue) : mixed{
			return $this->s->raw("ent.get", $this->entityId($thisValue), "op") === true ? 2 : 1;
		});
		$f->getter($player, "commandPermissionLevel", function(mixed $thisValue) : mixed{
			return $this->s->raw("ent.get", $this->entityId($thisValue), "op") === true ? 2 : 0;
		});

		$f->method($player, "spawnParticle", function(mixed $thisValue, array $args) : mixed{
			$id = $this->entityId($thisValue);
			$dimension = $this->s->raw("ent.get", $id, "dimension");
			$this->s->raw("x.particle", ["\$d" => $dimension], $this->string($args[0] ?? ""), $this->s->vector($args[1] ?? null), $this->molangVariables($args[2] ?? null), $id);
			return null;
		}, 3);

		$screenPlayer = function(mixed $thisValue) : int{
			return $this->f->host($thisValue, "screen")["player"];
		};
		$elements = function(mixed $value) : ?array{
			if(!$value instanceof JsArray){
				return null;
			}
			$result = [];
			foreach($value->items as $item){
				if(is_int($item) || is_float($item)){
					$result[] = (int) $item;
				}
			}
			return $result;
		};
		$f->method($screen, "hideAllExcept", function(mixed $thisValue, array $args) use ($screenPlayer, $elements) : mixed{
			$this->s->raw("x.hud.except", $screenPlayer($thisValue), $elements($args[0] ?? null) ?? []);
			return null;
		}, 1);
		$f->method($screen, "resetHudElementsVisibility", function(mixed $thisValue, array $args) use ($screenPlayer) : mixed{
			$this->s->raw("x.hud.set", $screenPlayer($thisValue), 1, null);
			return null;
		});
		$f->method($screen, "resetHudElements", function(mixed $thisValue, array $args) use ($screenPlayer) : mixed{
			$this->s->raw("x.hud.set", $screenPlayer($thisValue), 1, null);
			return null;
		});
		$f->method($screen, "setHudVisibility", function(mixed $thisValue, array $args) use ($screenPlayer, $elements) : mixed{
			$this->s->raw("x.hud.set", $screenPlayer($thisValue), (int) $this->js->toNumber($args[0] ?? 1), $elements($args[1] ?? null));
			return null;
		}, 2);
		$f->method($screen, "isForcedHidden", function(mixed $thisValue, array $args) use ($screenPlayer) : mixed{
			return $this->s->raw("x.hud.hidden", $screenPlayer($thisValue), (int) $this->js->toNumber($args[0] ?? 0));
		}, 1);

		$this->locatorBar = $f->define("LocatorBar");
		$barPlayer = function(mixed $thisValue) : int{
			return $this->f->host($thisValue, "locator")["player"];
		};
		$f->method($this->locatorBar, "addWaypoint", function(mixed $thisValue, array $args) use ($barPlayer, $js) : mixed{
			$waypoint = $args[0] ?? null;
			if(!$waypoint instanceof JsObject || ($waypoint->host["kind"] ?? null) !== "waypoint"){
				$js->throwError("TypeError", "Expected a Waypoint");
			}
			try{
				$this->s->raw("x.waypoint.add", $barPlayer($thisValue), $waypoint->host["key"], $this->waypointData($waypoint));
			}catch(ScriptException $e){
				$js->throwError("Error", $e->getMessage());
			}
			$this->s->waypoints[$waypoint->host["key"]] = $waypoint;
			return null;
		}, 1);
		$f->method($this->locatorBar, "removeWaypoint", function(mixed $thisValue, array $args) use ($barPlayer) : mixed{
			$waypoint = $args[0] ?? null;
			if(!$waypoint instanceof JsObject || ($waypoint->host["kind"] ?? null) !== "waypoint"){
				return false;
			}
			return $this->s->raw("x.waypoint.remove", $barPlayer($thisValue), $waypoint->host["key"]);
		}, 1);
		$f->method($this->locatorBar, "hasWaypoint", function(mixed $thisValue, array $args) use ($barPlayer) : mixed{
			$waypoint = $args[0] ?? null;
			if(!$waypoint instanceof JsObject || ($waypoint->host["kind"] ?? null) !== "waypoint"){
				return false;
			}
			return $this->s->raw("x.waypoint.has", $barPlayer($thisValue), $waypoint->host["key"]);
		}, 1);
		$f->method($this->locatorBar, "getAllWaypoints", function(mixed $thisValue, array $args) use ($barPlayer, $js) : mixed{
			$result = [];
			foreach($this->s->raw("x.waypoint.list", $barPlayer($thisValue)) as $key){
				$waypoint = $this->s->waypoints[$key] ?? null;
				if($waypoint !== null){
					$result[] = $waypoint;
				}
			}
			return $js->newArray($result);
		});
		$f->method($this->locatorBar, "removeAllWaypoints", function(mixed $thisValue, array $args) use ($barPlayer) : mixed{
			$this->s->raw("x.waypoint.clear", $barPlayer($thisValue));
			return null;
		});
		$f->getter($this->locatorBar, "count", function(mixed $thisValue) use ($barPlayer) : mixed{
			return count($this->s->raw("x.waypoint.list", $barPlayer($thisValue)));
		});
		$f->getter($this->locatorBar, "maxCount", function(mixed $thisValue) : mixed{
			return 100;
		});
		$this->s->export("LocatorBar", $this->locatorBar);
		$f->getter($player, "locatorBar", function(mixed $thisValue) : mixed{
			return $this->f->instance($this->locatorBar, ["kind" => "locator", "player" => $this->entityId($thisValue)]);
		});
	}

	private function defineSlots() : void{
		$f = $this->f;
		$js = $this->js;
		$this->containerSlot = $f->define("ContainerSlot");
		$read = function(mixed $thisValue) : mixed{
			$host = $this->f->host($thisValue, "slot");
			if($host["mode"] === "equip"){
				return $this->s->raw("ent.equip.get", $host["owner"], $host["slot"]);
			}
			return $this->s->raw("cont.get", ['$c' => $host["ref"]], $host["slot"]);
		};
		$write = function(mixed $thisValue, mixed $item) : void{
			$host = $this->f->host($thisValue, "slot");
			if($host["mode"] === "equip"){
				$this->s->raw("ent.equip.set", $host["owner"], $host["slot"], $item);
				return;
			}
			$this->s->raw("cont.set", ['$c' => $host["ref"]], $host["slot"], $item);
		};
		$f->method($this->containerSlot, "getItem", function(mixed $thisValue, array $args) use ($read) : mixed{
			return $this->s->toJs($read($thisValue));
		});
		$f->method($this->containerSlot, "setItem", function(mixed $thisValue, array $args) use ($write) : mixed{
			$write($thisValue, $this->s->fromJs($args[0] ?? null));
			return null;
		}, 1);
		$f->method($this->containerSlot, "hasItem", function(mixed $thisValue, array $args) use ($read) : mixed{
			return $read($thisValue) !== null;
		});
		$f->getter($this->containerSlot, "isValid", function(mixed $thisValue) : mixed{
			$host = $this->f->host($thisValue, "slot");
			return $host["mode"] === "equip" ? $this->s->raw("ent.valid", $host["owner"]) : $this->s->raw("cont.valid", ['$c' => $host["ref"]]);
		});
		$field = function(mixed $thisValue, string $key, mixed $default) use ($read, $js) : mixed{
			$item = $read($thisValue);
			if(!is_array($item)){
				$js->throwError("Error", "The slot is empty");
			}
			return $item['$i'][$key] ?? $default;
		};
		$update = function(mixed $thisValue, \Closure $change) use ($read, $write, $js) : void{
			$item = $read($thisValue);
			if(!is_array($item)){
				$js->throwError("Error", "The slot is empty");
			}
			$item['$i'] = $change($item['$i']);
			$write($thisValue, $item);
		};
		$f->getter($this->containerSlot, "typeId", function(mixed $thisValue) use ($read) : mixed{
			$item = $read($thisValue);
			return is_array($item) ? $item['$i']["t"] : null;
		});
		$f->getter($this->containerSlot, "amount", function(mixed $thisValue) use ($field) : mixed{
			return $field($thisValue, "c", 0);
		}, function(mixed $thisValue, mixed $value) use ($update, $js) : void{
			$amount = (int) $js->toNumber($value);
			if($amount < 1 || $amount > 255){
				$js->throwError("RangeError", "Amount must be an integer between 1 and 255");
			}
			$update($thisValue, function(array $data) use ($amount) : array{
				$data["c"] = $amount;
				return $data;
			});
		});
		$f->getter($this->containerSlot, "maxAmount", function(mixed $thisValue) use ($field) : mixed{
			return $field($thisValue, "m", 64);
		});
		$f->getter($this->containerSlot, "isStackable", function(mixed $thisValue) use ($field) : mixed{
			return $field($thisValue, "m", 64) > 1;
		});
		$f->getter($this->containerSlot, "nameTag", function(mixed $thisValue) use ($field) : mixed{
			return $field($thisValue, "n", null);
		}, function(mixed $thisValue, mixed $value) use ($update) : void{
			$name = ($value === null || $value instanceof JsNull || $value === "") ? null : $this->string($value);
			$update($thisValue, function(array $data) use ($name) : array{
				if($name === null){
					unset($data["n"]);
				}else{
					$data["n"] = $name;
				}
				return $data;
			});
		});
		$f->getter($this->containerSlot, "keepOnDeath", function(mixed $thisValue) : mixed{
			return false;
		});
		$f->getter($this->containerSlot, "lockMode", function(mixed $thisValue) : mixed{
			return "none";
		});
		$f->getter($this->containerSlot, "type", function(mixed $thisValue) use ($read) : mixed{
			$item = $read($thisValue);
			return is_array($item) ? $this->f->instance($this->s->itemType, ["kind" => "type", "id" => $item['$i']["t"]]) : null;
		});
		$f->method($this->containerSlot, "getLore", function(mixed $thisValue, array $args) use ($field, $js) : mixed{
			return $js->newArray($field($thisValue, "l", []));
		});
		$f->method($this->containerSlot, "setLore", function(mixed $thisValue, array $args) use ($update) : mixed{
			$lore = [];
			$list = $args[0] ?? null;
			if($list instanceof JsArray){
				foreach($list->items as $line){
					$lore[] = $this->s->text($line);
				}
			}
			$update($thisValue, function(array $data) use ($lore) : array{
				$data["l"] = $lore;
				return $data;
			});
			return null;
		}, 1);
		$f->method($this->containerSlot, "isStackableWith", function(mixed $thisValue, array $args) use ($read) : mixed{
			$item = $read($thisValue);
			$other = $args[0] ?? null;
			if(!is_array($item) || !$other instanceof JsObject){
				return false;
			}
			return $this->js->call($this->js->get($this->s->toJs($item), "isStackableWith"), $this->s->toJs($item), [$other]);
		}, 1);
		$slotTags = function(mixed $thisValue) use ($read) : array{
			$item = $read($thisValue);
			if(!is_array($item)){
				return [];
			}
			try{
				$tags = $this->s->raw("tag.item", $item);
			}catch(ScriptException){
				return [];
			}
			return is_array($tags) ? $tags : [];
		};
		$f->method($this->containerSlot, "getTags", function(mixed $thisValue, array $args) use ($js, $slotTags) : mixed{
			return $js->newArray($slotTags($thisValue));
		});
		$f->method($this->containerSlot, "hasTag", function(mixed $thisValue, array $args) use ($slotTags) : mixed{
			return \in_array($this->string($args[0] ?? ""), $slotTags($thisValue), true);
		}, 1);
		$this->s->export("ContainerSlot", $this->containerSlot);

		$f->method($this->s->container, "getSlot", function(mixed $thisValue, array $args) use ($js) : mixed{
			$ref = $this->f->host($thisValue, "container")["ref"];
			$slot = (int) $js->toNumber($args[0] ?? 0);
			$size = (int) $this->s->raw("cont.size", ['$c' => $ref]);
			if($slot < 0 || $slot >= $size){
				$js->throwError("RangeError", "Slot " . $slot . " is out of bounds");
			}
			return $this->f->instance($this->containerSlot, ["kind" => "slot", "mode" => "container", "ref" => $ref, "slot" => $slot]);
		}, 1);
		$f->method($this->s->equippable, "getEquipmentSlot", function(mixed $thisValue, array $args) : mixed{
			return $this->f->instance($this->containerSlot, ["kind" => "slot", "mode" => "equip", "owner" => $this->ownerId($thisValue), "slot" => $this->string($args[0] ?? "Mainhand")]);
		}, 1);
	}

	private function defineItems() : void{
		$f = $this->f;
		$js = $this->js;
		$itemData = function(JsObject $item) : array{
			$host = $item->host;
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
		};
		$owner = function(mixed $thisValue) : JsObject{
			return $this->f->host($thisValue, "component")["owner"];
		};

		$this->enchantmentType = $f->define("EnchantmentType");
		$f->getter($this->enchantmentType, "id", function(mixed $thisValue) : mixed{
			return $this->f->host($thisValue, "enchantmenttype")["id"];
		});
		$f->getter($this->enchantmentType, "maxLevel", function(mixed $thisValue) : mixed{
			return $this->f->host($thisValue, "enchantmenttype")["maxLevel"];
		});
		$this->s->export("EnchantmentType", $this->enchantmentType);
		$typeObject = function(array $data) : JsObject{
			return $this->f->instance($this->enchantmentType, ["kind" => "enchantmenttype", "id" => (string) $data["id"], "maxLevel" => (int) $data["maxLevel"]]);
		};
		$enchantmentTypes = $f->define("EnchantmentTypes");
		$f->staticMethod($enchantmentTypes, "get", function(mixed $thisValue, array $args) use ($typeObject) : mixed{
			$data = $this->s->raw("x.ench.type", $this->string($args[0] ?? ""));
			return is_array($data) ? $typeObject($data) : null;
		}, 1);
		$f->staticMethod($enchantmentTypes, "getAll", function(mixed $thisValue, array $args) use ($typeObject, $js) : mixed{
			$result = [];
			foreach($this->s->raw("x.ench.types") as $data){
				$result[] = $typeObject($data);
			}
			return $js->newArray($result);
		});
		$this->s->export("EnchantmentTypes", $enchantmentTypes);
		$typeId = function(mixed $value) : string{
			if($value instanceof JsObject && ($value->host["kind"] ?? null) === "enchantmenttype"){
				return $value->host["id"];
			}
			return $this->string($value);
		};

		$this->enchantable = $f->define("ItemEnchantableComponent", $this->s->itemComponent);
		$f->staticValue($this->enchantable, "componentId", "minecraft:enchantable");
		$list = function(mixed $thisValue) use ($owner, $itemData) : array{
			return $this->s->raw("x.ench.get", $itemData($owner($thisValue)));
		};
		$store = function(mixed $thisValue, array $enchantments) use ($owner, $itemData, $js) : void{
			$item = $owner($thisValue);
			try{
				$data = $this->s->raw("x.ench.set", $itemData($item), $enchantments);
			}catch(ScriptException $e){
				$js->throwError("Error", $e->getMessage());
			}
			if(is_array($data) && isset($data["nbt"])){
				$item->host["nbt"] = $data["nbt"];
			}
		};
		$entry = function(array $data) use ($typeObject) : JsObject{
			$object = $this->js->newObject();
			$object->props["type"] = $typeObject($data);
			$object->props["level"] = $data["level"];
			return $object;
		};
		$f->method($this->enchantable, "getEnchantments", function(mixed $thisValue, array $args) use ($list, $entry, $js) : mixed{
			$result = [];
			foreach($list($thisValue) as $data){
				$result[] = $entry($data);
			}
			return $js->newArray($result);
		});
		$f->method($this->enchantable, "getEnchantment", function(mixed $thisValue, array $args) use ($list, $entry, $typeId) : mixed{
			$id = $typeId($args[0] ?? "");
			foreach($list($thisValue) as $data){
				if($data["id"] === $id || "minecraft:" . $data["id"] === $id){
					return $entry($data);
				}
			}
			return null;
		}, 1);
		$f->method($this->enchantable, "hasEnchantment", function(mixed $thisValue, array $args) use ($list, $typeId) : mixed{
			$id = $typeId($args[0] ?? "");
			foreach($list($thisValue) as $data){
				if($data["id"] === $id || "minecraft:" . $data["id"] === $id){
					return $data["level"];
				}
			}
			return 0;
		}, 1);
		$toEntry = function(mixed $value) use ($typeId) : array{
			if(!$value instanceof JsObject){
				$this->js->throwError("TypeError", "Expected an Enchantment");
			}
			return ["id" => $typeId($this->js->get($value, "type")), "level" => (int) $this->js->toNumber($this->js->get($value, "level") ?? 1)];
		};
		$add = function(mixed $thisValue, array $entries) use ($list, $store) : void{
			$current = [];
			foreach($list($thisValue) as $data){
				$current[$data["id"]] = ["id" => $data["id"], "level" => $data["level"]];
			}
			foreach($entries as $new){
				$key = \str_starts_with($new["id"], "minecraft:") ? \substr($new["id"], 10) : $new["id"];
				$current[$key] = ["id" => $key, "level" => $new["level"]];
			}
			$store($thisValue, \array_values($current));
		};
		$f->method($this->enchantable, "addEnchantment", function(mixed $thisValue, array $args) use ($add, $toEntry) : mixed{
			$add($thisValue, [$toEntry($args[0] ?? null)]);
			return null;
		}, 1);
		$f->method($this->enchantable, "addEnchantments", function(mixed $thisValue, array $args) use ($add, $toEntry) : mixed{
			$entries = [];
			$values = $args[0] ?? null;
			if($values instanceof JsArray){
				foreach($values->items as $value){
					$entries[] = $toEntry($value);
				}
			}
			$add($thisValue, $entries);
			return null;
		}, 1);
		$f->method($this->enchantable, "canAddEnchantment", function(mixed $thisValue, array $args) use ($toEntry) : mixed{
			$new = $toEntry($args[0] ?? null);
			$type = $this->s->raw("x.ench.type", $new["id"]);
			return is_array($type) && $new["level"] >= 1 && $new["level"] <= $type["maxLevel"];
		}, 1);
		$f->method($this->enchantable, "removeEnchantment", function(mixed $thisValue, array $args) use ($list, $store, $typeId) : mixed{
			$id = $typeId($args[0] ?? "");
			$kept = [];
			foreach($list($thisValue) as $data){
				if($data["id"] !== $id && "minecraft:" . $data["id"] !== $id){
					$kept[] = ["id" => $data["id"], "level" => $data["level"]];
				}
			}
			$store($thisValue, $kept);
			return null;
		}, 1);
		$f->method($this->enchantable, "removeAllEnchantments", function(mixed $thisValue, array $args) use ($store) : mixed{
			$store($thisValue, []);
			return null;
		});
		$f->getter($this->enchantable, "slots", function(mixed $thisValue) use ($js) : mixed{
			return $js->newArray();
		});
		$this->s->export("ItemEnchantableComponent", $this->enchantable);
		$this->s->itemComponents["minecraft:enchantable"] = function(mixed $item) : ?JsObject{
			return $this->f->instance($this->enchantable, ["kind" => "component", "owner" => $item, "typeId" => "minecraft:enchantable"]);
		};

		$this->cooldown = $f->define("ItemCooldownComponent", $this->s->itemComponent);
		$f->staticValue($this->cooldown, "componentId", "minecraft:cooldown");
		$info = function(mixed $thisValue) use ($owner, $itemData) : array{
			return $this->s->raw("x.cooldown.info", $itemData($owner($thisValue))) ?? ["category" => "", "ticks" => 0];
		};
		$f->getter($this->cooldown, "cooldownCategory", function(mixed $thisValue) use ($info) : mixed{
			return $info($thisValue)["category"];
		});
		$f->getter($this->cooldown, "cooldownTicks", function(mixed $thisValue) use ($info) : mixed{
			return $info($thisValue)["ticks"];
		});
		$f->method($this->cooldown, "startCooldown", function(mixed $thisValue, array $args) use ($owner, $itemData) : mixed{
			$player = $args[0] ?? null;
			$this->s->raw("x.cooldown.start", $this->s->fromJs($player), $itemData($owner($thisValue)));
			return null;
		}, 1);
		$f->method($this->cooldown, "getCooldownTicksRemaining", function(mixed $thisValue, array $args) use ($owner, $itemData) : mixed{
			return $this->s->raw("x.cooldown.remaining", $this->s->fromJs($args[0] ?? null), $itemData($owner($thisValue)));
		}, 1);
		$f->method($this->cooldown, "isCooldownCategory", function(mixed $thisValue, array $args) use ($info) : mixed{
			return $info($thisValue)["category"] === $this->string($args[0] ?? "");
		}, 1);
		$this->s->export("ItemCooldownComponent", $this->cooldown);
		$this->s->itemComponents["minecraft:cooldown"] = function(mixed $item) use ($itemData) : ?JsObject{
			if(!$item instanceof JsObject || $this->s->raw("x.cooldown.info", $itemData($item)) === null){
				return null;
			}
			return $this->f->instance($this->cooldown, ["kind" => "component", "owner" => $item, "typeId" => "minecraft:cooldown"]);
		};
	}

	/**
	 * @return array<string, mixed>
	 */
	private function waypointData(JsObject $waypoint) : array{
		$host = $waypoint->host;
		$data = ["enabled" => $host["enabled"]];
		if(is_array($host["location"] ?? null)){
			$data += $host["location"];
		}
		if(isset($host["entity"])){
			$data["entity"] = $host["entity"];
		}
		$texture = $host["texture"] ?? null;
		if(is_array($texture)){
			$bounds = $texture["textureBoundsList"] ?? null;
			$first = is_array($bounds) ? ($bounds[0]["texture"] ?? null) : null;
			if(is_array($first)){
				$data["texture"] = $first["path"] ?? null;
				$data["iconWidth"] = $first["iconWidth"] ?? 1;
				$data["iconHeight"] = $first["iconHeight"] ?? 1;
			}
		}
		if(is_array($host["color"] ?? null)){
			$data["color"] = $host["color"];
		}
		return $data;
	}

	private function defineWaypoints() : void{
		$f = $this->f;
		$js = $this->js;
		$this->waypoint = $f->define("Waypoint");
		$sync = function(JsObject $waypoint) : void{
			$this->s->raw("x.waypoint.update", $waypoint->host["key"], $this->waypointData($waypoint));
		};
		$f->getter($this->waypoint, "isValid", function(mixed $thisValue) : mixed{
			$host = $this->f->host($thisValue, "waypoint");
			return !isset($host["entity"]) || $this->s->raw("ent.valid", $host["entity"]['$e'] ?? null) === true;
		});
		$f->getter($this->waypoint, "isEnabled", function(mixed $thisValue) : mixed{
			return $this->f->host($thisValue, "waypoint")["enabled"];
		}, function(mixed $thisValue, mixed $value) use ($sync) : void{
			$this->f->host($thisValue, "waypoint");
			$thisValue->host["enabled"] = $this->js->toBoolean($value);
			$sync($thisValue);
		});
		$f->getter($this->waypoint, "color", function(mixed $thisValue) : mixed{
			return $this->s->toJs($this->f->host($thisValue, "waypoint")["color"] ?? null);
		}, function(mixed $thisValue, mixed $value) use ($sync) : void{
			$this->f->host($thisValue, "waypoint");
			$thisValue->host["color"] = $value instanceof JsObject ? $this->s->fromJs($value) : null;
			$sync($thisValue);
		});
		$f->getter($this->waypoint, "textureSelector", function(mixed $thisValue) : mixed{
			return $this->s->toJs($this->f->host($thisValue, "waypoint")["texture"] ?? null);
		}, function(mixed $thisValue, mixed $value) use ($sync) : void{
			$this->f->host($thisValue, "waypoint");
			$thisValue->host["texture"] = $value instanceof JsObject ? $this->s->fromJs($value) : null;
			$sync($thisValue);
		});
		$this->s->export("Waypoint", $this->waypoint);

		$construct = function(string $name, array $args, JsObject $newTarget, HostClass $class, \Closure $target) use ($js) : JsObject{
			$texture = $args[1] ?? null;
			$color = $args[2] ?? null;
			$waypoint = $this->f->instance($class, [
				"kind" => "waypoint",
				"key" => "w" . $this->nextWaypoint++,
				"enabled" => true,
				"texture" => $texture instanceof JsObject ? $this->s->fromJs($texture) : null,
				"color" => $color instanceof JsObject ? $this->s->fromJs($color) : null
			] + $target($args[0] ?? null));
			$waypoint->proto = $js->prototypeFor($newTarget, $class->prototype);
			return $waypoint;
		};
		$this->locationWaypoint = $f->define("LocationWaypoint", $this->waypoint, function(array $args, JsObject $newTarget) use ($construct) : JsObject{
			return $construct("LocationWaypoint", $args, $newTarget, $this->locationWaypoint, function(mixed $location) : array{
				if(!$location instanceof JsObject){
					$this->js->throwError("TypeError", "Expected a DimensionLocation");
				}
				$vector = $this->s->vector($location);
				$dimension = $this->s->fromJs($this->js->get($location, "dimension"));
				return ["location" => $vector + ["dimension" => is_array($dimension) ? $dimension : ["\$d" => "minecraft:overworld"]]];
			});
		}, 3);
		$f->getter($this->locationWaypoint, "dimensionLocation", function(mixed $thisValue) : mixed{
			return $this->s->toJs($this->f->host($thisValue, "waypoint")["location"]);
		}, function(mixed $thisValue, mixed $value) use ($sync) : void{
			$this->f->host($thisValue, "waypoint");
			$vector = $this->s->vector($value);
			$dimension = $this->s->fromJs($this->js->get($value, "dimension"));
			$thisValue->host["location"] = $vector + ["dimension" => is_array($dimension) ? $dimension : ["\$d" => "minecraft:overworld"]];
			$sync($thisValue);
		});
		$this->s->export("LocationWaypoint", $this->locationWaypoint);

		$this->entityWaypoint = $f->define("EntityWaypoint", $this->waypoint, function(array $args, JsObject $newTarget) use ($construct) : JsObject{
			return $construct("EntityWaypoint", $args, $newTarget, $this->entityWaypoint, function(mixed $entity) : array{
				if(!$entity instanceof JsObject || ($entity->host["kind"] ?? null) !== "entity"){
					$this->js->throwError("TypeError", "Expected an Entity");
				}
				return ["entity" => $this->s->fromJs($entity), "entityObject" => $entity];
			});
		}, 3);
		$f->getter($this->entityWaypoint, "entity", function(mixed $thisValue) : mixed{
			return $this->f->host($thisValue, "waypoint")["entityObject"] ?? null;
		});
		$this->s->export("EntityWaypoint", $this->entityWaypoint);
	}

	private function defineWorld() : void{
		$f = $this->f;
		$js = $this->js;
		$world = $this->s->worldObject;

		$this->structure = $f->define("Structure");
		$structureId = function(mixed $thisValue) : string{
			return $this->f->host($thisValue, "structure")["id"];
		};
		$info = function(mixed $thisValue) use ($structureId, $js) : array{
			$data = $this->s->raw("x.struct.info", $structureId($thisValue));
			if(!is_array($data)){
				$js->throwError("InvalidStructureError", "The structure " . $structureId($thisValue) . " was deleted");
			}
			return $data;
		};
		$f->getter($this->structure, "id", $structureId);
		$f->getter($this->structure, "size", function(mixed $thisValue) use ($info) : mixed{
			return $this->s->toJs($info($thisValue)["size"]);
		});
		$f->getter($this->structure, "isValid", function(mixed $thisValue) use ($structureId) : mixed{
			return $this->s->raw("x.struct.info", $structureId($thisValue)) !== null;
		});
		$f->method($this->structure, "getBlockPermutation", function(mixed $thisValue, array $args) use ($structureId, $js) : mixed{
			try{
				return $this->s->api("x.struct.getBlock", $structureId($thisValue), $this->s->vector($args[0] ?? null));
			}catch(ScriptException $e){
				$js->throwError("InvalidStructureError", $e->getMessage());
			}
		}, 1);
		$f->method($this->structure, "setBlockPermutation", function(mixed $thisValue, array $args) use ($structureId, $js) : mixed{
			$permutation = $args[1] ?? null;
			try{
				$this->s->raw("x.struct.setBlock", $structureId($thisValue), $this->s->vector($args[0] ?? null), $permutation instanceof JsObject ? $this->s->fromJs($permutation) : null);
			}catch(ScriptException $e){
				$js->throwError("InvalidStructureError", $e->getMessage());
			}
			return null;
		}, 3);
		$f->method($this->structure, "getIsWaterlogged", function(mixed $thisValue, array $args) : mixed{
			return false;
		}, 1);
		$f->method($this->structure, "saveAs", function(mixed $thisValue, array $args) use ($structureId) : mixed{
			$id = $this->string($args[0] ?? "");
			$mode = ($args[1] ?? null) === null ? "World" : $this->string($args[1]);
			$this->s->raw("x.struct.saveAs", $structureId($thisValue), $id, $mode);
			return $this->f->instance($this->structure, ["kind" => "structure", "id" => $this->s->raw("x.struct.info", $id)["id"] ?? $id]);
		}, 2);
		$f->method($this->structure, "saveToWorld", function(mixed $thisValue, array $args) use ($structureId) : mixed{
			$this->s->raw("x.struct.mode", $structureId($thisValue), "World");
			return null;
		});
		$this->s->export("Structure", $this->structure);
		$structureObject = function(mixed $info) : mixed{
			return is_array($info) ? $this->f->instance($this->structure, ["kind" => "structure", "id" => $info["id"]]) : null;
		};
		$structureRef = function(mixed $value) : string{
			if($value instanceof JsObject && ($value->host["kind"] ?? null) === "structure"){
				return $value->host["id"];
			}
			return $this->string($value);
		};

		$this->structureManager = $f->define("StructureManager");
		$wrap = function(\Closure $call) use ($js) : mixed{
			try{
				return $call();
			}catch(ScriptException $e){
				$js->throwError("Error", $e->getMessage());
			}
		};
		$f->method($this->structureManager, "createFromWorld", function(mixed $thisValue, array $args) use ($structureObject, $wrap) : mixed{
			return $wrap(fn() => $structureObject($this->s->raw("x.struct.create", $this->string($args[0] ?? ""), $this->s->fromJs($args[1] ?? null), $this->s->vector($args[2] ?? null), $this->s->vector($args[3] ?? null), $this->options($args[4] ?? null))));
		}, 5);
		$f->method($this->structureManager, "createEmpty", function(mixed $thisValue, array $args) use ($structureObject, $wrap) : mixed{
			return $wrap(fn() => $structureObject($this->s->raw("x.struct.empty", $this->string($args[0] ?? ""), $this->s->vector($args[1] ?? null), ($args[2] ?? null) === null ? "Memory" : $this->string($args[2]))));
		}, 3);
		$f->method($this->structureManager, "get", function(mixed $thisValue, array $args) use ($structureObject, $wrap) : mixed{
			return $wrap(fn() => $structureObject($this->s->raw("x.struct.info", $this->string($args[0] ?? ""))));
		}, 1);
		$f->method($this->structureManager, "delete", function(mixed $thisValue, array $args) use ($structureRef) : mixed{
			return $this->s->raw("x.struct.delete", $structureRef($args[0] ?? ""));
		}, 1);
		$f->method($this->structureManager, "place", function(mixed $thisValue, array $args) use ($structureRef, $wrap) : mixed{
			return $wrap(function() use ($args, $structureRef) : mixed{
				$this->s->raw("x.struct.place", $structureRef($args[0] ?? ""), $this->s->fromJs($args[1] ?? null), $this->s->vector($args[2] ?? null), $this->options($args[3] ?? null));
				return null;
			});
		}, 4);
		$f->method($this->structureManager, "getWorldStructureIds", function(mixed $thisValue, array $args) : mixed{
			return $this->s->api("x.struct.ids");
		});
		$f->method($this->structureManager, "placeJigsawStructure", function(mixed $thisValue, array $args) use ($wrap) : mixed{
			return $wrap(fn() => $this->s->api("x.jigsaw.structure", $this->string($args[0] ?? ""), $this->s->fromJs($args[1] ?? null), $this->s->vector($args[2] ?? null), $this->options($args[3] ?? null)));
		}, 4);
		$f->method($this->structureManager, "placeJigsaw", function(mixed $thisValue, array $args) use ($wrap) : mixed{
			return $wrap(fn() => $this->s->api("x.jigsaw.pool", $this->string($args[0] ?? ""), $this->string($args[1] ?? ""), $this->s->fromJs($args[2] ?? null), $this->s->vector($args[3] ?? null), $this->options($args[4] ?? null)));
		}, 5);
		$this->s->export("StructureManager", $this->structureManager);
		$world->props["structureManager"] = $f->instance($this->structureManager, ["kind" => "structures"]);

		$this->tickingAreaManager = $f->define("TickingAreaManager");
		$areaRef = function(mixed $value) : string{
			if($value instanceof JsObject && !$value instanceof JsArray){
				$identifier = $this->js->get($value, "identifier");
				if(is_string($identifier)){
					return $identifier;
				}
			}
			return $this->string($value);
		};
		$f->method($this->tickingAreaManager, "createTickingArea", function(mixed $thisValue, array $args) use ($js) : mixed{
			$promise = $js->newPromise();
			$options = $args[1] ?? null;
			try{
				if(!$options instanceof JsObject){
					throw new ScriptException("Expected ticking area options");
				}
				$area = $this->s->api("x.ticking.create", $this->string($args[0] ?? ""), $this->s->fromJs($js->get($options, "dimension")), $this->s->vector($js->get($options, "from")), $this->s->vector($js->get($options, "to")));
				$js->resolvePromise($promise, $area);
			}catch(ScriptException $e){
				$js->rejectPromise($promise, $js->makeError("Error", $e->getMessage()));
			}
			return $promise;
		}, 2);
		$f->method($this->tickingAreaManager, "removeTickingArea", function(mixed $thisValue, array $args) use ($areaRef) : mixed{
			$this->s->raw("x.ticking.remove", $areaRef($args[0] ?? ""));
			return null;
		}, 1);
		$f->method($this->tickingAreaManager, "removeAllTickingAreas", function(mixed $thisValue, array $args) : mixed{
			foreach($this->s->raw("x.ticking.ids") as $id){
				$this->s->raw("x.ticking.remove", $id);
			}
			return null;
		});
		$f->method($this->tickingAreaManager, "getTickingArea", function(mixed $thisValue, array $args) : mixed{
			return $this->s->api("x.ticking.get", $this->string($args[0] ?? ""));
		}, 1);
		$f->method($this->tickingAreaManager, "hasTickingArea", function(mixed $thisValue, array $args) : mixed{
			return $this->s->raw("x.ticking.get", $this->string($args[0] ?? "")) !== null;
		}, 1);
		$f->method($this->tickingAreaManager, "getAllTickingAreas", function(mixed $thisValue, array $args) use ($js) : mixed{
			$result = [];
			foreach($this->s->raw("x.ticking.ids") as $id){
				$result[] = $this->s->api("x.ticking.get", $id);
			}
			return $js->newArray($result);
		});
		$f->getter($this->tickingAreaManager, "maxChunkCount", function(mixed $thisValue) : mixed{
			return 1024;
		});
		$f->getter($this->tickingAreaManager, "chunkCount", function(mixed $thisValue) : mixed{
			$count = 0;
			foreach($this->s->raw("x.ticking.ids") as $id){
				$count += (int) ($this->s->raw("x.ticking.get", $id)["chunkCount"] ?? 0);
			}
			return $count;
		});
		$this->s->export("TickingAreaManager", $this->tickingAreaManager);
		$world->props["tickingAreaManager"] = $f->instance($this->tickingAreaManager, ["kind" => "ticking"]);

		$this->textPrimitive = $f->define("TextPrimitive", null, function(array $args, JsObject $newTarget) use ($js) : JsObject{
			$location = $args[0] ?? null;
			if(!$location instanceof JsObject){
				$js->throwError("TypeError", "Expected a location");
			}
			$shape = $this->f->instance($this->textPrimitive, [
				"kind" => "shape",
				"id" => $this->nextShape++,
				"added" => false,
				"data" => $this->shapeLocation($location) + ["text" => $this->s->text($args[1] ?? "")]
			]);
			$shape->proto = $js->prototypeFor($newTarget, $this->textPrimitive->prototype);
			return $shape;
		}, 2);
		$syncShape = function(JsObject $shape) : void{
			if($shape->host["added"]){
				$this->s->raw("x.shape.set", $shape->host["id"], $shape->host["data"]);
			}
		};
		$property = function(string $name, \Closure $read, \Closure $write) use ($syncShape) : void{
			$this->f->getter($this->textPrimitive, $name, function(mixed $thisValue) use ($read) : mixed{
				return $read($this->f->host($thisValue, "shape")["data"]);
			}, function(mixed $thisValue, mixed $value) use ($write, $syncShape) : void{
				$this->f->host($thisValue, "shape");
				$thisValue->host["data"] = $write($thisValue->host["data"], $value);
				$syncShape($thisValue);
			});
		};
		$property("text", fn(array $data) => $data["text"] ?? "", function(array $data, mixed $value) : array{
			$data["text"] = $this->s->text($value);
			return $data;
		});
		foreach(["scale", "timeLeft", "maximumRenderDistance"] as $name){
			$property($name, fn(array $data) => $data[$name] ?? ($name === "scale" ? 1 : null), function(array $data, mixed $value) use ($name) : array{
				$data[$name] = $value === null || $value instanceof JsNull ? null : $this->js->toNumber($value);
				return $data;
			});
		}
		foreach(["useRotation", "depthTest", "showBackface", "showTextBackface"] as $name){
			$property($name, fn(array $data) => $data[$name] ?? ($name !== "useRotation"), function(array $data, mixed $value) use ($name) : array{
				$data[$name] = $this->js->toBoolean($value);
				return $data;
			});
		}
		foreach(["color", "backgroundColorOverride", "rotation"] as $name){
			$property($name, fn(array $data) => $this->s->toJs($data[$name] ?? null), function(array $data, mixed $value) use ($name) : array{
				$data[$name] = $value instanceof JsObject ? $this->s->fromJs($value) : null;
				return $data;
			});
		}
		$property("location", fn(array $data) => $this->s->toJs($data["location"]), function(array $data, mixed $value) : array{
			return $this->shapeLocation($value) + $data;
		});
		$property("dimension", fn(array $data) => $this->s->toJs($data["dimension"]), function(array $data, mixed $value) : array{
			$data["dimension"] = $this->s->fromJs($value);
			return $data;
		});
		$property("visibleTo", fn(array $data) => $this->s->toJs($data["visibleTo"] ?? []), function(array $data, mixed $value) : array{
			$data["visibleTo"] = $value instanceof JsArray && count($value->items) > 0 ? $this->s->fromJs($value) : null;
			return $data;
		});
		$f->getter($this->textPrimitive, "hasDuration", function(mixed $thisValue) : mixed{
			return isset($this->f->host($thisValue, "shape")["data"]["timeLeft"]);
		});
		$f->getter($this->textPrimitive, "attachedTo", function(mixed $thisValue) : mixed{
			return $this->s->toJs($this->f->host($thisValue, "shape")["data"]["attachedTo"] ?? null);
		});
		$f->method($this->textPrimitive, "attachToEntity", function(mixed $thisValue, array $args) use ($syncShape) : mixed{
			$this->f->host($thisValue, "shape");
			$thisValue->host["data"]["attachedTo"] = $this->s->fromJs($args[0] ?? null);
			$syncShape($thisValue);
			return null;
		}, 1);
		$f->method($this->textPrimitive, "detach", function(mixed $thisValue, array $args) use ($syncShape) : mixed{
			$this->f->host($thisValue, "shape");
			unset($thisValue->host["data"]["attachedTo"]);
			$syncShape($thisValue);
			return null;
		});
		$f->method($this->textPrimitive, "remove", function(mixed $thisValue, array $args) : mixed{
			$this->f->host($thisValue, "shape");
			if($thisValue->host["added"]){
				$thisValue->host["added"] = false;
				$this->s->raw("x.shape.remove", $thisValue->host["id"]);
			}
			return null;
		});
		$this->s->export("TextPrimitive", $this->textPrimitive);

		$this->shapesManager = $f->define("PrimitiveShapesManager");
		$f->method($this->shapesManager, "addText", function(mixed $thisValue, array $args) use ($js) : mixed{
			$shape = $args[0] ?? null;
			if(!$shape instanceof JsObject || ($shape->host["kind"] ?? null) !== "shape"){
				$js->throwError("TypeError", "Expected a TextPrimitive");
			}
			$shape->host["added"] = true;
			$this->s->shapes[$shape->host["id"]] = $shape;
			$this->s->raw("x.shape.set", $shape->host["id"], $shape->host["data"]);
			return null;
		}, 1);
		$f->method($this->shapesManager, "removeText", function(mixed $thisValue, array $args) : mixed{
			$shape = $args[0] ?? null;
			if($shape instanceof JsObject && ($shape->host["kind"] ?? null) === "shape" && $shape->host["added"]){
				$shape->host["added"] = false;
				unset($this->s->shapes[$shape->host["id"]]);
				$this->s->raw("x.shape.remove", $shape->host["id"]);
			}
			return null;
		}, 1);
		$f->method($this->shapesManager, "removeAll", function(mixed $thisValue, array $args) : mixed{
			foreach($this->s->shapes as $id => $shape){
				$shape->host["added"] = false;
				$this->s->raw("x.shape.remove", $id);
			}
			$this->s->shapes = [];
			return null;
		});
		$this->s->export("PrimitiveShapesManager", $this->shapesManager);
		$world->props["primitiveShapesManager"] = $f->instance($this->shapesManager, ["kind" => "shapes"]);

		foreach(["structureManager", "tickingAreaManager", "primitiveShapesManager"] as $key){
			$world->locked[$key] = true;
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function shapeLocation(mixed $location) : array{
		$vector = $this->s->vector($location);
		$result = ["location" => $vector];
		if($location instanceof JsObject){
			$dimension = $this->js->get($location, "dimension");
			if($dimension instanceof JsObject){
				$result["dimension"] = $this->s->fromJs($dimension);
			}
		}
		return $result;
	}

	private function defineDimensions() : void{
		$f = $this->f;
		$js = $this->js;
		$dimension = $this->s->dimension;
		$dimensionRef = function(mixed $thisValue) : array{
			return ["\$d" => $this->f->host($thisValue, "dimension")["id"]];
		};
		$wrap = function(\Closure $call) use ($js) : mixed{
			try{
				return $call();
			}catch(ScriptException $e){
				$js->throwError("LocationInUnloadedChunkError", $e->getMessage());
			}
		};

		$this->biomeType = $f->define("BiomeType");
		$f->getter($this->biomeType, "id", function(mixed $thisValue) : mixed{
			return $this->f->host($thisValue, "biome")["id"];
		});
		$this->s->export("BiomeType", $this->biomeType);
		$biomeTypes = $f->define("BiomeTypes");
		$f->staticMethod($biomeTypes, "get", function(mixed $thisValue, array $args) : mixed{
			return $this->f->instance($this->biomeType, ["kind" => "biome", "id" => ServerModule::namespaced($this->string($args[0] ?? ""))]);
		}, 1);
		$this->s->export("BiomeTypes", $biomeTypes);

		$f->method($dimension, "getBiome", function(mixed $thisValue, array $args) use ($dimensionRef, $wrap) : mixed{
			$id = $wrap(fn() => $this->s->raw("x.biome", $dimensionRef($thisValue), $this->s->vector($args[0] ?? null)));
			return $this->f->instance($this->biomeType, ["kind" => "biome", "id" => $id]);
		}, 1);
		$f->method($dimension, "getLightLevel", function(mixed $thisValue, array $args) use ($dimensionRef, $wrap) : mixed{
			return $wrap(fn() => $this->s->raw("x.light", $dimensionRef($thisValue), $this->s->vector($args[0] ?? null)));
		}, 1);
		$f->method($dimension, "getSkyLightLevel", function(mixed $thisValue, array $args) use ($dimensionRef, $wrap) : mixed{
			return $wrap(fn() => $this->s->raw("x.skyLight", $dimensionRef($thisValue), $this->s->vector($args[0] ?? null)));
		}, 1);
		$f->method($dimension, "isChunkLoaded", function(mixed $thisValue, array $args) use ($dimensionRef) : mixed{
			return $this->s->raw("x.chunkLoaded", $dimensionRef($thisValue), $this->s->vector($args[0] ?? null));
		}, 1);
		$f->method($dimension, "placeFeature", function(mixed $thisValue, array $args) use ($dimensionRef, $js) : mixed{
			$name = $this->string($args[0] ?? "");
			$shouldThrow = $this->js->toBoolean($args[2] ?? false);
			try{
				$placed = $this->s->raw("x.feature", $dimensionRef($thisValue), $name, $this->s->vector($args[1] ?? null));
			}catch(ScriptException $e){
				if($shouldThrow){
					$js->throwError("PlaceFeatureError", $e->getMessage());
				}
				return false;
			}
			if(!$placed && $shouldThrow){
				$js->throwError("PlaceFeatureError", "The feature " . $name . " could not be placed");
			}
			return $placed;
		}, 3);
		$f->method($dimension, "spawnParticle", function(mixed $thisValue, array $args) use ($dimensionRef) : mixed{
			$this->s->raw("x.particle", $dimensionRef($thisValue), $this->string($args[0] ?? ""), $this->s->vector($args[1] ?? null), $this->molangVariables($args[2] ?? null));
			return null;
		}, 3);
		$f->method($dimension, "getBlockFromRay", function(mixed $thisValue, array $args) use ($dimensionRef) : mixed{
			return $this->s->api("x.rayBlock", $dimensionRef($thisValue), $this->s->vector($args[0] ?? null), $this->s->vector($args[1] ?? null), $this->options($args[2] ?? null));
		}, 3);
		$f->method($dimension, "getEntitiesFromRay", function(mixed $thisValue, array $args) use ($dimensionRef) : mixed{
			return $this->s->api("x.rayEntities", $dimensionRef($thisValue), $this->s->vector($args[0] ?? null), $this->s->vector($args[1] ?? null), $this->options($args[2] ?? null));
		}, 3);

		$block = $this->s->block;
		foreach(["getLightLevel" => "x.light", "getSkyLightLevel" => "x.skyLight"] as $name => $method){
			$f->method($block, $name, function(mixed $thisValue, array $args) use ($method, $wrap) : mixed{
				$host = $this->f->host($thisValue, "block");
				return $wrap(fn() => $this->s->raw($method, ["\$d" => $host["d"]], ["x" => $host["x"], "y" => $host["y"], "z" => $host["z"]]));
			});
		}

		$this->dimensionType = $f->define("DimensionType");
		$f->getter($this->dimensionType, "typeId", function(mixed $thisValue) : mixed{
			return $this->f->host($thisValue, "dimensiontype")["id"];
		});
		$this->s->export("DimensionType", $this->dimensionType);
		$dimensionTypes = $f->define("DimensionTypes");
		$f->staticMethod($dimensionTypes, "get", function(mixed $thisValue, array $args) : mixed{
			$id = ServerModule::namespaced($this->string($args[0] ?? ""));
			foreach($this->s->raw("x.dimension.types") as $type){
				if($type === $id){
					return $this->f->instance($this->dimensionType, ["kind" => "dimensiontype", "id" => $type]);
				}
			}
			return null;
		}, 1);
		$f->staticMethod($dimensionTypes, "getAll", function(mixed $thisValue, array $args) use ($js) : mixed{
			$result = [];
			foreach($this->s->raw("x.dimension.types") as $type){
				$result[] = $this->f->instance($this->dimensionType, ["kind" => "dimensiontype", "id" => $type]);
			}
			return $js->newArray($result);
		});
		$this->s->export("DimensionTypes", $dimensionTypes);

		$this->dimensionRegistry = $f->define("CustomDimensionRegistry");
		$f->method($this->dimensionRegistry, "registerCustomDimension", function(mixed $thisValue, array $args) use ($js) : mixed{
			$typeId = $this->string($args[0] ?? "");
			if(!\str_contains($typeId, ":")){
				$js->throwError("Error", "Custom dimension ids must have a namespace");
			}
			try{
				$this->s->raw("x.dimension.register", $typeId);
			}catch(ScriptException $e){
				$js->throwError("Error", $e->getMessage());
			}
			return null;
		}, 1);
		$this->s->export("CustomDimensionRegistry", $this->dimensionRegistry);
		$this->s->dimensionRegistry = $f->instance($this->dimensionRegistry, ["kind" => "registry"]);
	}

	private function defineVolumes() : void{
		$f = $this->f;
		$js = $this->js;
		$this->blockVolume = $f->define("BlockVolume", null, function(array $args, JsObject $newTarget) use ($js) : JsObject{
			$volume = $this->f->instance($this->blockVolume, ["kind" => "volume", "from" => $this->intVector($args[0] ?? null), "to" => $this->intVector($args[1] ?? null)]);
			$volume->proto = $js->prototypeFor($newTarget, $this->blockVolume->prototype);
			return $volume;
		}, 2);
		$volume = $this->blockVolume;
		foreach(["from", "to"] as $name){
			$f->getter($volume, $name, function(mixed $thisValue) use ($name) : mixed{
				return $this->s->toJs($this->f->host($thisValue, "volume")[$name]);
			}, function(mixed $thisValue, mixed $value) use ($name) : void{
				$this->f->host($thisValue, "volume");
				$thisValue->host[$name] = $this->intVector($value);
			});
		}
		$bounds = function(mixed $thisValue) : array{
			$host = $this->f->host($thisValue, "volume");
			return [
				["x" => min($host["from"]["x"], $host["to"]["x"]), "y" => min($host["from"]["y"], $host["to"]["y"]), "z" => min($host["from"]["z"], $host["to"]["z"])],
				["x" => max($host["from"]["x"], $host["to"]["x"]), "y" => max($host["from"]["y"], $host["to"]["y"]), "z" => max($host["from"]["z"], $host["to"]["z"])]
			];
		};
		$f->method($volume, "getMin", function(mixed $thisValue, array $args) use ($bounds) : mixed{
			return $this->s->toJs($bounds($thisValue)[0]);
		});
		$f->method($volume, "getMax", function(mixed $thisValue, array $args) use ($bounds) : mixed{
			return $this->s->toJs($bounds($thisValue)[1]);
		});
		$f->method($volume, "getSpan", function(mixed $thisValue, array $args) use ($bounds) : mixed{
			[$min, $max] = $bounds($thisValue);
			return $this->s->toJs(["x" => $max["x"] - $min["x"] + 1, "y" => $max["y"] - $min["y"] + 1, "z" => $max["z"] - $min["z"] + 1]);
		});
		$f->method($volume, "getCapacity", function(mixed $thisValue, array $args) use ($bounds) : mixed{
			[$min, $max] = $bounds($thisValue);
			return ($max["x"] - $min["x"] + 1) * ($max["y"] - $min["y"] + 1) * ($max["z"] - $min["z"] + 1);
		});
		$f->method($volume, "isInside", function(mixed $thisValue, array $args) use ($bounds) : mixed{
			[$min, $max] = $bounds($thisValue);
			$point = $this->s->vector($args[0] ?? null);
			$x = (int) floor($point["x"]);
			$y = (int) floor($point["y"]);
			$z = (int) floor($point["z"]);
			return $x >= $min["x"] && $x <= $max["x"] && $y >= $min["y"] && $y <= $max["y"] && $z >= $min["z"] && $z <= $max["z"];
		}, 1);
		$f->method($volume, "translate", function(mixed $thisValue, array $args) : mixed{
			$host = $this->f->host($thisValue, "volume");
			$delta = $this->intVector($args[0] ?? null);
			foreach(["from", "to"] as $key){
				foreach(["x", "y", "z"] as $axis){
					$host[$key][$axis] += $delta[$axis];
				}
			}
			$thisValue->host = $host;
			return null;
		}, 1);
		$f->method($volume, "doesLocationTouchFaces", function(mixed $thisValue, array $args) use ($bounds) : mixed{
			[$min, $max] = $bounds($thisValue);
			$point = $this->intVector($args[0] ?? null);
			$inside = $point["x"] >= $min["x"] && $point["x"] <= $max["x"] && $point["y"] >= $min["y"] && $point["y"] <= $max["y"] && $point["z"] >= $min["z"] && $point["z"] <= $max["z"];
			return $inside && ($point["x"] === $min["x"] || $point["x"] === $max["x"] || $point["y"] === $min["y"] || $point["y"] === $max["y"] || $point["z"] === $min["z"] || $point["z"] === $max["z"]);
		}, 1);
		$other = function(mixed $value) use ($js) : array{
			if(!$value instanceof JsObject || ($value->host["kind"] ?? null) !== "volume"){
				$js->throwError("TypeError", "Expected a BlockVolume");
			}
			$host = $value->host;
			return [
				["x" => min($host["from"]["x"], $host["to"]["x"]), "y" => min($host["from"]["y"], $host["to"]["y"]), "z" => min($host["from"]["z"], $host["to"]["z"])],
				["x" => max($host["from"]["x"], $host["to"]["x"]), "y" => max($host["from"]["y"], $host["to"]["y"]), "z" => max($host["from"]["z"], $host["to"]["z"])]
			];
		};
		$f->method($volume, "intersects", function(mixed $thisValue, array $args) use ($bounds, $other) : mixed{
			[$aMin, $aMax] = $bounds($thisValue);
			[$bMin, $bMax] = $other($args[0] ?? null);
			foreach(["x", "y", "z"] as $axis){
				if($bMax[$axis] < $aMin[$axis] || $bMin[$axis] > $aMax[$axis]){
					return "Disjoint";
				}
			}
			foreach(["x", "y", "z"] as $axis){
				if($bMin[$axis] < $aMin[$axis] || $bMax[$axis] > $aMax[$axis]){
					return "Intersects";
				}
			}
			return "Contains";
		}, 1);
		$f->method($volume, "doesVolumeTouchFaces", function(mixed $thisValue, array $args) use ($bounds, $other) : mixed{
			[$aMin, $aMax] = $bounds($thisValue);
			[$bMin, $bMax] = $other($args[0] ?? null);
			foreach(["x", "y", "z"] as $axis){
				if($bMax[$axis] < $aMin[$axis] || $bMin[$axis] > $aMax[$axis]){
					return false;
				}
			}
			foreach(["x", "y", "z"] as $axis){
				if($bMin[$axis] <= $aMin[$axis] || $bMax[$axis] >= $aMax[$axis]){
					return true;
				}
			}
			return false;
		}, 1);
		$f->method($volume, "getBlockLocationIterator", function(mixed $thisValue, array $args) use ($bounds, $js) : mixed{
			[$min, $max] = $bounds($thisValue);
			$cursor = ["x" => $min["x"], "y" => $min["y"], "z" => $min["z"], "done" => false];
			$iterator = $js->newObject();
			$iterator->className = "BlockLocationIterator";
			$js->defineMethod($iterator, "next", 0, function(mixed $unused, array $none) use (&$cursor, $min, $max, $js) : mixed{
				$result = $js->newObject();
				if($cursor["done"]){
					$result->props["value"] = null;
					$result->props["done"] = true;
					return $result;
				}
				$result->props["value"] = $this->s->toJs(["x" => $cursor["x"], "y" => $cursor["y"], "z" => $cursor["z"]]);
				$result->props["done"] = false;
				$cursor["z"]++;
				if($cursor["z"] > $max["z"]){
					$cursor["z"] = $min["z"];
					$cursor["y"]++;
					if($cursor["y"] > $max["y"]){
						$cursor["y"] = $min["y"];
						$cursor["x"]++;
						if($cursor["x"] > $max["x"]){
							$cursor["done"] = true;
						}
					}
				}
				return $result;
			});
			$js->defineMethod($iterator, "isValid", 0, function(mixed $unused, array $none) : mixed{
				return true;
			});
			$js->defineMethod($iterator, $js->symIterator, 0, function(mixed $self, array $none) : mixed{
				return $self;
			});
			return $iterator;
		});
		$this->s->export("BlockVolume", $volume);
		$this->s->export("BlockVolumeBase", $volume);
		$this->s->export("BlockVolumeIntersection", $f->enum("BlockVolumeIntersection", ["Contains" => "Contains", "Disjoint" => "Disjoint", "Intersects" => "Intersects"]));
	}

	/**
	 * @return array{x: int, y: int, z: int}
	 */
	private function intVector(mixed $value) : array{
		$vector = $this->s->vector($value);
		return ["x" => (int) floor($vector["x"]), "y" => (int) floor($vector["y"]), "z" => (int) floor($vector["z"])];
	}

	private function defineParticles() : void{
		$f = $this->f;
		$js = $this->js;
		$this->molangMap = $f->define("MolangVariableMap", null, function(array $args, JsObject $newTarget) use ($js) : JsObject{
			$map = $this->f->instance($this->molangMap, ["kind" => "molang", "vars" => []]);
			$map->proto = $js->prototypeFor($newTarget, $this->molangMap->prototype);
			return $map;
		});
		$set = function(mixed $thisValue, string $name, mixed $value) : void{
			$this->f->host($thisValue, "molang");
			$thisValue->host["vars"][$name] = $value;
		};
		$f->method($this->molangMap, "setFloat", function(mixed $thisValue, array $args) use ($set) : mixed{
			$set($thisValue, $this->string($args[0] ?? ""), (float) $this->js->toNumber($args[1] ?? 0));
			return null;
		}, 2);
		$color = function(mixed $value, bool $alpha) : array{
			$result = [];
			foreach(["red" => "r", "green" => "g", "blue" => "b"] as $key => $member){
				$result[$member] = $value instanceof JsObject ? (float) $this->js->toNumber($this->js->get($value, $key) ?? 0) : 0.0;
			}
			$result["a"] = $alpha && $value instanceof JsObject ? (float) $this->js->toNumber($this->js->get($value, "alpha") ?? 1) : 1.0;
			return $result;
		};
		$f->method($this->molangMap, "setColorRGB", function(mixed $thisValue, array $args) use ($set, $color) : mixed{
			$set($thisValue, $this->string($args[0] ?? ""), $color($args[1] ?? null, false));
			return null;
		}, 2);
		$f->method($this->molangMap, "setColorRGBA", function(mixed $thisValue, array $args) use ($set, $color) : mixed{
			$set($thisValue, $this->string($args[0] ?? ""), $color($args[1] ?? null, true));
			return null;
		}, 2);
		$f->method($this->molangMap, "setVector3", function(mixed $thisValue, array $args) use ($set) : mixed{
			$set($thisValue, $this->string($args[0] ?? ""), $this->s->vector($args[1] ?? null));
			return null;
		}, 2);
		$f->method($this->molangMap, "setSpeedAndDirection", function(mixed $thisValue, array $args) use ($set) : mixed{
			$direction = $this->s->vector($args[2] ?? null);
			$set($thisValue, $this->string($args[0] ?? ""), [
				"speed" => (float) $this->js->toNumber($args[1] ?? 0),
				"direction_x" => $direction["x"],
				"direction_y" => $direction["y"],
				"direction_z" => $direction["z"]
			]);
			return null;
		}, 3);
		$this->s->export("MolangVariableMap", $this->molangMap);
	}

	/**
	 * @return list<array{name: string, value: mixed}>
	 */
	private function molangVariables(mixed $map) : array{
		if(!$map instanceof JsObject || ($map->host["kind"] ?? null) !== "molang"){
			return [];
		}
		$result = [];
		foreach($map->host["vars"] as $name => $value){
			$result[] = ["name" => (string) $name, "value" => $value];
		}
		return $result;
	}
}
