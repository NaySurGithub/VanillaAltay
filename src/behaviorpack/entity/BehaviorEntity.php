<?php

declare(strict_types=1);

namespace behaviorpack\entity;

use pocketmine\block\Water;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\player\Player;
use pocketmine\utils\Utils;
use function array_is_list;
use function array_map;
use function array_search;
use function array_values;
use function atan2;
use function ceil;
use function cos;
use function count;
use function explode;
use function floor;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function max;
use function min;
use function mt_rand;
use function sin;
use function sqrt;
use function str_replace;
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
 */
abstract class BehaviorEntity extends Living{

	private const TAG_COMPONENT_GROUPS = "BehaviorComponentGroups";
	private const TAG_SPAWNED = "BehaviorSpawned";

	private const MAX_EVENT_DEPTH = 16;

	private const TARGET_SEARCH_INTERVAL = 10;
	private const ATTACK_COOLDOWN = 20;
	private const PANIC_TICKS = 60;

	/** @var array<string, mixed>|null */
	private ?array $components = null;

	/** @var list<string> */
	private array $activeGroups = [];

	private bool $spawned = false;

	/** @var list<string> */
	private array $families = [];

	private bool $fireImmune = false;
	private bool $nameable = false;
	private bool $pushable = true;
	private float $attackDamage = 0.0;

	private ?Vector3 $strollTarget = null;
	private int $strollTicks = 0;
	private int $lookTicks = 0;
	private int $panicTicks = 0;
	private ?Vector3 $panicTarget = null;
	private ?Vector3 $panicSource = null;
	private ?Entity $attackTarget = null;
	private int $targetSearchTicks = 0;
	private int $attackCooldown = 0;
	private int $despawnCheckTicks = 0;

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

		$this->components = $this->buildComponents();
		$this->applyComponents(false);

		parent::initEntity($nbt);

		if(!$this->spawned){
			$this->spawned = true;
			$this->triggerEvent("minecraft:entity_spawned");
			$this->applyComponents(true);
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		if(count($this->activeGroups) > 0){
			$nbt->setTag(self::TAG_COMPONENT_GROUPS, new ListTag(array_map(function(string $group) : StringTag{
				return new StringTag($group);
			}, $this->activeGroups), NBT::TAG_String));
		}
		$nbt->setByte(self::TAG_SPAWNED, $this->spawned ? 1 : 0);
		return $nbt;
	}

	/**
	 * Runs an event of the entity definition: adds and removes component
	 * groups, following "sequence", "randomize" and "trigger".
	 */
	public function triggerEvent(string $event) : bool{
		$events = $this->getDefinition()["events"] ?? null;
		if(!is_array($events) || !is_array($events[$event] ?? null)){
			return false;
		}
		$before = $this->activeGroups;
		$this->runEventNode($events[$event], 0);
		if($before !== $this->activeGroups){
			$this->components = $this->buildComponents();
			$this->applyComponents(false);
		}
		return true;
	}

