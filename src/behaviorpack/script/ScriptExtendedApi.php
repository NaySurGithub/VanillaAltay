<?php

declare(strict_types=1);

namespace behaviorpack\script;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\player\PlayerBehavior;
use behaviorpack\entity\player\PlayerBehaviorManager;
use behaviorpack\script\ddui\DduiDataStorePacket;
use pocketmine\network\mcpe\protocol\ClientboundDataDrivenUICloseScreenPacket;
use pocketmine\network\mcpe\protocol\ClientboundDataDrivenUIShowScreenPacket;
use pocketmine\block\Block;
use pocketmine\block\Liquid;
use pocketmine\color\Color;
use pocketmine\entity\Entity;
use pocketmine\entity\object\ItemEntity;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\math\VoxelRayTrace;
use pocketmine\network\mcpe\protocol\AnimateEntityPacket;
use pocketmine\network\mcpe\protocol\CameraInstructionPacket;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\LocatorBarPacket;
use pocketmine\network\mcpe\protocol\PrimitiveShapesPacket;
use pocketmine\network\mcpe\protocol\SetHudPacket;
use pocketmine\network\mcpe\protocol\SpawnParticleEffectPacket;
use pocketmine\network\mcpe\protocol\types\camera\CameraFadeInstruction;
use pocketmine\network\mcpe\protocol\types\camera\CameraFadeInstructionColor;
use pocketmine\network\mcpe\protocol\types\camera\CameraFadeInstructionTime;
use pocketmine\network\mcpe\protocol\types\camera\CameraPreset;
use pocketmine\network\mcpe\protocol\types\camera\CameraSetInstruction;
use pocketmine\network\mcpe\protocol\types\camera\CameraSetInstructionEase;
use pocketmine\network\mcpe\protocol\types\camera\CameraSetInstructionEaseType;
use pocketmine\network\mcpe\protocol\types\camera\CameraSetInstructionRotation;
use pocketmine\network\mcpe\protocol\types\hud\HudElement;
use pocketmine\network\mcpe\protocol\types\hud\HudVisibility;
use pocketmine\network\mcpe\protocol\types\LocatorBarWaypoint;
use pocketmine\network\mcpe\protocol\types\LocatorBarWaypointPayload;
use pocketmine\network\mcpe\protocol\types\PacketShapeData;
use pocketmine\network\mcpe\protocol\types\PrimitiveShapeTextPayload;
use pocketmine\network\mcpe\protocol\types\PrimitiveShapeType;
use pocketmine\network\mcpe\protocol\types\WorldPosition;
use pocketmine\network\mcpe\protocol\UpdateClientInputLocksPacket;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\ChunkLoader;
use pocketmine\world\ChunkTicker;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\World;
use pocketmine\world\WorldCreationOptions;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use function array_keys;
use function count;
use function in_array;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function json_encode;
use function max;
use function md5;
use function min;
use function preg_replace;
use function spl_object_id;
use function str_replace;
use function strtolower;
use function usort;

/**
 * Serves the requests added on top of ScriptApi: entity properties,
 * animations, cameras, input, HUD, raycasts, riding, structures, features,
 * ticking areas, custom dimensions, waypoints, primitive shapes, particles,
 * enchantments and item cooldowns.
 */
final class ScriptExtendedApi{

	public const CAMERA_PRESETS = [
		"minecraft:first_person",
		"minecraft:free",
		"minecraft:third_person",
		"minecraft:third_person_front",
		"minecraft:follow_orbit",
		"minecraft:fixed_boom",
		"minecraft:control_scheme_camera"
	];

	public const DDUI_PROPERTY = "custom_form_data";

	private Server $server;

	/** @var array<string, array{world: World, loader: ChunkLoader, ticker: ChunkTicker, chunks: list<array{int, int}>, from: array{x: float, y: float, z: float}, to: array{x: float, y: float, z: float}}> */
	private array $tickingAreas = [];

	/** @var array<int, array<string, mixed>> */
	private array $shapes = [];

	/** @var array<string, int>|null */
	private ?array $biomeNames = null;

	/** @var array<int, string>|null */
	private ?array $enchantmentNames = null;

	public function __construct(
		private ScriptValues $values,
		private ScriptStructures $structures,
		private ScriptFeatures $features,
		private ScriptJigsaw $jigsaw,
		private ScriptPlayerState $state
	){
		$this->server = $values->getServer();
	}

