<?php

declare(strict_types=1);

namespace behaviorpack\entity;

use behaviorpack\entity\animation\AnimationRunner;
use behaviorpack\entity\animation\EntityAnimationHost;
use behaviorpack\entity\behavior\BehaviorRegistry;
use behaviorpack\entity\behavior\EntitySystem;
use behaviorpack\entity\behavior\FilterEvaluator;
use behaviorpack\entity\behavior\Goal;
use behaviorpack\entity\behavior\GoalSelector;
use behaviorpack\entity\behavior\Navigator;
use behaviorpack\Molang;
use pocketmine\block\Water;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\math\VoxelRayTrace;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\player\Player;
use pocketmine\utils\Utils;
use function abs;
use function array_is_list;
use function array_map;
use function array_search;
use function array_values;
use function atan2;
use function ceil;
use function count;
use function explode;
use function floor;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function max;
use function min;
use function mt_rand;
use function round;
use function sqrt;
use function str_replace;
use function str_starts_with;
use function strrchr;
use function strtolower;
use function substr;
use function ucwords;
use const M_PI;

/**
 * Base class of the custom entities defined by behavior packs. One tiny
 * subclass is generated per identifier because the network type id and the
 * save id are bound to the class; everything else is read from the entity
 * definition registered in EntityDefinitionRegistry.
 *
 * Every component with a runtime is an EntitySystem, every
 * "minecraft:behavior.*" component a Goal run by the goal selector; both
 * are created from BehaviorRegistry when the component becomes active.
 */
abstract class BehaviorEntity extends Living{

	private const TAG_COMPONENT_GROUPS = "BehaviorComponentGroups";
	private const TAG_SPAWNED = "BehaviorSpawned";
	private const TAG_PROPERTIES = "BehaviorProperties";
	private const TAG_DATA = "BehaviorData";

	private const MAX_EVENT_DEPTH = 16;

	/** @var array<string, mixed>|null */
	private ?array $components = null;

	/** @var list<string> */
	private array $activeGroups = [];

	private bool $spawned = false;

	/** @var array<string, bool|int|float|string> */
	private array $propertyValues = [];

	/** @var list<string> */
	private array $families = [];

	private bool $fireImmune = false;
	private bool $nameable = false;
	private float $attackDamage = 0.0;

	/** @var array<string, EntitySystem> */
	private array $systems = [];

	private ?GoalSelector $goalSelector = null;

	private ?Navigator $navigator = null;

	/** @var array<string, mixed> */
	private array $data = [];

	private ?int $lastHurtById = null;
	private int $lastHurtTick = -1;

	/** @var array<int, bool> */
	private array $flags = [];

	/** @var array<int, array{string, mixed}> */
	private array $metadata = [];

	private bool $collidable = true;
	private bool $pushableByEntity = true;
	private bool $pushableByBlock = true;
	private ?int $xpReward = null;

	private int $despawnCheckTicks = 0;

	private ?AnimationRunner $animationRunner = null;

	public function getAnimationRunner() : AnimationRunner{
		return $this->animationRunner ??= new AnimationRunner(new EntityAnimationHost($this));
	}