	/**
	 * @param array<mixed> $node
	 */
	private function runEventNode(array $node, int $depth) : void{
		if($depth > self::MAX_EVENT_DEPTH){
			return;
		}
		if(isset($node["filters"]) && is_array($node["filters"]) && !$this->matchesFilter($node["filters"], null, false)){
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

		if(is_array($node["sequence"] ?? null)){
			foreach($node["sequence"] as $entry){
				if(is_array($entry)){
					$this->runEventNode($entry, $depth + 1);
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
						$this->runEventNode($entry, $depth + 1);
						break;
					}
				}
			}
		}

		$trigger = $node["trigger"] ?? null;
		if(is_array($trigger)){
			$target = $trigger["target"] ?? "self";
			$trigger = $target === "self" ? ($trigger["event"] ?? null) : null;
		}
		if(is_string($trigger)){
			$events = $this->getDefinition()["events"] ?? [];
			if(is_array($events) && is_array($events[$trigger] ?? null)){
				$this->runEventNode($events[$trigger], $depth + 1);
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
		$this->pushable = !is_array($pushable) || ($pushable["is_pushable"] ?? true) !== false;

		$attack = $components["minecraft:attack"] ?? null;
		$this->attackDamage = is_array($attack) ? self::rangeValue($attack["damage"] ?? null, 0.0) : 0.0;
	}

	public function attack(EntityDamageEvent $source) : void{
		$cause = $source->getCause();
		if($this->fireImmune && ($cause === EntityDamageEvent::CAUSE_FIRE || $cause === EntityDamageEvent::CAUSE_FIRE_TICK || $cause === EntityDamageEvent::CAUSE_LAVA)){
			$source->cancel();
		}
		parent::attack($source);
		if($source->isCancelled() || !$this->hasComponent("minecraft:behavior.panic")){
			return;
		}
		$this->panicTicks = self::PANIC_TICKS;
		$this->panicTarget = null;
		$this->panicSource = null;
		if($source instanceof EntityDamageByEntityEvent && ($damager = $source->getDamager()) !== null){
			$this->panicSource = $damager->getPosition()->asVector3();
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if($this->closed || !$this->isAlive()){
			return $hasUpdate;
		}

		if($this->tickDespawn($tickDiff)){
			return false;
		}
		$this->tickPush();
		$this->tickAi($tickDiff);

		return true;
	}

	private function tickDespawn(int $tickDiff) : bool{
		$despawn = $this->getComponents()["minecraft:despawn"] ?? null;
		if(!is_array($despawn) || $this->getNameTag() !== ""){
			return false;
		}
		$this->despawnCheckTicks += $tickDiff;
		if($this->despawnCheckTicks < 20){
			return false;
		}
		$this->despawnCheckTicks = 0;

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
		if(!$this->pushable){
			return;
		}
		foreach($this->getWorld()->getNearbyEntities($this->boundingBox, $this) as $entity){
			if(!$entity instanceof Living || ($entity instanceof Player && $entity->isSpectator())){
				continue;
			}
			$dx = $this->location->x - $entity->getLocation()->x;
			$dz = $this->location->z - $entity->getLocation()->z;
			$distance = max(sqrt($dx * $dx + $dz * $dz), 0.01);
			$this->motion = $this->motion->add($dx / $distance * 0.05, 0, $dz / $distance * 0.05);
		}
	}

	private function tickAi(int $tickDiff) : void{
		$components = $this->getComponents();

		if(isset($components["minecraft:behavior.float"]) && $this->isInWater()){
			$this->motion = $this->motion->withComponents(null, max($this->motion->y, 0.08), null);
		}

		if($this->attackCooldown > 0){
			$this->attackCooldown -= $tickDiff;
		}

		if($this->panicTicks > 0 && isset($components["minecraft:behavior.panic"])){
			$this->panicTicks -= $tickDiff;
			if($this->panicTarget === null || $this->horizontalDistanceSquared($this->panicTarget) < 1){
				$this->panicTarget = $this->pickPanicTarget();
			}
			$panic = $components["minecraft:behavior.panic"];
			$this->moveTowards($this->panicTarget, is_array($panic) ? self::toFloat($panic["speed_multiplier"] ?? null, 1.25) : 1.25);
			return;
		}

		if(isset($components["minecraft:behavior.nearest_attackable_target"])){
			$this->targetSearchTicks -= $tickDiff;
			if(!$this->isValidTarget($this->attackTarget)){
				$this->attackTarget = null;
			}
			if($this->attackTarget === null && $this->targetSearchTicks <= 0){
				$this->targetSearchTicks = self::TARGET_SEARCH_INTERVAL;
				$this->attackTarget = $this->findAttackTarget($components["minecraft:behavior.nearest_attackable_target"]);
			}
		}else{
			$this->attackTarget = null;
		}

		$melee = $components["minecraft:behavior.melee_attack"] ?? null;
		if($melee !== null && $this->attackTarget !== null){
			$this->tickMeleeAttack($this->attackTarget, is_array($melee) ? self::toFloat($melee["speed_multiplier"] ?? null, 1.0) : 1.0);
			return;
		}

		$look = $components["minecraft:behavior.look_at_player"] ?? null;
		if($look !== null){
			$lookDistance = is_array($look) ? self::toFloat($look["look_distance"] ?? null, 8.0) : 8.0;
			$probability = is_array($look) ? self::toFloat($look["probability"] ?? null, 0.02) : 0.02;
			$player = $this->findNearestPlayer($lookDistance);
			if($player === null){
				$this->lookTicks = 0;
			}elseif($this->lookTicks > 0){
				$this->lookTicks -= $tickDiff;
				$this->lookAt($player->getEyePos());
				return;
			}elseif($this->strollTarget === null && Utils::getRandomFloat() < $probability){
				$this->lookTicks = mt_rand(40, 80);
			}
		}

		$stroll = $components["minecraft:behavior.random_stroll"] ?? null;
		if($stroll !== null){
			$this->tickRandomStroll(is_array($stroll) ? $stroll : [], $tickDiff);
		}
	}

	/**
	 * @param array<mixed> $stroll
	 */
	private function tickRandomStroll(array $stroll, int $tickDiff) : void{
		if($this->strollTarget !== null){
			$this->strollTicks -= $tickDiff;
			if($this->strollTicks <= 0 || $this->horizontalDistanceSquared($this->strollTarget) < 1){
				$this->strollTarget = null;
				return;
			}
			$this->moveTowards($this->strollTarget, self::toFloat($stroll["speed_multiplier"] ?? null, 1.0));
			return;
		}
		$interval = max(1, (int) self::toFloat($stroll["interval"] ?? null, 120.0));
		if(mt_rand(1, $interval) !== 1){
			return;
		}
		$xz = max(1, (int) self::toFloat($stroll["xz_dist"] ?? null, 10.0));
		$this->strollTarget = new Vector3(
			$this->location->x + mt_rand(-$xz, $xz),
			$this->location->y,
			$this->location->z + mt_rand(-$xz, $xz)
		);
		$this->strollTicks = 100;
	}

	private function tickMeleeAttack(Entity $target, float $speedMultiplier) : void{
		$targetPosition = $target->getPosition();
		$this->moveTowards($targetPosition, $speedMultiplier);
		$this->lookAt($target->getEyePos());

		$reach = ($this->size->getWidth() * 2) ** 2 + $target->getSize()->getWidth();
		if($this->attackCooldown > 0 || $this->location->distanceSquared($targetPosition) > $reach){
			return;
		}
		$this->attackCooldown = self::ATTACK_COOLDOWN;
		$this->broadcastAnimation(new ArmSwingAnimation($this));
		$target->attack(new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, max($this->attackDamage, 0.0)));
	}

	private function isValidTarget(?Entity $target) : bool{
		if($target === null || $target->isClosed() || !$target->isAlive() || $target->getWorld() !== $this->getWorld()){
			return false;
		}
		if($target instanceof Player && ($target->isCreative() || $target->isSpectator())){
			return false;
		}
		return $target->getPosition()->distanceSquared($this->location) <= 32 ** 2;
	}

	private function findAttackTarget(mixed $config) : ?Entity{
		if(!is_array($config)){
			return null;
		}
		$entries = $config["entity_types"] ?? [];
		if(!is_array($entries)){
			return null;
		}
		if(!array_is_list($entries)){
			$entries = [$entries];
		}
		$radius = self::toFloat($config["within_radius"] ?? null, 16.0);
		foreach($entries as $entry){
			if(is_array($entry)){
				$radius = max($radius, self::toFloat($entry["max_dist"] ?? null, $radius));
			}
		}

		$best = null;
		$bestDistance = null;
		foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy($radius, $radius, $radius), $this) as $entity){
			if(!$entity instanceof Living || !$this->isValidTarget($entity)){
				continue;
			}
			$distance = $entity->getPosition()->distanceSquared($this->location);
			foreach($entries as $entry){
				if(!is_array($entry)){
					continue;
				}
				$maxDistance = self::toFloat($entry["max_dist"] ?? null, self::toFloat($config["within_radius"] ?? null, 16.0));
				if($distance > $maxDistance ** 2){
					continue;
				}
				$filters = $entry["filters"] ?? null;
				if(is_array($filters) && !$this->matchesFilter($filters, $entity, true)){
					continue;
				}
				if($bestDistance === null || $distance < $bestDistance){
					$best = $entity;
					$bestDistance = $distance;
				}
				break;
			}
		}
		return $best;
	}