	/**
	 * @param list<mixed> $a
	 */
	public function handle(string $method, array $a) : mixed{
		return match($method){
			"x.prop.get" => $this->getProperty($this->values->entity($a[0] ?? null), $this->string($a[1] ?? "")),
			"x.prop.set" => $this->setProperty($this->values->entity($a[0] ?? null), $this->string($a[1] ?? ""), $a[2] ?? null),
			"x.prop.reset" => $this->resetProperty($this->values->entity($a[0] ?? null), $this->string($a[1] ?? "")),
			"x.anim" => $this->playAnimation($this->values->entity($a[0] ?? null), $this->string($a[1] ?? ""), is_array($a[2] ?? null) ? $a[2] : []),
			"x.aabb" => $this->aabb($this->values->entity($a[0] ?? null)),
			"x.viewEntities" => $this->viewEntities($this->values->entity($a[0] ?? null), is_array($a[1] ?? null) ? $a[1] : []),
			"x.viewBlock" => $this->viewBlock($this->values->entity($a[0] ?? null), is_array($a[1] ?? null) ? $a[1] : []),
			"x.rayBlock" => $this->rayBlock($this->values->world($a[0] ?? null), $this->values->vector($a[1] ?? null), $this->values->vector($a[2] ?? null), is_array($a[3] ?? null) ? $a[3] : []),
			"x.rayEntities" => $this->rayEntities($this->values->world($a[0] ?? null), $this->values->vector($a[1] ?? null), $this->values->vector($a[2] ?? null), is_array($a[3] ?? null) ? $a[3] : [], null),
			"x.riders" => $this->riders($this->values->entity($a[0] ?? null)),
			"x.addRider" => $this->values->entity($a[0] ?? null)->addPassenger($this->values->entity($a[1] ?? null)),
			"x.ejectRider" => $this->values->entity($a[0] ?? null)->removePassenger($this->values->entity($a[1] ?? null)),
			"x.ejectRiders" => $this->ejectRiders($this->values->entity($a[0] ?? null)),
			"x.vehicle" => $this->vehicle($this->values->entity($a[0] ?? null)),
			"x.itemEntity" => $this->itemEntity($this->values->entity($a[0] ?? null)),
			"x.camera.set" => $this->setCamera($this->values->player($a[0] ?? null), $this->string($a[1] ?? ""), is_array($a[2] ?? null) ? $a[2] : []),
			"x.camera.clear" => $this->send($this->values->player($a[0] ?? null), CameraInstructionPacket::create(null, true, null, null, null, null, null, null, null)),
			"x.camera.fade" => $this->fadeCamera($this->values->player($a[0] ?? null), is_array($a[1] ?? null) ? $a[1] : []),
			"x.input.set" => $this->setInputPermission($this->values->player($a[0] ?? null), (int) $this->number($a[1] ?? 0), ($a[2] ?? true) === true),
			"x.input.get" => ($this->state->getLocks($this->values->player($a[0] ?? null)->getId()) & (1 << (int) $this->number($a[1] ?? 0))) === 0,
			"x.input.button" => $this->buttonState($this->values->player($a[0] ?? null), $this->string($a[1] ?? "")),
			"x.input.move" => $this->movementVector($this->values->player($a[0] ?? null)),
			"x.input.mode" => $this->state->getInput($this->values->player($a[0] ?? null)->getId())["mode"],
			"x.hud.set" => $this->setHud($this->values->player($a[0] ?? null), ($a[1] ?? 1) === 0, is_array($a[2] ?? null) ? $a[2] : null),
			"x.hud.except" => $this->hideAllExcept($this->values->player($a[0] ?? null), is_array($a[1] ?? null) ? $a[1] : []),
			"x.hud.hidden" => isset($this->state->getHiddenHud($this->values->player($a[0] ?? null)->getId())[(int) $this->number($a[1] ?? 0)]),
			"x.waypoint.add" => $this->addWaypoint($this->values->player($a[0] ?? null), $this->string($a[1] ?? ""), is_array($a[2] ?? null) ? $a[2] : []),
			"x.waypoint.remove" => $this->removeWaypoint($this->values->player($a[0] ?? null), $this->string($a[1] ?? "")),
			"x.waypoint.has" => isset($this->state->getWaypoints($this->values->player($a[0] ?? null)->getId())[$this->string($a[1] ?? "")]),
			"x.waypoint.list" => array_keys($this->state->getWaypoints($this->values->player($a[0] ?? null)->getId())),
			"x.waypoint.clear" => $this->clearWaypoints($this->values->player($a[0] ?? null)),
			"x.waypoint.update" => $this->updateWaypoint($this->string($a[0] ?? ""), is_array($a[1] ?? null) ? $a[1] : []),
			"x.particle" => $this->spawnParticle($this->values->world($a[0] ?? null), $this->string($a[1] ?? ""), $this->values->vector($a[2] ?? null), is_array($a[3] ?? null) ? $a[3] : [], isset($a[4]) ? $this->values->player($a[4]) : null),
			"x.biome" => $this->biome($this->values->world($a[0] ?? null), $this->values->vector($a[1] ?? null)),
			"x.light" => $this->light($this->values->world($a[0] ?? null), $this->values->vector($a[1] ?? null), false),
			"x.skyLight" => $this->light($this->values->world($a[0] ?? null), $this->values->vector($a[1] ?? null), true),
			"x.chunkLoaded" => $this->chunkLoaded($this->values->world($a[0] ?? null), $this->values->vector($a[1] ?? null)),
			"x.feature" => $this->features->place($this->string($a[1] ?? ""), $this->values->world($a[0] ?? null), $this->values->vector($a[2] ?? null)),
			"x.feature.has" => $this->features->has($this->string($a[0] ?? "")),
			"x.struct.create" => $this->createStructure($a),
			"x.struct.empty" => $this->createEmptyStructure($a),
			"x.struct.info" => $this->structureInfo($this->string($a[0] ?? "")),
			"x.struct.delete" => $this->structures->delete($this->string($a[0] ?? "")),
			"x.struct.place" => $this->placeStructure($this->string($a[0] ?? ""), $this->values->world($a[1] ?? null), $this->values->vector($a[2] ?? null), is_array($a[3] ?? null) ? $a[3] : []),
			"x.struct.getBlock" => $this->structureBlock($this->string($a[0] ?? ""), $this->values->vector($a[1] ?? null)),
			"x.struct.setBlock" => $this->setStructureBlock($this->string($a[0] ?? ""), $this->values->vector($a[1] ?? null), $a[2] ?? null),
			"x.struct.saveAs" => $this->structures->saveAs($this->string($a[0] ?? ""), $this->string($a[1] ?? ""), $this->string($a[2] ?? ScriptStructures::MODE_WORLD)),
			"x.struct.mode" => $this->structures->setMode($this->string($a[0] ?? ""), $this->string($a[1] ?? ScriptStructures::MODE_WORLD)),
			"x.struct.ids" => $this->structures->worldIds(),
			"x.jigsaw.structure" => $this->placeJigsawStructure($this->string($a[0] ?? ""), $this->values->world($a[1] ?? null), $this->values->vector($a[2] ?? null), is_array($a[3] ?? null) ? $a[3] : []),
			"x.jigsaw.pool" => $this->placeJigsawPool($this->string($a[0] ?? ""), $this->string($a[1] ?? ""), $this->values->world($a[2] ?? null), $this->values->vector($a[3] ?? null), is_array($a[4] ?? null) ? $a[4] : []),
			"x.ticking.create" => $this->createTickingArea($this->string($a[0] ?? ""), $this->values->world($a[1] ?? null), $this->values->vector($a[2] ?? null), $this->values->vector($a[3] ?? null)),
			"x.ticking.remove" => $this->removeTickingArea($this->string($a[0] ?? "")),
			"x.ticking.get" => $this->tickingAreaInfo($this->string($a[0] ?? "")),
			"x.ticking.ids" => array_keys($this->tickingAreas),
			"x.dimension.register" => $this->registerDimension($this->string($a[0] ?? "")),
			"x.dimension.types" => $this->dimensionTypes(),
			"x.shape.set" => $this->setShape((int) $this->number($a[0] ?? 0), is_array($a[1] ?? null) ? $a[1] : []),
			"x.shape.remove" => $this->removeShape((int) $this->number($a[0] ?? 0)),
			"x.ench.get" => $this->getEnchantments($a[0] ?? null),
			"x.ench.set" => $this->setEnchantments($a[0] ?? null, is_array($a[1] ?? null) ? $a[1] : []),
			"x.ench.type" => $this->enchantmentType($this->string($a[0] ?? "")),
			"x.ench.types" => $this->enchantmentTypes(),
			"x.cooldown.info" => $this->cooldownInfo($a[0] ?? null),
			"x.cooldown.start" => $this->startCooldown($this->values->player($a[0] ?? null), $a[1] ?? null),
			"x.cooldown.remaining" => $this->cooldownRemaining($this->values->player($a[0] ?? null), $a[1] ?? null),
			"x.form.close" => $this->closeForms($this->values->player($a[0] ?? null)),
			"x.types.entity" => $this->entityTypeIds(),
			"x.families" => BehaviorEntity::familiesOf($this->values->entity($a[0] ?? null)),
			"x.types.item" => $this->aliasTypeIds(\pocketmine\item\StringToItemParser::getInstance()->getKnownAliases(), false),
			"x.types.block" => $this->aliasTypeIds(\pocketmine\item\StringToItemParser::getInstance()->getKnownAliases(), true),
			"x.ddui.show" => $this->showScreen($this->values->player($a[0] ?? null), (int) $this->number($a[1] ?? 0), is_array($a[2] ?? null) ? $a[2] : []),
			"x.ddui.update" => $this->send($this->values->player($a[0] ?? null), DduiDataStorePacket::entries(\array_map(fn(array $update) : array => ["update", self::DDUI_PROPERTY, $update[0], $update[1]], is_array($a[1] ?? null) ? $a[1] : []))),
			"x.ddui.close" => $this->send($this->values->player($a[0] ?? null), ClientboundDataDrivenUICloseScreenPacket::create((int) $this->number($a[1] ?? 0))),
			"x.proj.get" => $this->projectileInfo($this->projectile($a[0] ?? null)),
			"x.proj.set" => $this->setProjectile($this->projectile($a[0] ?? null), $this->string($a[1] ?? ""), $a[2] ?? null),
			"x.proj.shoot" => $this->shootProjectile($this->projectile($a[0] ?? null), $this->values->vector($a[1] ?? null), is_numeric($a[2] ?? null) ? (float) $a[2] : 0.0),
			default => throw new ScriptException("Unknown request " . $method)
		};
	}

