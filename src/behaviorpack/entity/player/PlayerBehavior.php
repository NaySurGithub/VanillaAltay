<?php

declare(strict_types=1);

namespace behaviorpack\entity\player;

use behaviorpack\entity\animation\AnimationHost;
use behaviorpack\entity\animation\AnimationRunner;
use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\EntityProperties;
use behaviorpack\Molang;
use behaviorpack\script\ScriptCommandSender;
use pocketmine\block\Water;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\player\Player;
use pocketmine\utils\Utils;
use function array_search;
use function array_values;
use function ceil;
use function floor;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function ltrim;
use function max;
use function min;
use function round;

/**
 * The behavior pack state of an online player: the properties, component
 * groups and Molang variables of the "minecraft:player" definition, and the
 * runner of its animation controllers.
 */
final class PlayerBehavior implements AnimationHost{

	private const MAX_EVENT_DEPTH = 16;

	private static ?ScriptCommandSender $commandSender = null;

	/** @var array<string, bool|int|float|string> */
	private array $propertyValues = [];

	/** @var list<string> */
	private array $activeGroups = [];

	/** @var array<string, float> */
	private array $variables = [];

	private bool $initialized = false;

	private bool $spawned = false;

	private AnimationRunner $runner;

	private ?Vector3 $lastPosition = null;

	private bool $moving = false;

	/**
	 * @param array<mixed> $saved
	 */
	public function __construct(
		private Player $player,
		array $saved = []
	){
		$this->runner = new AnimationRunner($this);
		$this->load($saved);
	}

	public function getPlayer() : Player{
		return $this->player;
	}

	/**
	 * @return array<mixed>
	 */
	private function definition() : array{
		return PlayerDefinition::get() ?? [];
	}