	/**
	 * Evaluates a subset of Bedrock filters: all_of, any_of, none_of,
	 * is_family and has_component. Unsupported tests return $unknownResult.
	 *
	 * @param array<mixed> $filter
	 */
	private function matchesFilter(array $filter, ?Entity $other, bool $unknownResult) : bool{
		if(array_is_list($filter)){
			foreach($filter as $entry){
				if(is_array($entry) && !$this->matchesFilter($entry, $other, $unknownResult)){
					return false;
				}
			}
			return true;
		}
		if(is_array($filter["all_of"] ?? null)){
			foreach($filter["all_of"] as $entry){
				if(is_array($entry) && !$this->matchesFilter($entry, $other, $unknownResult)){
					return false;
				}
			}
			return true;
		}
		if(is_array($filter["any_of"] ?? null)){
			foreach($filter["any_of"] as $entry){
				if(is_array($entry) && $this->matchesFilter($entry, $other, $unknownResult)){
					return true;
				}
			}
			return false;
		}
		if(is_array($filter["none_of"] ?? null)){
			foreach($filter["none_of"] as $entry){
				if(is_array($entry) && $this->matchesFilter($entry, $other, $unknownResult)){
					return false;
				}
			}
			return true;
		}

		$test = $filter["test"] ?? null;
		if(!is_string($test)){
			return $unknownResult;
		}
		$subject = ($filter["subject"] ?? "self") === "self" ? $this : $other;
		if($subject === null){
			return false;
		}
		$value = $filter["value"] ?? null;
		if($test === "is_family"){
			$result = is_string($value) && in_array(strtolower($value), self::familiesOf($subject), true);
		}elseif($test === "has_component"){
			$result = is_string($value) && $subject instanceof BehaviorEntity && $subject->hasComponent($value);
		}else{
			return $unknownResult;
		}
		$operator = $filter["operator"] ?? "==";
		return ($operator === "!=" || $operator === "not" || $operator === "<>") ? !$result : $result;
	}