	public function getIdentifier() : string{
		return static::getNetworkTypeId();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getDefinition() : array{
		return EntityDefinitionRegistry::get($this->getIdentifier()) ?? [];
	}

	public function getName() : string{
		$identifier = $this->getIdentifier();
		$path = strrchr($identifier, ":");
		return ucwords(str_replace("_", " ", $path === false ? $identifier : substr($path, 1)));
	}

	/**
	 * @return list<string>
	 */
	public function getFamilies() : array{
		return $this->families;
	}

	/**
	 * @return list<string>
	 */
	public function getActiveComponentGroups() : array{
		return $this->activeGroups;
	}

	public function hasComponent(string $name) : bool{
		return isset($this->getComponents()[$name]);
	}

	/**
	 * Returns the configuration of a component, or null when it is not active.
	 *
	 * @return array<mixed>|null
	 */
	public function getComponent(string $name) : ?array{
		$value = $this->getComponents()[$name] ?? null;
		if($value === null){
			return null;
		}
		return is_array($value) ? $value : [];
	}

	/**
	 * Returns the base components merged with those of the active component
	 * groups, in activation order.
	 *
	 * @return array<string, mixed>
	 */
	public function getComponents() : array{
		return $this->components ?? $this->buildComponents();
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		$box = $this->getComponents()["minecraft:collision_box"] ?? null;
		$width = 0.6;
		$height = 1.8;
		if(is_array($box)){
			$width = self::toFloat($box["width"] ?? null, $width);
			$height = self::toFloat($box["height"] ?? null, $height);
		}
		return new EntitySizeInfo(max($height, 0.01), max($width, 0.01));
	}

	public function isFireProof() : bool{
		return $this->fireImmune;
	}

	public function canBeRenamed() : bool{
		return $this->nameable;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$groups = $nbt->getListTag(self::TAG_COMPONENT_GROUPS, StringTag::class);
		if($groups !== null){
			foreach($groups as $group){
				$this->activeGroups[] = $group->getValue();
			}
		}
		$this->spawned = $nbt->getByte(self::TAG_SPAWNED, 0) !== 0;
		$this->loadProperties($nbt->getCompoundTag(self::TAG_PROPERTIES));
		$saved = $nbt->getString(self::TAG_DATA, "");
		if($saved !== ""){
			$decoded = json_decode($saved, true);
			$this->data = is_array($decoded) ? $decoded : [];
		}
		$this->goalSelector = new GoalSelector();
		$this->navigator = new Navigator($this);
		$this->setStepHeight(0.6);

		$this->components = $this->buildComponents();
		$this->applyComponents(false);

		parent::initEntity($nbt);

		$this->syncSystems();

		if(!$this->spawned){
			$this->spawned = true;
			$this->triggerEvent("minecraft:entity_spawned");
			$this->applyComponents(true);
		}
		$this->getAnimationRunner()->initialize();
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		if(count($this->activeGroups) > 0){
			$nbt->setTag(self::TAG_COMPONENT_GROUPS, new ListTag(array_map(function(string $group) : StringTag{
				return new StringTag($group);
			}, $this->activeGroups), NBT::TAG_String));
		}
		$nbt->setByte(self::TAG_SPAWNED, $this->spawned ? 1 : 0);
		if(count($this->propertyValues) > 0){
			$properties = CompoundTag::create();
			foreach($this->propertyValues as $name => $value){
				$properties->setTag($name, match(true){
					is_bool($value) => new ByteTag($value ? 1 : 0),
					is_int($value) => new IntTag($value),
					is_float($value) => new FloatTag($value),
					default => new StringTag($value)
				});
			}
			$nbt->setTag(self::TAG_PROPERTIES, $properties);
		}
		if(count($this->data) > 0){
			$encoded = json_encode($this->data);
			if($encoded !== false){
				$nbt->setString(self::TAG_DATA, $encoded);
			}
		}
		return $nbt;
	}

	/**
	 * Returns a value of the data store: the state the systems and goals keep
	 * across reloads. Values must be JSON serializable.
	 */
	public function getData(string $key, mixed $default = null) : mixed{
		return $this->data[$key] ?? $default;
	}

	public function setData(string $key, mixed $value) : void{
		if($value === null){
			unset($this->data[$key]);
			return;
		}
		$this->data[$key] = $value;
	}

	public function getSystem(string $component) : ?EntitySystem{
		return $this->systems[$component] ?? null;
	}

	/**
	 * @return array<string, EntitySystem>
	 */
	public function getSystems() : array{
		return $this->systems;
	}

	public function getGoalSelector() : GoalSelector{
		return $this->goalSelector ??= new GoalSelector();
	}

	public function getNavigator() : Navigator{
		return $this->navigator ??= new Navigator($this);
	}

	public function setNavigator(?Navigator $navigator) : void{
		$this->navigator?->stop();
		$this->navigator = $navigator ?? new Navigator($this);
	}

	/**
	 * Creates, updates and removes the systems and goals to match the active
	 * components.
	 */
	private function syncSystems() : void{
		if($this->goalSelector === null){
			return;
		}
		$components = $this->getComponents();
		foreach($this->systems as $name => $system){
			if(!isset($components[$name])){
				unset($this->systems[$name]);
				$system->onRemove();
			}
		}
		$goals = $this->goalSelector->getGoals();
		$newGoals = [];
		foreach($components as $name => $value){
			$name = (string) $name;
			$config = is_array($value) ? $value : [];
			$systemClass = BehaviorRegistry::system($name);
			if($systemClass !== null){
				$existing = $this->systems[$name] ?? null;
				if($existing === null){
					$system = new $systemClass($this, $name, $config);
					$this->systems[$name] = $system;
					$system->onAdd();
				}elseif($existing->getConfig() !== $config){
					$existing->updateConfig($config);
				}
			}
			if(str_starts_with($name, "minecraft:behavior.")){
				$goalClass = BehaviorRegistry::goal($name);
				if($goalClass === null){
					continue;
				}
				$existing = $goals[$name] ?? null;
				if($existing !== null && $existing::class === $goalClass){
					if($existing->getConfig() !== $config){
						$existing->updateConfig($config);
					}
					$newGoals[$name] = $existing;
				}else{
					$newGoals[$name] = new $goalClass($this, $name, $config);
				}
			}
		}
		$this->goalSelector->setGoals($newGoals);
	}

	private function loadProperties(?CompoundTag $saved) : void{
		$this->propertyValues = [];
		foreach(EntityProperties::of($this->getIdentifier()) as $name => $property){
			$value = null;
			$tag = $saved?->getTag($name);
			if($tag !== null){
				$raw = $tag->getValue();
				if($property["type"] === EntityProperties::TYPE_BOOL && is_int($raw)){
					$raw = $raw !== 0;
				}
				$value = EntityProperties::coerce($property, $raw);
			}
			$this->propertyValues[$name] = $value ?? EntityProperties::defaultValue($property);
		}
	}

	public function getPropertyValue(string $name) : bool|int|float|string|null{
		return $this->propertyValues[$name] ?? null;
	}

	/**
	 * @return array<string, bool|int|float|string>
	 */
	public function getPropertyValues() : array{
		return $this->propertyValues;
	}

	/**
	 * Changes a property and sends it to the viewers when it is synced.
	 *
	 * @throws \InvalidArgumentException
	 */
	public function setPropertyValue(string $name, mixed $value) : void{
		$property = EntityProperties::of($this->getIdentifier())[$name] ?? null;
		if($property === null){
			throw new \InvalidArgumentException("Property " . $name . " is not defined for " . $this->getIdentifier());
		}
		$coerced = EntityProperties::coerce($property, $value);
		if($coerced === null){
			throw new \InvalidArgumentException("Invalid value for property " . $name);
		}
		if(($this->propertyValues[$name] ?? null) === $coerced){
			return;
		}
		$this->propertyValues[$name] = $coerced;
		if($property["sync"]){
			$this->broadcastProperties();
		}
	}

	public function resetPropertyValue(string $name) : void{
		$property = EntityProperties::of($this->getIdentifier())[$name] ?? null;
		if($property === null){
			throw new \InvalidArgumentException("Property " . $name . " is not defined for " . $this->getIdentifier());
		}
		$this->setPropertyValue($name, EntityProperties::defaultValue($property));
	}

	/**
	 * Receives the hits of the custom projectiles: the projectile, the block
	 * or the entity hit, the hit face and the hit position.
	 *
	 * @var (\Closure(BehaviorEntity, ?\pocketmine\block\Block, int, ?Entity, Vector3) : void)|null
	 */
	public static ?\Closure $projectileHitListener = null;

	private ?int $projectileOwner = null;
	private ?float $projectileGravity = null;
	private ?float $projectileAirInertia = null;
	private ?float $projectileLiquidInertia = null;
	private bool $projectileStuck = false;

	/** @var array<int, true> */
	private array $projectileHitEntities = [];

	public function isCustomProjectile() : bool{
		return $this->hasComponent("minecraft:projectile");
	}

	/**
	 * @return array<mixed>
	 */
	private function projectileConfig() : array{
		$config = $this->getComponents()["minecraft:projectile"] ?? null;
		return is_array($config) ? $config : [];
	}

	public function getProjectileOwner() : ?Entity{
		if($this->projectileOwner === null){
			return null;
		}
		$owner = $this->getWorld()->getServer()->getWorldManager()->findEntity($this->projectileOwner);
		return $owner === null || $owner->isClosed() ? null : $owner;
	}

	public function setProjectileOwner(?Entity $owner) : void{
		$this->projectileOwner = $owner?->getId();
	}

	public function getProjectileGravity() : float{
		return $this->projectileGravity ?? self::toFloat($this->projectileConfig()["gravity"] ?? null, 0.05);
	}

	public function setProjectileGravity(float $gravity) : void{
		$this->projectileGravity = $gravity;
		$this->gravity = $gravity;
	}

	public function getProjectileAirInertia() : float{
		return $this->projectileAirInertia ?? self::toFloat($this->projectileConfig()["inertia"] ?? null, 0.99);
	}

	public function setProjectileAirInertia(float $inertia) : void{
		$this->projectileAirInertia = $inertia;
	}

	public function getProjectileLiquidInertia() : float{
		return $this->projectileLiquidInertia ?? self::toFloat($this->projectileConfig()["liquid_inertia"] ?? null, 0.6);
	}

	public function setProjectileLiquidInertia(float $inertia) : void{
		$this->projectileLiquidInertia = $inertia;
	}

	/**
	 * Launches the projectile with a velocity, spread by the uncertainty.
	 */
	public function shootProjectile(Vector3 $velocity, float $uncertainty) : void{
		if($uncertainty > 0){
			$length = $velocity->length();
			$velocity = $velocity->add(
				(Utils::getRandomFloat() * 2 - 1) * 0.0075 * $uncertainty * $length,
				(Utils::getRandomFloat() * 2 - 1) * 0.0075 * $uncertainty * $length,
				(Utils::getRandomFloat() * 2 - 1) * 0.0075 * $uncertainty * $length
			);
		}
		$this->projectileStuck = false;
		$this->projectileHitEntities = [];
		$this->setMotion($velocity);
		$this->faceMotion($velocity);
	}

	private function faceMotion(Vector3 $motion) : void{
		$horizontal = sqrt($motion->x ** 2 + $motion->z ** 2);
		if($horizontal < 0.0001 && abs($motion->y) < 0.0001){
			return;
		}
		$yaw = atan2($motion->z, $motion->x) / M_PI * 180 - 90;
		$pitch = -atan2($motion->y, $horizontal) / M_PI * 180;
		$this->setRotation($yaw < 0 ? $yaw + 360.0 : $yaw, $pitch);
	}

	protected function move(float $dx, float $dy, float $dz) : void{
		if($this->projectileStuck || !$this->isCustomProjectile()){
			parent::move($dx, $dy, $dz);
			return;
		}
		$start = $this->location->asVector3();
		$end = $start->add($dx, $dy, $dz);
		$world = $this->getWorld();
		$best = null;
		$hitBlock = null;
		$hitEntity = null;
		$face = 1;
		foreach(VoxelRayTrace::betweenPoints($start, $end) as $position){
			$block = $world->getBlockAt((int) $position->x, (int) $position->y, (int) $position->z);
			$result = $block->calculateIntercept($start, $end);
			if($result !== null){
				$best = $result->getHitVector();
				$hitBlock = $block;
				$face = $result->getHitFace();
				break;
			}
		}
		$owner = $this->projectileOwner;
		foreach($world->getNearbyEntities($this->boundingBox->addCoord($dx, $dy, $dz)->expandedCopy(1, 1, 1), $this) as $entity){
			if(isset($this->projectileHitEntities[$entity->getId()]) || !$entity->canBeCollidedWith() || !$entity->isAlive()){
				continue;
			}
			if($entity->getId() === $owner && $this->ticksLived < 5){
				continue;
			}
			if($entity instanceof Player && $entity->isSpectator()){
				continue;
			}
			$result = $entity->getBoundingBox()->expandedCopy(0.3, 0.3, 0.3)->calculateIntercept($start, $end);
			if($result === null){
				continue;
			}
			if($best === null || $start->distanceSquared($result->getHitVector()) < $start->distanceSquared($best)){
				$best = $result->getHitVector();
				$hitEntity = $entity;
				$hitBlock = null;
			}
		}
		if($best === null){
			parent::move($dx, $dy, $dz);
			$this->faceMotion($this->motion);
			return;
		}
		$this->setPosition($best);
		$this->onProjectileHit($hitBlock, $face, $hitEntity, $best);
	}

	private function onProjectileHit(?\pocketmine\block\Block $block, int $face, ?Entity $entity, Vector3 $position) : void{
		if(self::$projectileHitListener !== null){
			(self::$projectileHitListener)($this, $block, $face, $entity, $position);
		}
		$onHit = $this->projectileConfig()["on_hit"] ?? [];
		$onHit = is_array($onHit) ? $onHit : [];
		$owner = $this->getProjectileOwner();
		if($entity !== null){
			$this->projectileHitEntities[$entity->getId()] = true;
			$impact = $onHit["impact_damage"] ?? null;
			if(is_array($impact)){
				$damage = max(0.0, self::rangeValue($impact["damage"] ?? null, 1.0));
				$event = $owner !== null
					? new EntityDamageByChildEntityEvent($owner, $this, $entity, EntityDamageEvent::CAUSE_PROJECTILE, $damage)
					: new EntityDamageByEntityEvent($this, $entity, EntityDamageEvent::CAUSE_PROJECTILE, $damage);
				$entity->attack($event);
			}
		}
		$definitionEvent = $onHit["definition_event"] ?? null;
		if(is_array($definitionEvent) && is_array($definitionEvent["event_trigger"] ?? null)){
			$trigger = $definitionEvent["event_trigger"];
			$name = is_string($trigger["event"] ?? null) ? $trigger["event"] : null;
			$target = $trigger["target"] ?? "self";
			if($name !== null){
				if(($target === "self" || ($definitionEvent["affect_projectile"] ?? false) === true)){
					$this->triggerEvent($name, ["other" => $entity]);
				}
				if(($target === "other" || ($definitionEvent["affect_target"] ?? false) === true) && $entity instanceof BehaviorEntity){
					$entity->triggerEvent($name, ["other" => $this]);
				}
			}
		}
		if(isset($onHit["teleport_owner"]) && $owner !== null){
			$owner->teleport($position);
		}
		if(isset($onHit["remove_on_hit"])){
			$this->flagForDespawn();
			return;
		}
		if($block !== null){
			$this->projectileStuck = true;
			$this->motion = new Vector3(0, 0, 0);
			$this->gravity = 0.0;
		}
	}

	public function getSeatPosition(?Entity $passenger = null) : Vector3{
		$rideable = $this->getComponents()["minecraft:rideable"] ?? null;
		$seats = is_array($rideable) ? ($rideable["seats"] ?? null) : null;
		if(is_array($seats)){
			$seat = array_is_list($seats) ? ($seats[0] ?? null) : $seats;
			if($passenger !== null && array_is_list($seats)){
				$index = array_search($passenger, $this->getPassengers(), true);
				$seat = $seats[$index === false ? 0 : $index] ?? $seat;
			}
			$position = is_array($seat) ? ($seat["position"] ?? null) : null;
			if(is_array($position) && count($position) === 3){
				return new Vector3(self::toFloat($position[0], 0.0), self::toFloat($position[1], 0.0), self::toFloat($position[2], 0.0));
			}
		}
		return parent::getSeatPosition($passenger);
	}

	private function broadcastProperties() : void{
		$packet = SetActorDataPacket::create($this->getId(), [], EntityProperties::syncData($this->getIdentifier(), $this->propertyValues), 0);
		foreach($this->getViewers() as $player){
			$player->getNetworkSession()->sendDataPacket($packet);
		}
	}

	protected function sendSpawnPacket(Player $player) : void{
		parent::sendSpawnPacket($player);
		if(count($this->propertyValues) > 0){
			$player->getNetworkSession()->sendDataPacket(SetActorDataPacket::create($this->getId(), [], EntityProperties::syncData($this->getIdentifier(), $this->propertyValues), 0));
		}
		foreach($this->systems as $system){
			$system->onSpawnTo($player);
		}
	}

	/**
	 * Sets an actor flag (EntityMetadataFlags) sent to the viewers.
	 */
	public function setFlag(int $flag, bool $value) : void{
		if(($this->flags[$flag] ?? null) === $value){
			return;
		}
		$this->flags[$flag] = $value;
		$this->networkPropertiesDirty = true;
	}

	public function getFlag(int $flag) : bool{
		return $this->flags[$flag] ?? false;
	}

	/**
	 * Sets an actor data property (EntityMetadataProperties) sent to the
	 * viewers. The type is "byte", "short", "int", "long", "float" or "string".
	 */
	public function setMetadata(int $key, string $type, mixed $value) : void{
		if(($this->metadata[$key] ?? null) === [$type, $value]){
			return;
		}
		$this->metadata[$key] = [$type, $value];
		$this->networkPropertiesDirty = true;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		foreach($this->flags as $flag => $value){
			$properties->setGenericFlag($flag, $value);
		}
		foreach($this->metadata as $key => [$type, $value]){
			match($type){
				"byte" => $properties->setByte($key, (int) $value),
				"short" => $properties->setShort($key, (int) $value),
				"long" => $properties->setLong($key, (int) $value),
				"float" => $properties->setFloat($key, (float) $value),
				"string" => $properties->setString($key, (string) $value),
				default => $properties->setInt($key, (int) $value)
			};
		}
	}

	public function setCollidable(bool $collidable) : void{
		$this->collidable = $collidable;
	}

	public function canBeCollidedWith() : bool{
		return $this->collidable && parent::canBeCollidedWith();
	}

	public function setPushable(bool $byEntity, bool $byBlock) : void{
		$this->pushableByEntity = $byEntity;
		$this->pushableByBlock = $byBlock;
	}

	public function isPushableByEntity() : bool{
		return $this->pushableByEntity;
	}

	public function isPushableByBlock() : bool{
		return $this->pushableByBlock;
	}

	public function setXpReward(?int $xp) : void{
		$this->xpReward = $xp;
	}

	public function getXpDropAmount() : int{
		return $this->xpReward ?? parent::getXpDropAmount();
	}

	public function getAttackDamage() : float{
		return $this->attackDamage;
	}

	/**
	 * Returns the current attack target, if it is still valid.
	 */
	public function getTargetEntity() : ?Entity{
		if($this->targetId === null){
			return null;
		}
		$target = $this->getWorld()->getEntity($this->targetId);
		if($target === null || $target->isClosed() || !$target->isAlive() || $target->getWorld() !== $this->getWorld()){
			$this->setTargetEntity(null);
			return null;
		}
		return $target;
	}

	public function setTargetEntity(?Entity $target) : void{
		$previousId = $this->targetId;
		$newId = $target?->getId();
		if($previousId === $newId){
			return;
		}
		$previous = $previousId === null ? null : $this->getWorld()->getEntity($previousId);
		parent::setTargetEntity($target !== null && $target->isClosed() ? null : $target);
		foreach($this->systems as $system){
			$system->onTargetChanged($previous, $target);
		}
	}

	/**
	 * Returns the owner of a tamed entity: a player (kept by name so that it
	 * survives reconnections) or an entity.
	 */
	public function getOwner() : ?Entity{
		$owner = $this->getData("owner");
		if(!is_array($owner)){
			return null;
		}
		$server = $this->getWorld()->getServer();
		if(($owner["type"] ?? null) === "player" && is_string($owner["name"] ?? null)){
			return $server->getPlayerExact($owner["name"]);
		}
		if(is_int($owner["id"] ?? null)){
			$entity = $server->getWorldManager()->findEntity($owner["id"]);
			return $entity === null || $entity->isClosed() ? null : $entity;
		}
		return null;
	}

	public function setOwner(?Entity $owner) : void{
		if($owner === null){
			$this->setData("owner", null);
			$this->setMetadata(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties::OWNER_EID, "long", -1);
			return;
		}
		$this->setData("owner", $owner instanceof Player ? ["type" => "player", "name" => $owner->getName()] : ["type" => "entity", "id" => $owner->getId()]);
		$this->setMetadata(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties::OWNER_EID, "long", $owner->getId());
	}

	public function isOwnedBy(Entity $entity) : bool{
		$owner = $this->getData("owner");
		if(!is_array($owner)){
			return false;
		}
		if($entity instanceof Player){
			return ($owner["type"] ?? null) === "player" && ($owner["name"] ?? null) === $entity->getName();
		}
		return ($owner["id"] ?? null) === $entity->getId();
	}

	public function getLastHurtBy() : ?Entity{
		if($this->lastHurtById === null){
			return null;
		}
		$entity = $this->getWorld()->getEntity($this->lastHurtById);
		return $entity === null || $entity->isClosed() || !$entity->isAlive() ? null : $entity;
	}

	public function getLastHurtTick() : int{
		return $this->lastHurtTick;
	}

	/**
	 * Swings and hits a target with the attack damage and the attack effect
	 * of the entity. Returns whether the hit landed.
	 */
	public function performMeleeAttack(Entity $target, ?float $damage = null) : bool{
		$this->broadcastAnimation(new ArmSwingAnimation($this));
		$event = new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, max($damage ?? $this->attackDamage, 0.0));
		$target->attack($event);
		if($event->isCancelled()){
			return false;
		}
		$attack = $this->getComponent("minecraft:attack");
		if($attack !== null && is_string($attack["effect_name"] ?? null) && $target instanceof Living){
			$effect = \pocketmine\entity\effect\StringToEffectParser::getInstance()->parse($attack["effect_name"]);
			if($effect !== null){
				$seconds = self::toFloat($attack["effect_duration"] ?? null, 0.0);
				$target->getEffects()->add(new \pocketmine\entity\effect\EffectInstance($effect, max(1, (int) ($seconds * 20))));
			}
		}
		return true;
	}