	/**
	 * @param array<mixed> $saved
	 */
	private function load(array $saved) : void{
		$stored = is_array($saved["properties"] ?? null) ? $saved["properties"] : [];
		foreach(EntityProperties::of(PlayerDefinition::IDENTIFIER) as $name => $property){
			$value = isset($stored[$name]) ? EntityProperties::coerce($property, $stored[$name]) : null;
			$this->propertyValues[$name] = $value ?? EntityProperties::defaultValue($property);
		}
		$groups = $this->definition()["component_groups"] ?? [];
		foreach(is_array($saved["groups"] ?? null) ? $saved["groups"] : [] as $group){
			if(is_string($group) && is_array($groups) && isset($groups[$group]) && !in_array($group, $this->activeGroups, true)){
				$this->activeGroups[] = $group;
			}
		}
		foreach(is_array($saved["variables"] ?? null) ? $saved["variables"] : [] as $name => $value){
			if(is_string($name) && (is_int($value) || is_float($value))){
				$this->variables[$name] = (float) $value;
			}
		}
		$this->initialized = ($saved["initialized"] ?? false) === true;
		$this->spawned = ($saved["spawned"] ?? false) === true;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function save() : array{
		return [
			"properties" => $this->propertyValues,
			"groups" => $this->activeGroups,
			"variables" => $this->variables,
			"initialized" => $this->initialized,
			"spawned" => $this->spawned
		];
	}

	/**
	 * Runs the first spawn event and the initialize scripts, applies the
	 * components and sends the properties to the player.
	 */
	public function onJoin() : void{
		if(!$this->spawned){
			$this->spawned = true;
			$this->triggerEvent("minecraft:entity_spawned");
		}
		$this->applyComponents();
		$this->runner->initialize();
		$this->broadcastProperties();
	}

	public function tick(int $tickDiff) : void{
		$position = $this->player->getPosition()->asVector3();
		$this->moving = $this->lastPosition !== null && $this->lastPosition->distanceSquared($position) > 0.0001;
		$this->lastPosition = $position;
		$this->runner->tick($tickDiff);
	}

	/**
	 * @return array<string, bool|int|float|string>
	 */
	public function getPropertyValues() : array{
		return $this->propertyValues;
	}

	public function getPropertyValue(string $name) : bool|int|float|string|null{
		return $this->propertyValues[$name] ?? null;
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	public function setPropertyValue(string $name, mixed $value) : void{
		$property = EntityProperties::of(PlayerDefinition::IDENTIFIER)[$name] ?? null;
		if($property === null){
			throw new \InvalidArgumentException("Property " . $name . " is not defined for " . PlayerDefinition::IDENTIFIER);
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

	/**
	 * @throws \InvalidArgumentException
	 */
	public function resetPropertyValue(string $name) : void{
		$property = EntityProperties::of(PlayerDefinition::IDENTIFIER)[$name] ?? null;
		if($property === null){
			throw new \InvalidArgumentException("Property " . $name . " is not defined for " . PlayerDefinition::IDENTIFIER);
		}
		$this->setPropertyValue($name, EntityProperties::defaultValue($property));
	}

	public function syncData() : PropertySyncData{
		return EntityProperties::syncData(PlayerDefinition::IDENTIFIER, $this->propertyValues);
	}

	public function broadcastProperties() : void{
		if(!$this->player->isConnected()){
			return;
		}
		$packet = SetActorDataPacket::create($this->player->getId(), [], $this->syncData(), 0);
		$this->player->getNetworkSession()->sendDataPacket($packet);
		foreach($this->player->getViewers() as $viewer){
			if($viewer !== $this->player && $viewer->isConnected()){
				$viewer->getNetworkSession()->sendDataPacket($packet);
			}
		}
	}

	/**
	 * Runs an event of the player definition.
	 */
	public function triggerEvent(string $event) : bool{
		$events = $this->definition()["events"] ?? null;
		if(!is_array($events) || !is_array($events[$event] ?? null)){
			return false;
		}
		$before = $this->activeGroups;
		$this->runEventNode($events[$event], 0);
		if($before !== $this->activeGroups){
			$this->applyComponents();
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
		$groups = $this->definition()["component_groups"] ?? [];
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
					$total += max(0.0, BehaviorEntity::toFloat($entry["weight"] ?? null, 1.0));
				}
			}
			if($total > 0){
				$roll = Utils::getRandomFloat() * $total;
				foreach($node["randomize"] as $entry){
					if(!is_array($entry)){
						continue;
					}
					$roll -= max(0.0, BehaviorEntity::toFloat($entry["weight"] ?? null, 1.0));
					if($roll <= 0){
						$this->runEventNode($entry, $depth + 1);
						break;
					}
				}
			}
		}
		$trigger = $node["trigger"] ?? null;
		if(is_array($trigger)){
			$trigger = $trigger["event"] ?? null;
		}
		if(is_string($trigger)){
			$events = $this->definition()["events"] ?? [];
			if(is_array($events) && is_array($events[$trigger] ?? null)){
				$this->runEventNode($events[$trigger], $depth + 1);
			}
		}
	}

	/**
	 * @param array<mixed> $changes
	 */
	private function applySetProperty(array $changes) : void{
		$definitions = EntityProperties::of(PlayerDefinition::IDENTIFIER);
		foreach($changes as $name => $value){
			$property = $definitions[$name] ?? null;
			if($property === null){
				continue;
			}
			if($property["type"] === EntityProperties::TYPE_ENUM){
				if(!is_string($value) || !in_array($value, $property["values"], true)){
					$value = $property["values"][(int) $this->molang($value)] ?? null;
				}
			}elseif($property["type"] === EntityProperties::TYPE_BOOL){
				$value = is_bool($value) ? $value : $this->molang($value) != 0;
			}else{
				$number = max($property["min"], min($property["max"], $this->molang($value)));
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
	 * @return array<string, mixed>
	 */
	private function components() : array{
		$definition = $this->definition();
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

	private function applyComponents() : void{
		$components = $this->components();
		$scale = $components["minecraft:scale"] ?? null;
		$this->player->setScale(max(0.01, is_array($scale) ? BehaviorEntity::toFloat($scale["value"] ?? null, 1.0) : 1.0));
		$health = $components["minecraft:health"] ?? null;
		if(is_array($health)){
			$value = BehaviorEntity::rangeValue($health["value"] ?? null, 20.0);
			$maxHealth = isset($health["max"]) ? BehaviorEntity::rangeValue($health["max"], $value) : $value;
			$this->player->setMaxHealth(max(1, (int) ceil($maxHealth)));
		}
	}

	private function queryProperty(mixed $name) : bool|int|float|null{
		if(!is_string($name)){
			return null;
		}
		$value = $this->propertyValues[$name] ?? null;
		if(is_string($value)){
			$property = EntityProperties::of(PlayerDefinition::IDENTIFIER)[$name] ?? null;
			return $property === null ? 0 : (int) array_search($value, $property["values"], true);
		}
		return $value;
	}

	/**
	 * Evaluates a Molang value with the variables and the queries of the player.
	 */
	public function molang(mixed $value) : float{
		$player = $this->player;
		return Molang::evaluate($value, $this->variables, function(string $query, array $args) use ($player) : bool|int|float|string|null{
			$location = $player->getLocation();
			return match($query){
				"property", "actor_property" => $this->queryProperty($args[0] ?? null),
				"has_property" => is_string($args[0] ?? null) && isset($this->propertyValues[$args[0]]),
				"health" => $player->getHealth(),
				"max_health" => $player->getMaxHealth(),
				"is_alive" => $player->isAlive(),
				"is_on_ground" => $player->isOnGround(),
				"is_in_water" => $this->isInWater(),
				"is_in_water_or_rain" => $this->isInWater(),
				"is_on_fire" => $player->isOnFire(),
				"is_sneaking" => $player->isSneaking(),
				"is_sprinting" => $player->isSprinting(),
				"is_swimming" => $player->isSwimming(),
				"is_gliding" => $player->isGliding(),
				"is_flying" => $player->isFlying(),
				"is_moving" => $this->moving,
				"is_riding" => $player->isRiding(),
				"target_x_rotation" => $location->pitch,
				"target_y_rotation", "body_y_rotation", "head_y_rotation" => $location->yaw,
				"player_level" => $player->getXpManager()->getXpLevel(),
				"scale" => $player->getScale(),
				"position" => match((int) ($args[0] ?? 0)){
					1 => $location->y,
					2 => $location->z,
					default => $location->x
				},
				"time_of_day" => ($player->getWorld()->getTimeOfDay() % 24000) / 24000,
				"day" => (int) ($player->getWorld()->getTime() / 24000),
				default => 0.0
			};
		});
	}

	private function isInWater() : bool{
		$location = $this->player->getLocation();
		return $this->player->isUnderwater() || $this->player->getWorld()->getBlockAt(
			(int) floor($location->x),
			(int) floor($location->y + 0.3),
			(int) floor($location->z)
		) instanceof Water;
	}

	public function getAnimationDefinition() : array{
		return $this->definition();
	}

	public function evaluateMolang(mixed $value) : float{
		return $this->molang($value);
	}

	public function triggerAnimationEvent(string $event) : void{
		$this->triggerEvent($event);
	}

	public function getMolangVariables() : array{
		return $this->variables;
	}

	public function setMolangVariable(string $name, float $value) : void{
		$this->variables[$name] = $value;
	}

	public function isAnimationInitialized() : bool{
		return $this->initialized;
	}

	public function markAnimationInitialized() : void{
		$this->initialized = true;
	}

	public function runAnimationCommand(string $command) : void{
		$command = PlayerSelector::apply($this->player, ltrim($command, "/ "));
		if($command === null){
			return;
		}
		$server = $this->player->getServer();
		self::$commandSender ??= new ScriptCommandSender($server, $server->getLanguage());
		$server->dispatchCommand(self::$commandSender, $command);
	}

	public function isAnimationHostClosed() : bool{
		return !$this->player->isConnected() || $this->player->isClosed();
	}
}