	/**
	 * @return list<string>
	 */
	private static function familiesOf(Entity $entity) : array{
		if($entity instanceof BehaviorEntity){
			return $entity->getFamilies();
		}
		if($entity instanceof Player){
			return ["player", "mob"];
		}
		$parts = explode("\\", $entity::class);
		return [strtolower($parts[count($parts) - 1]), "mob"];
	}

	private function findNearestPlayer(float $distance) : ?Player{
		$best = null;
		$bestDistance = $distance ** 2;
		foreach($this->getWorld()->getPlayers() as $player){
			if($player->isSpectator() || !$player->isAlive()){
				continue;
			}
			$d = $player->getPosition()->distanceSquared($this->location);
			if($d <= $bestDistance){
				$best = $player;
				$bestDistance = $d;
			}
		}
		return $best;
	}

	private function pickPanicTarget() : Vector3{
		if($this->panicSource !== null){
			$angle = atan2($this->location->z - $this->panicSource->z, $this->location->x - $this->panicSource->x) + (Utils::getRandomFloat() - 0.5);
		}else{
			$angle = Utils::getRandomFloat() * 2 * M_PI;
		}
		return new Vector3($this->location->x + cos($angle) * 6, $this->location->y, $this->location->z + sin($angle) * 6);
	}

	private function moveTowards(Vector3 $target, float $speedMultiplier) : void{
		$dx = $target->x - $this->location->x;
		$dz = $target->z - $this->location->z;
		$distance = sqrt($dx * $dx + $dz * $dz);
		if($distance < 0.1){
			return;
		}
		$speed = $this->getMovementSpeed() * $speedMultiplier;
		$this->motion = new Vector3($dx / $distance * $speed, $this->motion->y, $dz / $distance * $speed);
		$yaw = atan2($dz, $dx) / M_PI * 180 - 90;
		$this->setRotation($yaw < 0 ? $yaw + 360.0 : $yaw, 0.0);
		if($this->isCollidedHorizontally && $this->onGround){
			$this->jump();
		}
	}

	private function horizontalDistanceSquared(Vector3 $target) : float{
		return ($target->x - $this->location->x) ** 2 + ($target->z - $this->location->z) ** 2;
	}

	private function isInWater() : bool{
		return $this->getWorld()->getBlockAt(
			(int) floor($this->location->x),
			(int) floor($this->location->y + 0.3),
			(int) floor($this->location->z)
		) instanceof Water;
	}

	private static function toFloat(mixed $value, float $default) : float{
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
	private static function rangeValue(mixed $value, float $default) : float{
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
			$min = self::toFloat($value["range_min"] ?? null, $default);
			$max = self::toFloat($value["range_max"] ?? null, $min);
		}
		return $min + Utils::getRandomFloat() * max(0.0, $max - $min);
	}
}