	/**
	 * Evaluates a Molang value with the property and state queries of this entity.
	 */
	public function molang(mixed $value) : float{
		return Molang::evaluate($value, EntityAnimationHost::variablesOf($this), function(string $query, array $args) : bool|int|float|string|null{
			return match($query){
				"property", "actor_property" => $this->queryProperty($args[0] ?? null),
				"has_property" => is_string($args[0] ?? null) && isset($this->propertyValues[$args[0]]),
				"health" => $this->getHealth(),
				"max_health" => $this->getMaxHealth(),
				"is_on_ground" => $this->onGround,
				"is_in_water" => $this->isInWater(),
				"is_on_fire" => $this->isOnFire(),
				"is_sneaking" => $this->isSneaking(),
				"has_target" => $this->getTargetEntity() !== null,
				"is_tamed" => $this->hasComponent("minecraft:is_tamed"),
				"is_baby" => $this->hasComponent("minecraft:is_baby"),
				"variant" => (int) $this->getData("variant", 0),
				"mark_variant" => (int) $this->getData("mark_variant", 0),
				"skin_id" => (int) $this->getData("skin_id", 0),
				"scale" => $this->getScale(),
				"position" => match((int) ($args[0] ?? 0)){
					1 => $this->location->y,
					2 => $this->location->z,
					default => $this->location->x
				},
				"time_of_day" => ($this->getWorld()->getTimeOfDay() % 24000) / 24000,
				"day" => (int) ($this->getWorld()->getTime() / 24000),
				default => 0.0
			};
		});
	}