	private function string(mixed $value) : string{
		if(is_string($value)){
			return $value;
		}
		if(is_numeric($value)){
			return (string) $value;
		}
		throw new ScriptException("Expected a string");
	}

	private function number(mixed $value) : float{
		if(!is_numeric($value)){
			throw new ScriptException("Expected a number");
		}
		return (float) $value;
	}

	private function send(Player $player, ClientboundPacket $packet) : bool{
		$player->getNetworkSession()->sendDataPacket($packet);
		return true;
	}

	private function getProperty(Entity $entity, string $name) : bool|int|float|string|null{
		if($entity instanceof Player){
			return PlayerBehaviorManager::get($entity)?->getPropertyValue($name);
		}
		return $entity instanceof BehaviorEntity ? $entity->getPropertyValue($name) : null;
	}

	private function setProperty(Entity $entity, string $name, mixed $value) : bool{
		if($entity instanceof Player){
			$entity = PlayerBehaviorManager::get($entity);
		}
		if(!$entity instanceof BehaviorEntity && !$entity instanceof PlayerBehavior){
			throw new ScriptException("Property " . $name . " is not defined for this entity");
		}
		try{
			$entity->setPropertyValue($name, $value);
		}catch(\InvalidArgumentException $e){
			throw new ScriptException($e->getMessage());
		}
		return true;
	}