	private function queryProperty(mixed $name) : bool|int|float|null{
		if(!is_string($name)){
			return null;
		}
		$value = $this->propertyValues[$name] ?? null;
		if(is_string($value)){
			$property = EntityProperties::of($this->getIdentifier())[$name] ?? null;
			return $property === null ? 0 : (int) array_search($value, $property["values"], true);
		}
		return $value;
	}

	/**
	 * Applies a "set_property" event response.
	 *
	 * @param array<mixed> $changes
	 */
	private function applySetProperty(array $changes) : void{
		$definitions = EntityProperties::of($this->getIdentifier());
		foreach($changes as $name => $value){
			$property = $definitions[$name] ?? null;
			if($property === null){
				continue;
			}
			if($property["type"] === EntityProperties::TYPE_ENUM){
				if(!is_string($value) || !in_array($value, $property["values"], true)){
					$index = (int) $this->molang($value);
					$value = $property["values"][$index] ?? null;
				}
			}elseif($property["type"] === EntityProperties::TYPE_BOOL){
				$value = is_bool($value) ? $value : $this->molang($value) != 0;
			}else{
				$number = $this->molang($value);
				$number = max($property["min"], min($property["max"], $number));
				$value = $property["type"] === EntityProperties::TYPE_INT ? (int) round($number) : $number;
			}
			if($value !== null){
				try{
					$this->setPropertyValue((string) $name, $value);
				}catch(\InvalidArgumentException){
				}
			}
		}
	}

	/**
	 * Evaluates a filter with this entity as "self".
	 *
	 * @param array<mixed>          $filter
	 * @param array<string, mixed> $context
	 */
	public function testFilter(array $filter, array $context = [], bool $unknownResult = false) : bool{
		return FilterEvaluator::test($filter, $this, $context, $unknownResult);
	}

	/**
	 * Runs an event of the entity definition: adds and removes component
	 * groups, following "sequence", "randomize" and "trigger". The context
	 * names the entities its filters and triggers can refer to.
	 *
	 * @param array<string, mixed> $context
	 */
	public function triggerEvent(string $event, array $context = []) : bool{
		$events = $this->getDefinition()["events"] ?? null;
		if(!is_array($events) || !is_array($events[$event] ?? null)){
			return false;
		}
		$before = $this->activeGroups;
		$this->runEventNode($events[$event], 0, $context);
		if($before !== $this->activeGroups){
			$this->components = $this->buildComponents();
			$this->applyComponents(false);
			$this->syncSystems();
		}
		return true;
	}

	/**
	 * Runs a trigger of a component: an event name, or an object with
	 * "event", "target" and "filters". Returns whether an event ran.
	 *
	 * @param array<string, mixed> $context
	 */
	public function runTrigger(mixed $trigger, array $context = []) : bool{
		if(is_string($trigger)){
			return $this->triggerEvent($trigger, $context);
		}
		if(!is_array($trigger)){
			return false;
		}
		if(is_array($trigger["filters"] ?? null) && !$this->testFilter($trigger["filters"], $context)){
			return false;
		}
		$event = $trigger["event"] ?? null;
		if(!is_string($event)){
			return false;
		}
		$target = FilterEvaluator::subject($trigger["target"] ?? "self", $this, $context);
		if(!$target instanceof BehaviorEntity){
			return false;
		}
		if($target === $this){
			return $this->triggerEvent($event, $context);
		}
		$swapped = $context;
		$swapped["other"] = $this;
		return $target->triggerEvent($event, $swapped);
	}