	private function resetProperty(Entity $entity, string $name) : bool{
		if($entity instanceof Player){
			$entity = PlayerBehaviorManager::get($entity);
		}
		if(!$entity instanceof BehaviorEntity && !$entity instanceof PlayerBehavior){
			throw new ScriptException("Property " . $name . " is not defined for this entity");
		}
		try{
			$entity->resetPropertyValue($name);
		}catch(\InvalidArgumentException $e){
			throw new ScriptException($e->getMessage());
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $options
	 */
	private function playAnimation(Entity $entity, string $animation, array $options) : bool{
		$packet = AnimateEntityPacket::create(
			$animation,
			is_string($options["nextState"] ?? null) ? $options["nextState"] : "",
			is_string($options["stopExpression"] ?? null) ? $options["stopExpression"] : "query.any_animation_finished",
			0,
			is_string($options["controller"] ?? null) ? $options["controller"] : "",
			is_numeric($options["blendOutTime"] ?? null) ? (float) $options["blendOutTime"] : 0.0,
			[$entity->getId()]
		);
		$targets = $entity->getViewers();
		if($entity instanceof Player){
			$targets[spl_object_id($entity)] = $entity;
		}
		$names = is_array($options["players"] ?? null) ? $options["players"] : null;
		foreach($targets as $player){
			if($names !== null && !in_array($player->getName(), $names, true)){
				continue;
			}
			$player->getNetworkSession()->sendDataPacket($packet);
		}
		return true;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function aabb(Entity $entity) : array{
		$box = $entity->getBoundingBox();
		return [
			"center" => ["x" => ($box->minX + $box->maxX) / 2, "y" => ($box->minY + $box->maxY) / 2, "z" => ($box->minZ + $box->maxZ) / 2],
			"extent" => ["x" => ($box->maxX - $box->minX) / 2, "y" => ($box->maxY - $box->minY) / 2, "z" => ($box->maxZ - $box->minZ) / 2]
		];
	}

	/**
	 * @param array<string, mixed> $options
	 * @return list<array<string, mixed>>
	 */
	private function viewEntities(Entity $entity, array $options) : array{
		return $this->rayEntities($entity->getWorld(), $entity->getEyePos(), $entity->getDirectionVector(), $options, $entity);
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>|null
	 */
	private function viewBlock(Entity $entity, array $options) : ?array{
		return $this->rayBlock($entity->getWorld(), $entity->getEyePos(), $entity->getDirectionVector(), $options);
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array{block: Block, vector: Vector3, face: int, distance: float}|null
	 */
	private function traceBlock(World $world, Vector3 $start, Vector3 $direction, array $options) : ?array{
		$direction = $direction->lengthSquared() > 0 ? $direction->normalize() : new Vector3(0, 0, 1);
		$maxDistance = is_numeric($options["maxDistance"] ?? null) ? (float) $options["maxDistance"] : 64.0;
		$includeLiquid = ($options["includeLiquidBlocks"] ?? false) === true;
		$includePassable = ($options["includePassableBlocks"] ?? false) === true;
		$end = $start->addVector($direction->multiply($maxDistance));
		foreach(VoxelRayTrace::inDirection($start, $direction, $maxDistance) as $position){
			$x = (int) $position->x;
			$y = (int) $position->y;
			$z = (int) $position->z;
			if(!$world->isInWorld($x, $y, $z) || !$world->isChunkLoaded($x >> 4, $z >> 4)){
				continue;
			}
			$block = $world->getBlockAt($x, $y, $z);
			if($block->getTypeId() === \pocketmine\block\BlockTypeIds::AIR){
				continue;
			}
			$liquid = $block instanceof Liquid;
			if($liquid && !$includeLiquid){
				continue;
			}
			$result = $block->calculateIntercept($start, $end);
			if($result === null && ($liquid || $includePassable)){
				$result = (new AxisAlignedBB($x, $y, $z, $x + 1, $y + 1, $z + 1))->calculateIntercept($start, $end);
			}
			if($result === null){
				continue;
			}
			return ["block" => $block, "vector" => $result->getHitVector(), "face" => $result->getHitFace(), "distance" => $start->distance($result->getHitVector())];
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>|null
	 */
	private function rayBlock(World $world, Vector3 $start, Vector3 $direction, array $options) : ?array{
		$hit = $this->traceBlock($world, $start, $direction, $options);
		if($hit === null){
			return null;
		}
		$position = $hit["block"]->getPosition();
		return [
			"block" => $this->values->blockRef($hit["block"]),
			"face" => \behaviorpack\script\binding\ServerModule::DIRECTIONS[$hit["face"]] ?? "Up",
			"faceLocation" => [
				"x" => $hit["vector"]->x - $position->x,
				"y" => $hit["vector"]->y - $position->y,
				"z" => $hit["vector"]->z - $position->z
			]
		];
	}

	/**
	 * @param array<string, mixed> $options
	 * @return list<array<string, mixed>>
	 */
	private function rayEntities(World $world, Vector3 $start, Vector3 $direction, array $options, ?Entity $self) : array{
		$direction = $direction->lengthSquared() > 0 ? $direction->normalize() : new Vector3(0, 0, 1);
		$maxDistance = is_numeric($options["maxDistance"] ?? null) ? (float) $options["maxDistance"] : 64.0;
		if(($options["ignoreBlockCollision"] ?? false) !== true){
			$blockHit = $this->traceBlock($world, $start, $direction, $options);
			if($blockHit !== null){
				$maxDistance = min($maxDistance, $blockHit["distance"]);
			}
		}
		$end = $start->addVector($direction->multiply($maxDistance));
		$box = new AxisAlignedBB(min($start->x, $end->x), min($start->y, $end->y), min($start->z, $end->z), max($start->x, $end->x), max($start->y, $end->y), max($start->z, $end->z));
		$type = is_string($options["type"] ?? null) ? ScriptValues::namespaced($options["type"]) : null;
		$excludeTypes = [];
		foreach(is_array($options["excludeTypes"] ?? null) ? $options["excludeTypes"] : [] as $excluded){
			$excludeTypes[] = ScriptValues::namespaced((string) $excluded);
		}
		$hits = [];
		foreach($world->getNearbyEntities($box->expandedCopy(1, 1, 1), $self) as $entity){
			if($entity->isClosed() || $entity->isFlaggedForDespawn()){
				continue;
			}
			$typeId = $this->values->entityTypeId($entity);
			if(($type !== null && $typeId !== $type) || in_array($typeId, $excludeTypes, true)){
				continue;
			}
			$result = $entity->getBoundingBox()->calculateIntercept($start, $end);
			if($result === null){
				continue;
			}
			$hits[] = ["entity" => $entity, "distance" => $start->distance($result->getHitVector())];
		}
		usort($hits, fn(array $a, array $b) : int => $a["distance"] <=> $b["distance"]);
		$result = [];
		foreach($hits as $hit){
			$result[] = ["entity" => $this->values->entityRef($hit["entity"]), "distance" => $hit["distance"]];
		}
		return $result;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function riders(Entity $entity) : array{
		$result = [];
		foreach($entity->getPassengers() as $passenger){
			$result[] = $this->values->entityRef($passenger);
		}
		return $result;
	}

	private function ejectRiders(Entity $entity) : bool{
		foreach($entity->getPassengers() as $passenger){
			$entity->removePassenger($passenger);
		}
		return true;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function vehicle(Entity $entity) : ?array{
		$vehicle = $entity->getVehicle();
		return $vehicle === null ? null : $this->values->entityRef($vehicle);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function itemEntity(Entity $entity) : ?array{
		return $entity instanceof ItemEntity ? $this->values->item($entity->getItem()) : null;
	}

	/**
	 * @param array<string, mixed> $options
	 */
	private function setCamera(Player $player, string $preset, array $options) : bool{
		$index = \array_search(ScriptValues::namespaced($preset), self::CAMERA_PRESETS, true);
		if($index === false){
			throw new ScriptException("Unknown camera preset " . $preset);
		}
		$ease = null;
		if(is_array($options["easeOptions"] ?? null)){
			$easeType = is_string($options["easeOptions"]["easeType"] ?? null) ? $options["easeOptions"]["easeType"] : "Linear";
			try{
				$type = CameraSetInstructionEaseType::fromName(strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $easeType)));
			}catch(\InvalidArgumentException){
				$type = CameraSetInstructionEaseType::LINEAR;
			}
			$ease = new CameraSetInstructionEase($type, is_numeric($options["easeOptions"]["easeTime"] ?? null) ? (float) $options["easeOptions"]["easeTime"] : 1.0);
		}
		$location = isset($options["location"]) ? $this->values->vector($options["location"]) : null;
		$rotation = null;
		if(is_array($options["rotation"] ?? null)){
			$rotation = new CameraSetInstructionRotation((float) ($options["rotation"]["x"] ?? 0), (float) ($options["rotation"]["y"] ?? 0));
		}
		$facing = null;
		if(isset($options["facingLocation"])){
			$facing = $this->values->vector($options["facingLocation"]);
		}elseif(isset($options["facingEntity"])){
			$target = $this->values->findEntity($options["facingEntity"]);
			$facing = $target?->getEyePos();
		}
		$viewOffset = is_array($options["viewOffset"] ?? null) ? new Vector2((float) ($options["viewOffset"]["x"] ?? 0), (float) ($options["viewOffset"]["y"] ?? 0)) : null;
		$entityOffset = isset($options["entityOffset"]) ? $this->values->vector($options["entityOffset"]) : null;
		$set = new CameraSetInstruction((int) $index, $ease, $location, $rotation, $facing, $viewOffset, $entityOffset, null, false);
		return $this->send($player, CameraInstructionPacket::create($set, null, null, null, null, null, null, null, null));
	}

	/**
	 * @param array<string, mixed> $options
	 */
	private function fadeCamera(Player $player, array $options) : bool{
		$time = null;
		if(is_array($options["fadeTime"] ?? null)){
			$fadeTime = $options["fadeTime"];
			$time = new CameraFadeInstructionTime((float) ($fadeTime["fadeInTime"] ?? 0), (float) ($fadeTime["holdTime"] ?? 0), (float) ($fadeTime["fadeOutTime"] ?? 0));
		}
		$color = null;
		if(is_array($options["fadeColor"] ?? null)){
			$fadeColor = $options["fadeColor"];
			$color = new CameraFadeInstructionColor((float) ($fadeColor["red"] ?? 0), (float) ($fadeColor["green"] ?? 0), (float) ($fadeColor["blue"] ?? 0));
		}
		return $this->send($player, CameraInstructionPacket::create(null, null, new CameraFadeInstruction($time, $color), null, null, null, null, null, null));
	}

	/**
	 * Builds the presets sent after the StartGame packet, which the camera
	 * instructions refer to by index.
	 *
	 * @return list<CameraPreset>
	 */
	public static function cameraPresets() : array{
		$presets = [];
		foreach(self::CAMERA_PRESETS as $name){
			$presets[] = new CameraPreset($name, "", null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, false, null);
		}
		return $presets;
	}

	private function setInputPermission(Player $player, int $category, bool $enabled) : bool{
		$locks = $this->state->getLocks($player->getId());
		$locks = $enabled ? $locks & ~(1 << $category) : $locks | (1 << $category);
		$this->state->setLocks($player->getId(), $locks);
		return $this->send($player, UpdateClientInputLocksPacket::create($locks));
	}

	private function buttonState(Player $player, string $button) : string{
		$input = $this->state->getInput($player->getId());
		$pressed = match($button){
			"Jump" => $input["jump"],
			"Sneak" => $input["sneak"],
			default => false
		};
		return $pressed ? "Pressed" : "Released";
	}

	/**
	 * @return array{x: float, y: float}
	 */
	private function movementVector(Player $player) : array{
		$move = $this->state->getInput($player->getId())["move"];
		return ["x" => $move->x, "y" => $move->y];
	}

	/**
	 * @param list<mixed>|null $elements
	 */
	private function setHud(Player $player, bool $hide, ?array $elements) : bool{
		$ids = [];
		foreach($elements ?? \array_map(fn(HudElement $case) : int => $case->value, HudElement::cases()) as $element){
			if(is_numeric($element) && HudElement::tryFrom((int) $element) !== null){
				$ids[] = (int) $element;
			}
		}
		$hidden = $this->state->getHiddenHud($player->getId());
		foreach($ids as $id){
			if($hide){
				$hidden[$id] = true;
			}else{
				unset($hidden[$id]);
			}
		}
		$this->state->setHiddenHud($player->getId(), $hidden);
		return $this->send($player, SetHudPacket::create(\array_map(fn(int $id) => HudElement::from($id), $ids), $hide ? HudVisibility::HIDE : HudVisibility::RESET));
	}

	/**
	 * @param list<mixed> $except
	 */
	private function hideAllExcept(Player $player, array $except) : bool{
		$kept = [];
		foreach($except as $element){
			if(is_numeric($element)){
				$kept[(int) $element] = true;
			}
		}
		$hide = [];
		$show = [];
		foreach(HudElement::cases() as $case){
			if(isset($kept[$case->value])){
				$show[] = $case;
			}else{
				$hide[] = $case;
			}
		}
		$hidden = [];
		foreach($hide as $case){
			$hidden[$case->value] = true;
		}
		$this->state->setHiddenHud($player->getId(), $hidden);
		if(count($show) > 0){
			$this->send($player, SetHudPacket::create($show, HudVisibility::RESET));
		}
		if(count($hide) > 0){
			$this->send($player, SetHudPacket::create($hide, HudVisibility::HIDE));
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function waypointPayload(\Ramsey\Uuid\UuidInterface $uuid, array $data, int $action) : LocatorBarWaypointPayload{
		$position = null;
		$flags = 0;
		$visible = ($data["enabled"] ?? true) !== false;
		$flags |= 1;
		if(isset($data["x"], $data["y"], $data["z"])){
			$dimension = 0;
			try{
				$dimension = $this->networkDimension($this->values->world($data["dimension"] ?? ScriptValues::OVERWORLD));
			}catch(ScriptException){
			}
			$position = new WorldPosition(new Vector3((float) $data["x"], (float) $data["y"], (float) $data["z"]), $dimension);
			$flags |= 2;
		}
		$texture = is_string($data["texture"] ?? null) ? $data["texture"] : null;
		if($texture !== null){
			$flags |= 4;
		}
		$size = null;
		if(is_numeric($data["iconWidth"] ?? null) || is_numeric($data["iconHeight"] ?? null)){
			$size = new Vector2((float) ($data["iconWidth"] ?? 1), (float) ($data["iconHeight"] ?? 1));
			$flags |= 8;
		}
		$color = null;
		if(is_array($data["color"] ?? null)){
			$color = self::color($data["color"]);
			$flags |= 16;
		}
		$actor = null;
		if(isset($data["entity"])){
			$entity = $this->values->findEntity($data["entity"]);
			if($entity !== null){
				$actor = $entity->getId();
				$flags |= 64;
			}
		}
		$waypoint = new LocatorBarWaypoint($flags, $visible, $position, $texture, $size, $color, $actor !== null ? true : null, $actor);
		return new LocatorBarWaypointPayload($uuid, $waypoint, $action);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function addWaypoint(Player $player, string $key, array $data) : bool{
		$existing = $this->state->getWaypoints($player->getId())[$key] ?? null;
		if($existing !== null){
			throw new ScriptException("The waypoint is already in the locator bar");
		}
		$uuid = Uuid::fromString(\substr(md5($key), 0, 8) . "-" . \substr(md5($key), 8, 4) . "-4" . \substr(md5($key), 13, 3) . "-a" . \substr(md5($key), 17, 3) . "-" . \substr(md5($key), 20, 12));
		$this->state->setWaypoint($player->getId(), $key, $uuid, $data);
		return $this->send($player, LocatorBarPacket::create([$this->waypointPayload($uuid, $data, 1)]));
	}

	private function removeWaypoint(Player $player, string $key) : bool{
		$existing = $this->state->getWaypoints($player->getId())[$key] ?? null;
		if($existing === null){
			return false;
		}
		$this->state->removeWaypoint($player->getId(), $key);
		return $this->send($player, LocatorBarPacket::create([$this->waypointPayload($existing["uuid"], $existing["data"], 2)]));
	}

	private function clearWaypoints(Player $player) : bool{
		$payloads = [];
		foreach($this->state->getWaypoints($player->getId()) as $key => $waypoint){
			$payloads[] = $this->waypointPayload($waypoint["uuid"], $waypoint["data"], 2);
			$this->state->removeWaypoint($player->getId(), $key);
		}
		if(count($payloads) > 0){
			$this->send($player, LocatorBarPacket::create($payloads));
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function updateWaypoint(string $key, array $data) : bool{
		foreach($this->state->playersWithWaypoint($key) as $playerId){
			$player = $this->values->findEntity($playerId);
			if(!$player instanceof Player){
				continue;
			}
			$existing = $this->state->getWaypoints($playerId)[$key];
			$this->state->setWaypoint($playerId, $key, $existing["uuid"], $data);
			$this->send($player, LocatorBarPacket::create([$this->waypointPayload($existing["uuid"], $data, 3)]));
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $color
	 */
	private static function color(array $color) : Color{
		$channel = fn(string $key, float $default) : int => (int) (max(0.0, min(1.0, is_numeric($color[$key] ?? null) ? (float) $color[$key] : $default)) * 255);
		return new Color($channel("red", 1.0), $channel("green", 1.0), $channel("blue", 1.0), $channel("alpha", 1.0));
	}

	public function networkDimension(World $world) : int{
		return match($this->values->dimensionId($world)){
			ScriptValues::NETHER => 1,
			ScriptValues::THE_END => 2,
			default => 0
		};
	}

	/**
	 * @param list<array<string, mixed>> $variables
	 */
	private function spawnParticle(World $world, string $effect, Vector3 $location, array $variables, ?Player $player) : bool{
		$json = null;
		if(count($variables) > 0){
			$entries = [];
			foreach($variables as $variable){
				if(!is_array($variable) || !is_string($variable["name"] ?? null)){
					continue;
				}
				$name = $variable["name"];
				$name = \str_starts_with($name, "variable.") ? $name : "variable." . $name;
				$value = $variable["value"] ?? null;
				if(is_array($value)){
					$members = [];
					foreach($value as $member => $memberValue){
						$members[] = ["name" => "." . $member, "value" => ["type" => "float", "value" => (float) $memberValue]];
					}
					$entries[] = ["name" => $name, "value" => ["type" => "member_array", "value" => $members]];
				}else{
					$entries[] = ["name" => $name, "value" => ["type" => "float", "value" => (float) $value]];
				}
			}
			$encoded = json_encode($entries);
			$json = $encoded === false ? null : $encoded;
		}
		$packet = SpawnParticleEffectPacket::create($this->networkDimension($world), -1, $location, $effect, $json);
		$targets = $player !== null ? [$player] : $world->getViewersForPosition($location);
		foreach($targets as $target){
			$target->getNetworkSession()->sendDataPacket($packet);
		}
		return true;
	}

	private function biome(World $world, Vector3 $location) : string{
		if($this->biomeNames === null){
			$this->biomeNames = [];
			foreach((new ReflectionClass(\pocketmine\data\bedrock\BiomeIds::class))->getConstants() as $name => $id){
				if(is_int($id)){
					$this->biomeNames[$name] = $id;
				}
			}
		}
		$x = (int) \floor($location->x);
		$y = (int) \floor($location->y);
		$z = (int) \floor($location->z);
		if(!$world->isChunkLoaded($x >> 4, $z >> 4)){
			throw new ScriptException("The location is in an unloaded chunk");
		}
		$id = $world->getBiomeId($x, $y, $z);
		$name = \array_search($id, $this->biomeNames, true);
		return "minecraft:" . strtolower($name === false ? "plains" : (string) $name);
	}

	private function light(World $world, Vector3 $location, bool $sky) : int{
		$x = (int) \floor($location->x);
		$y = (int) \floor($location->y);
		$z = (int) \floor($location->z);
		if(!$world->isInWorld($x, $y, $z) || !$world->isChunkLoaded($x >> 4, $z >> 4)){
			throw new ScriptException("The location is in an unloaded chunk");
		}
		return $sky ? $world->getRealBlockSkyLightAt($x, $y, $z) : $world->getFullLightAt($x, $y, $z);
	}

	private function chunkLoaded(World $world, Vector3 $location) : bool{
		return $world->isChunkLoaded(((int) \floor($location->x)) >> 4, ((int) \floor($location->z)) >> 4);
	}

	private function saveMode(mixed $options) : string{
		return is_array($options) && ($options["saveMode"] ?? null) === ScriptStructures::MODE_MEMORY ? ScriptStructures::MODE_MEMORY : ScriptStructures::MODE_WORLD;
	}

	/**
	 * @param list<mixed> $a
	 * @return array<string, mixed>
	 */
	private function createStructure(array $a) : array{
		$id = $this->string($a[0] ?? "");
		$options = is_array($a[4] ?? null) ? $a[4] : [];
		$this->structures->createFromWorld($id, $this->values->world($a[1] ?? null), $this->values->vector($a[2] ?? null), $this->values->vector($a[3] ?? null), ($options["includeBlocks"] ?? true) !== false, ($options["includeEntities"] ?? true) !== false, $this->saveMode($options));
		return $this->structureInfo($id);
	}

	/**
	 * @param list<mixed> $a
	 * @return array<string, mixed>
	 */
	private function createEmptyStructure(array $a) : array{
		$id = $this->string($a[0] ?? "");
		$size = $this->values->vector($a[1] ?? null);
		$this->structures->createEmpty($id, [(int) $size->x, (int) $size->y, (int) $size->z], $this->saveMode(["saveMode" => $a[2] ?? null]));
		return $this->structureInfo($id);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function structureInfo(string $id) : ?array{
		if(!$this->structures->exists($id)){
			return null;
		}
		$size = $this->structures->size($id);
		return ["id" => ScriptStructures::normalize($id), "size" => ["x" => $size[0], "y" => $size[1], "z" => $size[2]], "mode" => $this->structures->mode($id)];
	}

	/**
	 * @param array<string, mixed> $options
	 */
	private function placeStructure(string $id, World $world, Vector3 $location, array $options) : bool{
		$rotation = match($options["rotation"] ?? "None"){
			"Rotate90" => 90,
			"Rotate180" => 180,
			"Rotate270" => 270,
			default => 0
		};
		$mirror = match($options["mirror"] ?? "None"){
			"X" => "X",
			"Z" => "Z",
			"XZ" => "XZ",
			default => "None"
		};
		$integrity = is_numeric($options["integrity"] ?? null) ? max(0.0, min(1.0, (float) $options["integrity"])) : 1.0;
		$seed = null;
		if(is_string($options["integritySeed"] ?? null) && $options["integritySeed"] !== ""){
			$seed = (int) \crc32($options["integritySeed"]);
		}
		$this->structures->place($id, $world, $location->floor(), $rotation, $mirror, ($options["includeBlocks"] ?? true) !== false, ($options["includeEntities"] ?? true) !== false, $integrity, $seed);
		return true;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function structureBlock(string $id, Vector3 $location) : ?array{
		$block = $this->structures->getBlock($id, (int) $location->x, (int) $location->y, (int) $location->z);
		return $block === null ? null : $this->values->permutation($block);
	}

	private function setStructureBlock(string $id, Vector3 $location, mixed $permutation) : bool{
		$this->structures->setBlock($id, (int) $location->x, (int) $location->y, (int) $location->z, $permutation === null ? null : $this->values->block($permutation));
		return true;
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	private function placeJigsawStructure(string $identifier, World $world, Vector3 $location, array $options) : array{
		$box = $this->jigsaw->placeStructure($identifier, $world, $location, ($options["ignoreStartHeight"] ?? false) === true, ($options["keepJigsaws"] ?? false) === true);
		return self::boundingBox($box);
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	private function placeJigsawPool(string $pool, string $target, World $world, Vector3 $location, array $options) : array{
		$depth = is_numeric($options["maxDepth"] ?? null) ? (int) $options["maxDepth"] : 7;
		$box = $this->jigsaw->placePool($pool, $target === "" ? null : $target, $world, $location, $depth, ($options["keepJigsaws"] ?? false) === true);
		return self::boundingBox($box);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function boundingBox(AxisAlignedBB $box) : array{
		return [
			"min" => ["x" => (int) $box->minX, "y" => (int) $box->minY, "z" => (int) $box->minZ],
			"max" => ["x" => (int) $box->maxX - 1, "y" => (int) $box->maxY - 1, "z" => (int) $box->maxZ - 1]
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function createTickingArea(string $id, World $world, Vector3 $from, Vector3 $to) : array{
		if(isset($this->tickingAreas[$id])){
			throw new ScriptException("The ticking area " . $id . " already exists");
		}
		$loader = new class implements ChunkLoader{};
		$ticker = new ChunkTicker();
		$chunks = [];
		$minX = ((int) \floor(min($from->x, $to->x))) >> 4;
		$maxX = ((int) \floor(max($from->x, $to->x))) >> 4;
		$minZ = ((int) \floor(min($from->z, $to->z))) >> 4;
		$maxZ = ((int) \floor(max($from->z, $to->z))) >> 4;
		if(($maxX - $minX + 1) * ($maxZ - $minZ + 1) > 1024){
			throw new ScriptException("The ticking area is too large");
		}
		for($x = $minX; $x <= $maxX; $x++){
			for($z = $minZ; $z <= $maxZ; $z++){
				$world->registerChunkLoader($loader, $x, $z, true);
				$world->registerTickingChunk($ticker, $x, $z);
				$world->loadChunk($x, $z);
				$chunks[] = [$x, $z];
			}
		}
		$this->tickingAreas[$id] = ["world" => $world, "loader" => $loader, "ticker" => $ticker, "chunks" => $chunks, "from" => $this->values->vectorOut($from), "to" => $this->values->vectorOut($to)];
		return $this->tickingAreaInfo($id) ?? [];
	}

	private function removeTickingArea(string $id) : bool{
		$area = $this->tickingAreas[$id] ?? null;
		if($area === null){
			return false;
		}
		foreach($area["chunks"] as [$x, $z]){
			$area["world"]->unregisterChunkLoader($area["loader"], $x, $z);
			$area["world"]->unregisterTickingChunk($area["ticker"], $x, $z);
		}
		unset($this->tickingAreas[$id]);
		return true;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function tickingAreaInfo(string $id) : ?array{
		$area = $this->tickingAreas[$id] ?? null;
		if($area === null){
			return null;
		}
		$loaded = true;
		foreach($area["chunks"] as [$x, $z]){
			if(!$area["world"]->isChunkLoaded($x, $z)){
				$loaded = false;
				break;
			}
		}
		return [
			"identifier" => $id,
			"dimension" => ["\$d" => $this->values->dimensionId($area["world"])],
			"boundingBox" => ["min" => $area["from"], "max" => $area["to"]],
			"chunkCount" => count($area["chunks"]),
			"isFullyLoaded" => $loaded
		];
	}

	private function registerDimension(string $typeId) : bool{
		$typeId = strtolower($typeId);
		$folder = str_replace([":", "/"], "_", $typeId);
		$manager = $this->server->getWorldManager();
		if(!$manager->isWorldGenerated($folder)){
			$generator = GeneratorManager::getInstance()->getGenerator("void");
			if($generator === null){
				throw new ScriptException("No void generator is available for custom dimensions");
			}
			$manager->generateWorld($folder, WorldCreationOptions::create()->setGeneratorClass($generator->getGeneratorClass())->setSpawnPosition(new Vector3(0, 64, 0)), false);
		}
		if(!$manager->isWorldLoaded($folder) && !$manager->loadWorld($folder)){
			throw new ScriptException("The custom dimension " . $typeId . " could not be loaded");
		}
		$this->values->registerCustomDimension($typeId, $folder);
		return true;
	}

	/**
	 * @return list<string>
	 */
	private function dimensionTypes() : array{
		$types = [ScriptValues::OVERWORLD, ScriptValues::NETHER, ScriptValues::THE_END];
		foreach($this->values->customDimensions() as $typeId){
			$types[] = $typeId;
		}
		return $types;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function setShape(int $id, array $data) : bool{
		$this->shapes[$id] = $data;
		$packet = $this->shapePacket($id, $data);
		if($packet === null){
			return false;
		}
		foreach($this->shapeViewers($data) as $player){
			$player->getNetworkSession()->sendDataPacket($packet);
		}
		return true;
	}

	private function removeShape(int $id) : bool{
		$data = $this->shapes[$id] ?? null;
		if($data === null){
			return false;
		}
		unset($this->shapes[$id]);
		$packet = PrimitiveShapesPacket::create([PacketShapeData::remove($id)]);
		foreach($this->server->getOnlinePlayers() as $player){
			$player->getNetworkSession()->sendDataPacket($packet);
		}
		return true;
	}

	/**
	 * Sends every primitive shape a player can see, after joining or
	 * changing dimension.
	 */
	public function sendShapes(Player $player) : void{
		$shapes = [];
		foreach($this->shapes as $id => $data){
			foreach($this->shapeViewers($data) as $viewer){
				if($viewer === $player){
					$shape = $this->shapeData($id, $data);
					if($shape !== null){
						$shapes[] = $shape;
					}
				}
			}
		}
		if(count($shapes) > 0){
			$player->getNetworkSession()->sendDataPacket(PrimitiveShapesPacket::create($shapes));
		}
	}

	/**
	 * @param array<string, mixed> $data
	 * @return list<Player>
	 */
	private function shapeViewers(array $data) : array{
		$players = [];
		$visibleTo = is_array($data["visibleTo"] ?? null) ? $data["visibleTo"] : null;
		foreach($this->server->getOnlinePlayers() as $player){
			if($visibleTo !== null){
				$found = false;
				foreach($visibleTo as $ref){
					if(is_array($ref) && (int) ($ref["\$e"] ?? -1) === $player->getId()){
						$found = true;
						break;
					}
				}
				if(!$found){
					continue;
				}
			}
			$players[] = $player;
		}
		return $players;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function shapePacket(int $id, array $data) : ?PrimitiveShapesPacket{
		$shape = $this->shapeData($id, $data);
		return $shape === null ? null : PrimitiveShapesPacket::create([$shape]);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function shapeData(int $id, array $data) : ?PacketShapeData{
		try{
			$location = $this->values->vector($data["location"] ?? null);
		}catch(ScriptException){
			return null;
		}
		$dimension = 0;
		try{
			$dimension = $this->networkDimension($this->values->world($data["dimension"] ?? ScriptValues::OVERWORLD));
		}catch(ScriptException){
		}
		$attached = null;
		if(isset($data["attachedTo"])){
			$attached = $this->values->findEntity($data["attachedTo"])?->getId();
		}
		$rotation = null;
		if(is_array($data["rotation"] ?? null)){
			try{
				$rotation = $this->values->vector($data["rotation"]);
			}catch(ScriptException){
			}
		}
		$payload = new PrimitiveShapeTextPayload(
			is_string($data["text"] ?? null) ? $data["text"] : "",
			($data["useRotation"] ?? false) === true,
			is_array($data["backgroundColorOverride"] ?? null) ? self::color($data["backgroundColorOverride"]) : null,
			0.0,
			($data["depthTest"] ?? true) !== false,
			($data["showBackface"] ?? true) !== false,
			($data["showTextBackface"] ?? true) !== false
		);
		return new PacketShapeData(
			$id,
			PrimitiveShapeType::TEXT,
			$location,
			is_numeric($data["scale"] ?? null) ? (float) $data["scale"] : null,
			$rotation,
			is_numeric($data["timeLeft"] ?? null) ? (float) $data["timeLeft"] : null,
			is_numeric($data["maximumRenderDistance"] ?? null) ? (float) $data["maximumRenderDistance"] : null,
			is_array($data["color"] ?? null) ? self::color($data["color"]) : null,
			$dimension,
			$attached,
			$payload
		);
	}

	private function enchantmentName(\pocketmine\item\enchantment\Enchantment $enchantment) : string{
		if($this->enchantmentNames === null){
			$this->enchantmentNames = [];
			$parser = StringToEnchantmentParser::getInstance();
			foreach($parser->getKnownAliases() as $alias){
				$alias = (string) $alias;
				if(\str_contains($alias, " ")){
					continue;
				}
				$candidate = $parser->parse($alias);
				if($candidate !== null && !isset($this->enchantmentNames[spl_object_id($candidate)])){
					$this->enchantmentNames[spl_object_id($candidate)] = \str_starts_with($alias, "minecraft:") ? \substr($alias, 10) : $alias;
				}
			}
		}
		return $this->enchantmentNames[spl_object_id($enchantment)] ?? "unknown";
	}

	private function item(mixed $data) : Item{
		return $this->values->decodeItem(is_array($data) && isset($data["\$i"]) ? $data : ["\$i" => $data]);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function getEnchantments(mixed $data) : array{
		$result = [];
		foreach($this->item($data)->getEnchantments() as $instance){
			$type = $instance->getType();
			$result[] = ["id" => $this->enchantmentName($type), "maxLevel" => $type->getMaxLevel(), "level" => $instance->getLevel()];
		}
		return $result;
	}

	/**
	 * @param list<mixed> $enchantments
	 * @return array<string, mixed>|null
	 */
	private function setEnchantments(mixed $data, array $enchantments) : ?array{
		$item = $this->item($data);
		$item->removeEnchantments();
		foreach($enchantments as $entry){
			if(!is_array($entry) || !is_string($entry["id"] ?? null)){
				continue;
			}
			$type = StringToEnchantmentParser::getInstance()->parse($entry["id"]);
			if($type === null){
				throw new ScriptException("Unknown enchantment type: " . $entry["id"]);
			}
			$level = is_numeric($entry["level"] ?? null) ? (int) $entry["level"] : 1;
			if($level < 1 || $level > $type->getMaxLevel()){
				throw new ScriptException("Enchantment level " . $level . " is out of bounds for " . $entry["id"]);
			}
			$item->addEnchantment(new EnchantmentInstance($type, $level));
		}
		$result = $this->values->item($item);
		return $result === null ? null : $result["\$i"];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function enchantmentType(string $id) : ?array{
		$type = StringToEnchantmentParser::getInstance()->parse($id);
		return $type === null ? null : ["id" => $this->enchantmentName($type), "maxLevel" => $type->getMaxLevel()];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function enchantmentTypes() : array{
		$seen = [];
		$result = [];
		$parser = StringToEnchantmentParser::getInstance();
		foreach($parser->getKnownAliases() as $alias){
			$type = $parser->parse((string) $alias);
			if($type === null || isset($seen[spl_object_id($type)])){
				continue;
			}
			$seen[spl_object_id($type)] = true;
			$result[] = ["id" => $this->enchantmentName($type), "maxLevel" => $type->getMaxLevel()];
		}
		return $result;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function cooldownInfo(mixed $data) : ?array{
		$item = $this->item($data);
		$ticks = $item->getCooldownTicks();
		if($ticks <= 0){
			return null;
		}
		return ["category" => $item->getCooldownTag() ?? $this->values->itemTypeId($item), "ticks" => $ticks];
	}

	private function startCooldown(Player $player, mixed $data) : bool{
		$item = $this->item($data);
		$player->resetItemCooldown($item);
		return true;
	}

	private function projectile(mixed $handle) : BehaviorEntity{
		$entity = $this->values->entity($handle);
		if(!$entity instanceof BehaviorEntity || !$entity->isCustomProjectile()){
			throw new ScriptException("The entity is not a projectile");
		}
		return $entity;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function projectileInfo(BehaviorEntity $projectile) : array{
		$owner = $projectile->getProjectileOwner();
		return [
			"gravity" => $projectile->getProjectileGravity(),
			"airInertia" => $projectile->getProjectileAirInertia(),
			"liquidInertia" => $projectile->getProjectileLiquidInertia(),
			"owner" => $owner === null ? null : $this->values->entityRef($owner)
		];
	}

	private function setProjectile(BehaviorEntity $projectile, string $key, mixed $value) : bool{
		match($key){
			"gravity" => $projectile->setProjectileGravity((float) $this->number($value)),
			"airInertia" => $projectile->setProjectileAirInertia((float) $this->number($value)),
			"liquidInertia" => $projectile->setProjectileLiquidInertia((float) $this->number($value)),
			"owner" => $projectile->setProjectileOwner($value === null ? null : $this->values->findEntity($value)),
			default => throw new ScriptException("Unknown projectile property " . $key)
		};
		return true;
	}

	private function shootProjectile(BehaviorEntity $projectile, Vector3 $velocity, float $uncertainty) : bool{
		$projectile->shootProjectile($velocity, $uncertainty);
		return true;
	}

	/**
	 * @param array<string, mixed> $state
	 */
	private function showScreen(Player $player, int $formId, array $state) : bool{
		$this->send($player, DduiDataStorePacket::entries([["change", self::DDUI_PROPERTY, $state]]));
		return $this->send($player, ClientboundDataDrivenUIShowScreenPacket::create("minecraft:custom_form", $formId, null));
	}

	/**
	 * Returns the identifiers of every registered entity type.
	 *
	 * @return list<string>
	 */
	private function entityTypeIds() : array{
		$factory = \pocketmine\entity\EntityFactory::getInstance();
		$saveNames = (fn() => $this->saveNames)->call($factory);
		$ids = ["minecraft:player" => true];
		foreach($saveNames as $name){
			if(is_string($name) && \str_contains($name, ":")){
				$ids[strtolower($name)] = true;
			}
		}
		return array_keys($ids);
	}

	/**
	 * Returns the namespaced ids of the known item aliases, keeping only
	 * those that are blocks, or only those that are not.
	 *
	 * @param list<string> $aliases
	 * @return list<string>
	 */
	private function aliasTypeIds(array $aliases, bool $blocks) : array{
		$ids = [];
		$parser = \pocketmine\item\StringToItemParser::getInstance();
		foreach($aliases as $alias){
			$alias = (string) $alias;
			if(!\str_contains($alias, ":")){
				continue;
			}
			$item = $parser->parse($alias);
			if($item === null){
				continue;
			}
			$isBlock = $item instanceof \pocketmine\item\ItemBlock;
			if($isBlock !== $blocks){
				continue;
			}
			$id = $blocks ? $this->values->blockTypeId($item->getBlock()) : $this->values->itemTypeId($item);
			if($id !== "minecraft:unknown"){
				$ids[$id] = true;
			}
		}
		return array_keys($ids);
	}

	private function closeForms(Player $player) : bool{
		$player->closeAllForms();
		return true;
	}

	private function cooldownRemaining(Player $player, mixed $data) : int{
		$item = $this->item($data);
		if(!$player->hasItemCooldown($item)){
			return 0;
		}
		return max(0, $player->getItemCooldownExpiry($item) - $this->server->getTick());
	}
}