	/**
	 * @param array<mixed>          $node
	 * @param array<string, mixed> $context
	 */
	private function runEventNode(array $node, int $depth, array $context) : void{
		if($depth > self::MAX_EVENT_DEPTH){
			return;
		}
		if(isset($node["filters"]) && is_array($node["filters"]) && !$this->testFilter($node["filters"], $context)){
			return;
		}

		$groups = $this->getDefinition()["component_groups"] ?? [];
		foreach($node["remove"]["component_groups"] ?? [] as $group){
			if(is_string($group) && ($index = array_search($group, $this->activeGroups, true)) !== false){
				unset($this->activeGroups[$index]);
				$this->activeGroups = array_values($this->activeGroups);
			}
		}
		foreach($node["add"]["component_groups"] ?? [] as $group){
			if(is_string($group) && is_array($groups) && isset($groups[$group]) && !in_array($group, $this->activeGroups, true)){
				$this->activeGroups[] = $group;
			}
		}
		if(is_array($node["set_property"] ?? null)){
			$this->applySetProperty($node["set_property"]);
		}
		if(isset($node["reset_target"])){
			$this->setTargetEntity(null);
		}

		if(is_array($node["sequence"] ?? null)){
			foreach($node["sequence"] as $entry){
				if(is_array($entry)){
					$this->runEventNode($entry, $depth + 1, $context);
				}
			}
		}

		if(is_array($node["randomize"] ?? null)){
			$total = 0.0;
			foreach($node["randomize"] as $entry){
				if(is_array($entry)){
					$total += max(0.0, self::toFloat($entry["weight"] ?? null, 1.0));
				}
			}
			if($total > 0){
				$roll = Utils::getRandomFloat() * $total;
				foreach($node["randomize"] as $entry){
					if(!is_array($entry)){
						continue;
					}
					$roll -= max(0.0, self::toFloat($entry["weight"] ?? null, 1.0));
					if($roll <= 0){
						$this->runEventNode($entry, $depth + 1, $context);
						break;
					}
				}
			}
		}

		$trigger = $node["trigger"] ?? null;
		if(is_string($trigger)){
			$trigger = ["event" => $trigger, "target" => "self"];
		}
		if(is_array($trigger) && is_string($trigger["event"] ?? null)){
			$target = FilterEvaluator::subject($trigger["target"] ?? "self", $this, $context);
			if($target === $this){
				$events = $this->getDefinition()["events"] ?? [];
				if(is_array($events) && is_array($events[$trigger["event"]] ?? null)){
					$this->runEventNode($events[$trigger["event"]], $depth + 1, $context);
				}
			}elseif($target instanceof BehaviorEntity){
				$swapped = $context;
				$swapped["other"] = $this;
				$target->triggerEvent($trigger["event"], $swapped);
			}
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildComponents() : array{
		$definition = $this->getDefinition();
		$components = is_array($definition["components"] ?? null) ? $definition["components"] : [];
		$groups = is_array($definition["component_groups"] ?? null) ? $definition["component_groups"] : [];
		foreach($this->activeGroups as $group){
			if(is_array($groups[$group] ?? null)){
				foreach($groups[$group] as $name => $value){
					$components[$name] = $value;
				}
			}
		}
		return $components;
	}

	private function applyComponents(bool $resetHealth) : void{
		$components = $this->getComponents();

		$health = $components["minecraft:health"] ?? null;
		if(is_array($health)){
			$value = self::rangeValue($health["value"] ?? null, 20.0);
			$maxHealth = isset($health["max"]) ? self::rangeValue($health["max"], $value) : $value;
			$this->setMaxHealth(max(1, (int) ceil($maxHealth)));
			if($resetHealth){
				$this->setHealth(min($value, (float) $this->getMaxHealth()));
			}
		}

		$movement = $components["minecraft:movement"] ?? null;
		if(is_array($movement)){
			$this->setMovementSpeed(self::rangeValue($movement["value"] ?? null, 0.25), true);
		}

		$physics = $components["minecraft:physics"] ?? null;
		$this->setHasGravity(!is_array($physics) || ($physics["has_gravity"] ?? true) !== false);

		$scale = $components["minecraft:scale"] ?? null;
		$this->setScale(max(0.01, is_array($scale) ? self::toFloat($scale["value"] ?? null, 1.0) : 1.0));

		$families = $components["minecraft:type_family"]["family"] ?? [];
		$this->families = [];
		if(is_array($families)){
			foreach($families as $family){
				if(is_string($family)){
					$this->families[] = strtolower($family);
				}
			}
		}

		$this->fireImmune = isset($components["minecraft:fire_immune"]) || ($components["minecraft:is_immune_to_fire"] ?? false) !== false;

		$knockback = $components["minecraft:knockback_resistance"] ?? null;
		$this->knockbackResistanceAttr->setValue(is_array($knockback) ? max(0.0, min(1.0, self::toFloat($knockback["value"] ?? null, 0.0))) : 0.0, true);

		$nameable = $components["minecraft:nameable"] ?? null;
		$this->nameable = $nameable !== null;
		if(is_array($nameable)){
			$this->setNameTagAlwaysVisible(($nameable["always_show"] ?? false) === true);
		}

		$pushable = $components["minecraft:pushable"] ?? null;
		if(is_array($pushable)){
			$this->setPushable(($pushable["is_pushable"] ?? true) !== false, ($pushable["is_pushable_by_piston"] ?? true) !== false);
		}

		$attack = $components["minecraft:attack"] ?? null;
		$this->attackDamage = is_array($attack) ? self::rangeValue($attack["damage"] ?? null, 0.0) : 0.0;

		if(isset($components["minecraft:projectile"])){
			$this->gravity = $this->projectileStuck ? 0.0 : $this->getProjectileGravity();
			$this->drag = 1 - $this->getProjectileAirInertia();
		}
	}

	public function attack(EntityDamageEvent $source) : void{
		$cause = $source->getCause();
		if($this->fireImmune && ($cause === EntityDamageEvent::CAUSE_FIRE || $cause === EntityDamageEvent::CAUSE_FIRE_TICK || $cause === EntityDamageEvent::CAUSE_LAVA)){
			$source->cancel();
		}
		foreach($this->systems as $system){
			$system->beforeDamage($source);
		}
		parent::attack($source);
		if($source->isCancelled()){
			return;
		}
		if($source instanceof EntityDamageByEntityEvent && ($damager = $source->getDamager()) !== null){
			$this->lastHurtById = $damager->getId();
			$this->lastHurtTick = $this->getWorld()->getServer()->getTick();
		}
		foreach($this->systems as $system){
			$system->afterDamage($source);
		}
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$handled = false;
		foreach($this->systems as $system){
			if($system->onInteract($player, $clickPos)){
				$handled = true;
			}
		}
		return $handled || parent::onInteract($player, $clickPos);
	}

	protected function onDeath() : void{
		foreach($this->systems as $system){
			$system->onDeath();
		}
		$this->getGoalSelector()->stopAll();
		parent::onDeath();
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if($this->closed || !$this->isAlive()){
			return $hasUpdate;
		}

		if($this->tickDespawn($tickDiff)){
			return false;
		}
		if($this->isCustomProjectile()){
			if($this->projectileStuck){
				$this->motion = new Vector3(0, 0, 0);
			}else{
				$this->drag = 1 - ($this->isInWater() ? $this->getProjectileLiquidInertia() : $this->getProjectileAirInertia());
			}
			return true;
		}
		foreach($this->systems as $system){
			$system->tick($tickDiff);
			if($this->closed || !$this->isAlive()){
				return false;
			}
		}
		$this->getAnimationRunner()->tick($tickDiff);
		if($this->closed || !$this->isAlive()){
			return false;
		}
		$this->getGoalSelector()->tick($tickDiff);
		$this->getNavigator()->tick();
		$this->tickPush();

		return true;
	}

	private function tickDespawn(int $tickDiff) : bool{
		$despawn = $this->getComponents()["minecraft:despawn"] ?? null;
		if(!is_array($despawn) || $this->getNameTag() !== "" || $this->hasComponent("minecraft:persistent") || $this->getData("persistent") === true || $this->getData("owner") !== null){
			return false;
		}
		$this->despawnCheckTicks += $tickDiff;
		if($this->despawnCheckTicks < 20){
			return false;
		}
		$this->despawnCheckTicks = 0;
		if(is_array($despawn["filters"] ?? null) && !$this->testFilter($despawn["filters"])){
			return false;
		}

		$distance = $despawn["despawn_from_distance"] ?? null;
		$maxDistance = is_array($distance) ? self::toFloat($distance["max_distance"] ?? null, 128.0) : 128.0;
		$minDistance = is_array($distance) ? self::toFloat($distance["min_distance"] ?? null, 32.0) : 32.0;

		$nearest = null;
		foreach($this->getWorld()->getPlayers() as $player){
			$d = $player->getPosition()->distanceSquared($this->location);
			if($nearest === null || $d < $nearest){
				$nearest = $d;
			}
		}
		if($nearest === null || $nearest > $maxDistance ** 2 || ($nearest > $minDistance ** 2 && mt_rand(1, 40) === 1)){
			$this->flagForDespawn();
			return true;
		}
		return false;
	}

	private function tickPush() : void{
		if(!$this->pushableByEntity){
			return;
		}
		foreach($this->getWorld()->getNearbyEntities($this->boundingBox, $this) as $entity){
			if(!$entity instanceof Living || !$entity->canBeCollidedWith() || ($entity instanceof Player && $entity->isSpectator())){
				continue;
			}
			$dx = $this->location->x - $entity->getLocation()->x;
			$dz = $this->location->z - $entity->getLocation()->z;
			$distance = max(sqrt($dx * $dx + $dz * $dz), 0.01);
			$this->motion = $this->motion->add($dx / $distance * 0.05, 0, $dz / $distance * 0.05);
		}
	}

	/**
	 * @return list<string>
	 */
	public static function familiesOf(Entity $entity) : array{
		if($entity instanceof BehaviorEntity){
			return $entity->getFamilies();
		}
		if($entity instanceof Player){
			return ["player", "mob"];
		}
		$parts = explode("\\", $entity::class);
		return [strtolower($parts[count($parts) - 1]), "mob"];
	}

	public function horizontalDistanceSquared(Vector3 $target) : float{
		return ($target->x - $this->location->x) ** 2 + ($target->z - $this->location->z) ** 2;
	}

	public function isInWater() : bool{
		return $this->getWorld()->getBlockAt(
			(int) floor($this->location->x),
			(int) floor($this->location->y + 0.3),
			(int) floor($this->location->z)
		) instanceof Water;
	}

	public static function toFloat(mixed $value, float $default) : float{
		if(is_int($value) || is_float($value)){
			return (float) $value;
		}
		if(is_bool($value)){
			return $value ? 1.0 : 0.0;
		}
		return $default;
	}

	/**
	 * Reads a number, a [min, max] pair or a {range_min, range_max} object.
	 */
	public static function rangeValue(mixed $value, float $default) : float{
		if(is_int($value) || is_float($value)){
			return (float) $value;
		}
		if(!is_array($value)){
			return $default;
		}
		if(array_is_list($value) && count($value) === 2){
			$min = self::toFloat($value[0], $default);
			$max = self::toFloat($value[1], $min);
		}else{
			$min = self::toFloat($value["range_min"] ?? $value["min"] ?? null, $default);
			$max = self::toFloat($value["range_max"] ?? $value["max"] ?? null, $min);
		}
		return $min + Utils::getRandomFloat() * max(0.0, $max - $min);
	}
}
