<?php

declare(strict_types=1);

namespace behaviorpack\script;

use behaviorpack\entity\BehaviorEntity;
use pocketmine\block\Block;
use pocketmine\block\Liquid;
use pocketmine\block\tile\Container as ContainerTile;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Human;
use pocketmine\entity\Living;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\Inventory;
use pocketmine\item\Durable;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Explosion;
use pocketmine\world\Position;
use pocketmine\world\World;
use function abs;
use function array_map;
use function array_reverse;
use function array_slice;
use function count;
use function in_array;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function ltrim;
use function max;
use function min;
use function str_replace;
use function strtolower;
use function usort;

/**
 * Serves the requests of the script runtime. Each method name maps to one
 * operation on the server; see ScriptValues for the value encoding.
 */
final class ScriptApi{

	private const DAMAGE_CAUSES = [
		"contact" => EntityDamageEvent::CAUSE_CONTACT,
		"entityAttack" => EntityDamageEvent::CAUSE_ENTITY_ATTACK,
		"projectile" => EntityDamageEvent::CAUSE_PROJECTILE,
		"suffocation" => EntityDamageEvent::CAUSE_SUFFOCATION,
		"fall" => EntityDamageEvent::CAUSE_FALL,
		"fire" => EntityDamageEvent::CAUSE_FIRE,
		"fireTick" => EntityDamageEvent::CAUSE_FIRE_TICK,
		"lava" => EntityDamageEvent::CAUSE_LAVA,
		"drowning" => EntityDamageEvent::CAUSE_DROWNING,
		"blockExplosion" => EntityDamageEvent::CAUSE_BLOCK_EXPLOSION,
		"entityExplosion" => EntityDamageEvent::CAUSE_ENTITY_EXPLOSION,
		"selfDestruct" => EntityDamageEvent::CAUSE_SUICIDE,
		"magic" => EntityDamageEvent::CAUSE_MAGIC,
		"none" => EntityDamageEvent::CAUSE_CUSTOM,
		"void" => EntityDamageEvent::CAUSE_VOID,
		"starve" => EntityDamageEvent::CAUSE_STARVATION,
		"fallingBlock" => EntityDamageEvent::CAUSE_FALLING_BLOCK
	];

	private const EQUIPMENT_SLOTS = [
		"Head" => ArmorInventory::SLOT_HEAD,
		"Chest" => ArmorInventory::SLOT_CHEST,
		"Legs" => ArmorInventory::SLOT_LEGS,
		"Feet" => ArmorInventory::SLOT_FEET
	];

	private Server $server;

	private ?ScriptCommandSender $commandSender = null;

	public function __construct(
		private ScriptLoader $loader,
		private ScriptValues $values,
		private ScriptStorage $storage
	){
		$this->server = $values->getServer();
	}

	public static function damageCauseName(int $cause) : string{
		foreach(self::DAMAGE_CAUSES as $name => $id){
			if($id === $cause){
				return $name;
			}
		}
		return "none";
	}

	/**
	 * @param list<mixed> $a
	 */
	public function handle(string $method, array $a) : mixed{
		return match($method){
			"world.players" => $this->queryPlayers($a[0] ?? []),
			"world.entity" => $this->findEntity($a[0] ?? null),
			"world.message" => $this->server->broadcastMessage($this->string($a[0] ?? "")) >= 0,
			"world.time" => $this->defaultWorld()->getTime(),
			"world.setTime" => $this->defaultWorld()->setTime($this->int($a[0] ?? 0)),
			"world.spawn" => $this->values->vectorOut($this->defaultWorld()->getSpawnLocation()),
			"world.setSpawn" => $this->defaultWorld()->setSpawnLocation($this->values->vector($a[0] ?? null)),
			"world.sound" => $this->playSound($this->defaultWorld()->getPlayers(), $a[0] ?? "", $this->values->vector($a[1] ?? null), $a[2] ?? []),
			"dp.get" => $this->storage->get($this->scope($a[0] ?? null), $this->string($a[1] ?? "")),
			"dp.set" => $this->storage->set($this->scope($a[0] ?? null), $this->string($a[1] ?? ""), $a[2] ?? null),
			"dp.ids" => $this->storage->ids($this->scope($a[0] ?? null)),
			"dp.clear" => $this->storage->clear($this->scope($a[0] ?? null)),
			"sb.save" => $this->storage->setScoreboard($a[0] ?? null),
			"dim.block" => $this->getBlock($a[0] ?? null, $a[1] ?? null),
			"dim.setBlock" => $this->setBlock($a[0] ?? null, $a[1] ?? null, $a[2] ?? null),
			"dim.entities" => $this->queryDimension($a[0] ?? null, $a[1] ?? []),
			"dim.spawn" => $this->spawnEntity($a[0] ?? null, $a[1] ?? null, $a[2] ?? null),
			"dim.item" => $this->spawnItem($a[0] ?? null, $a[1] ?? null, $a[2] ?? null),
			"dim.command" => $this->runCommand($this->string($a[1] ?? ""), null),
			"dim.sound" => $this->playSound($this->values->world($a[0] ?? null)->getPlayers(), $a[1] ?? "", $this->values->vector($a[2] ?? null), $a[3] ?? []),
			"dim.explode" => $this->explode($a[0] ?? null, $a[1] ?? null, $a[2] ?? 1, $a[3] ?? []),
			"dim.top" => $this->topBlock($a[0] ?? null, $a[1] ?? 0, $a[2] ?? 0),
			"block.get" => $this->blockInfo($a[0] ?? null, $a[1] ?? null),
			"block.resolve" => $this->values->permutation($this->values->resolveBlock(ScriptValues::namespaced($this->string($a[0] ?? "")), is_array($a[1] ?? null) ? $a[1] : [])),
			"block.item" => $this->blockItem($a[0] ?? null, $a[1] ?? 1),
			"item.info" => $this->itemInfo($this->string($a[0] ?? "")),
			"ent.valid" => $this->values->findEntity($a[0] ?? null) !== null,
			"ent.get" => $this->getEntityField($this->values->entity($a[0] ?? null), $this->string($a[1] ?? "")),
			"ent.set" => $this->setEntityField($this->values->entity($a[0] ?? null), $this->string($a[1] ?? ""), $a[2] ?? null),
			"ent.teleport" => $this->teleport($this->values->entity($a[0] ?? null), $a[1] ?? null, $a[2] ?? []),
			"ent.kill" => $this->kill($this->values->entity($a[0] ?? null)),
			"ent.trigger" => $this->triggerEvent($this->values->entity($a[0] ?? null), $this->string($a[1] ?? "")),
			"ent.remove" => $this->remove($this->values->entity($a[0] ?? null)),
			"ent.damage" => $this->damage($this->values->entity($a[0] ?? null), $a[1] ?? 0, $a[2] ?? []),
			"ent.impulse" => $this->impulse($this->values->entity($a[0] ?? null), $this->values->vector($a[1] ?? null)),
			"ent.setVelocity" => $this->values->entity($a[0] ?? null)->setMotion($this->values->vector($a[1] ?? null)),
			"ent.effect.add" => $this->addEffect($this->living($a[0] ?? null), $a[1] ?? null, $a[2] ?? 0, $a[3] ?? 0, $a[4] ?? true),
			"ent.effect.remove" => $this->removeEffect($this->living($a[0] ?? null), $a[1] ?? null),
			"ent.effect.get" => $this->getEffect($this->living($a[0] ?? null), $a[1] ?? null),
			"ent.effects" => $this->getEffects($this->living($a[0] ?? null)),
			"ent.tags" => $this->storage->getTags($this->values->scope($this->values->entity($a[0] ?? null))),
			"ent.addTag" => $this->storage->addTag($this->values->scope($this->values->entity($a[0] ?? null)), $this->string($a[1] ?? "")),
			"ent.removeTag" => $this->storage->removeTag($this->values->scope($this->values->entity($a[0] ?? null)), $this->string($a[1] ?? "")),
			"ent.comp" => $this->hasComponent($this->values->entity($a[0] ?? null), $this->string($a[1] ?? "")),
			"ent.health" => $this->health($this->values->entity($a[0] ?? null)),
			"ent.setHealth" => $this->setHealth($this->values->entity($a[0] ?? null), $a[1] ?? 0),
			"ent.equip.get" => $this->getEquipment($this->values->entity($a[0] ?? null), $this->string($a[1] ?? "")),
			"ent.equip.set" => $this->setEquipment($this->values->entity($a[0] ?? null), $this->string($a[1] ?? ""), $a[2] ?? null),
			"ent.command" => $this->runCommand($this->string($a[1] ?? ""), $this->values->entity($a[0] ?? null)),
			"ent.fire" => $this->setOnFire($this->values->entity($a[0] ?? null), $a[1] ?? 0),
			"ent.extinguish" => $this->values->entity($a[0] ?? null)->extinguish(),
			"pl.message" => $this->values->player($a[0] ?? null)->sendMessage($this->string($a[1] ?? "")),
			"pl.sound" => $this->playPlayerSound($this->values->player($a[0] ?? null), $a[1] ?? "", $a[2] ?? []),
			"pl.title" => $this->sendTitle($this->values->player($a[0] ?? null), $this->string($a[1] ?? ""), $a[2] ?? []),
			"pl.actionbar" => $this->values->player($a[0] ?? null)->sendActionBarMessage($this->string($a[1] ?? "")),
			"pl.gamemode" => self::gameModeName($this->values->player($a[0] ?? null)->getGamemode()),
			"pl.setGamemode" => $this->setGameMode($this->values->player($a[0] ?? null), $this->string($a[1] ?? "")),
			"pl.xp" => $this->addExperience($this->values->player($a[0] ?? null), $this->int($a[1] ?? 0)),
			"pl.levels" => $this->addLevels($this->values->player($a[0] ?? null), $this->int($a[1] ?? 0)),
			"pl.xpInfo" => $this->experience($this->values->player($a[0] ?? null)),
			"pl.spawnPoint" => $this->spawnPoint($this->values->player($a[0] ?? null)),
			"pl.setSpawnPoint" => $this->setSpawnPoint($this->values->player($a[0] ?? null), $a[1] ?? null),
			"pl.form" => $this->sendForm($this->values->player($a[0] ?? null), $this->int($a[1] ?? 0), $a[2] ?? null),
			"pl.selectedSlot" => $this->values->player($a[0] ?? null)->getInventory()->setHeldItemIndex(max(0, min(8, $this->int($a[1] ?? 0)))),
			"cont.valid" => $this->findInventory($a[0] ?? null) !== null,
			"cont.size" => $this->inventory($a[0] ?? null)->getSize(),
			"cont.empty" => $this->emptySlots($this->inventory($a[0] ?? null)),
			"cont.get" => $this->values->item($this->inventory($a[0] ?? null)->getItem($this->slot($a[0] ?? null, $a[1] ?? 0))),
			"cont.set" => $this->inventory($a[0] ?? null)->setItem($this->slot($a[0] ?? null, $a[1] ?? 0), $this->values->decodeItem($a[2] ?? null)),
			"cont.add" => $this->addItem($this->inventory($a[0] ?? null), $a[1] ?? null),
			"cont.clear" => $this->inventory($a[0] ?? null)->clearAll(),
			default => throw new ScriptException("Unknown request " . $method)
		};
	}

	public static function gameModeName(GameMode $mode) : string{
		return match($mode){
			GameMode::SURVIVAL => "Survival",
			GameMode::CREATIVE => "Creative",
			GameMode::ADVENTURE => "Adventure",
			GameMode::SPECTATOR => "Spectator"
		};
	}

	private function defaultWorld() : World{
		$world = $this->server->getWorldManager()->getDefaultWorld();
		if($world === null){
			throw new ScriptException("No world is loaded");
		}
		return $world;
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

	private function int(mixed $value) : int{
		if(!is_numeric($value)){
			throw new ScriptException("Expected a number");
		}
		return (int) $value;
	}

	private function float(mixed $value) : float{
		if(!is_numeric($value)){
			throw new ScriptException("Expected a number");
		}
		return (float) $value;
	}

	private function scope(mixed $owner) : string{
		if($owner === "world"){
			return "world";
		}
		return $this->values->scope($this->values->entity($owner));
	}

	private function living(mixed $handle) : Living{
		$entity = $this->values->entity($handle);
		if(!$entity instanceof Living){
			throw new ScriptException("The entity has no effects");
		}
		return $entity;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function findEntity(mixed $id) : ?array{
		$entity = $this->values->findEntity($id);
		return $entity === null ? null : $this->values->entityRef($entity);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function queryPlayers(mixed $options) : array{
		$players = [];
		foreach($this->server->getOnlinePlayers() as $player){
			if($player->isConnected()){
				$players[] = $player;
			}
		}
		return $this->query($players, is_array($options) ? $options : []);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function queryDimension(mixed $dimension, mixed $options) : array{
		$world = $this->values->world($dimension);
		$options = is_array($options) ? $options : [];
		$entities = [];
		if(($options["_players"] ?? false) === true){
			foreach($world->getPlayers() as $player){
				if($player->isConnected()){
					$entities[] = $player;
				}
			}
		}else{
			foreach($world->getEntities() as $entity){
				if(!$entity->isClosed() && !$entity->isFlaggedForDespawn() && (!$entity instanceof Player || $entity->isConnected())){
					$entities[] = $entity;
				}
			}
		}
		return $this->query($entities, $options);
	}

	/**
	 * Applies the common EntityQueryOptions to a list of entities.
	 *
	 * @param list<Entity>         $entities
	 * @param array<string, mixed> $options
	 * @return list<array<string, mixed>>
	 */
	private function query(array $entities, array $options) : array{
		$location = isset($options["location"]) ? $this->values->vector($options["location"]) : null;
		$block = isset($options["_block"]) ? $this->values->vector($options["_block"])->floor() : null;
		$type = is_string($options["type"] ?? null) ? ScriptValues::namespaced($options["type"]) : null;
		$excludeTypes = array_map(fn($t) => ScriptValues::namespaced((string) $t), is_array($options["excludeTypes"] ?? null) ? $options["excludeTypes"] : []);
		$name = is_string($options["name"] ?? null) ? $options["name"] : null;
		$excludeNames = is_array($options["excludeNames"] ?? null) ? $options["excludeNames"] : [];
		$tags = is_array($options["tags"] ?? null) ? $options["tags"] : [];
		$excludeTags = is_array($options["excludeTags"] ?? null) ? $options["excludeTags"] : [];
		$maxDistance = is_numeric($options["maxDistance"] ?? null) ? (float) $options["maxDistance"] : null;
		$minDistance = is_numeric($options["minDistance"] ?? null) ? (float) $options["minDistance"] : null;
		$gameMode = is_string($options["gameMode"] ?? null) ? strtolower($options["gameMode"]) : null;
		$excludeGameModes = array_map(fn($m) => strtolower((string) $m), is_array($options["excludeGameModes"] ?? null) ? $options["excludeGameModes"] : []);

		$matches = [];
		foreach($entities as $entity){
			$typeId = $this->values->entityTypeId($entity);
			if($type !== null && $typeId !== $type){
				continue;
			}
			if(in_array($typeId, $excludeTypes, true)){
				continue;
			}
			$entityName = $entity instanceof Player ? $entity->getName() : $entity->getNameTag();
			if($name !== null && $entityName !== $name){
				continue;
			}
			if(in_array($entityName, $excludeNames, true)){
				continue;
			}
			if(count($tags) > 0 || count($excludeTags) > 0){
				$entityTags = $this->storage->getTags($this->values->scope($entity));
				foreach($tags as $tag){
					if(!in_array($tag, $entityTags, true)){
						continue 2;
					}
				}
				foreach($excludeTags as $tag){
					if(in_array($tag, $entityTags, true)){
						continue 2;
					}
				}
			}
			if($gameMode !== null || count($excludeGameModes) > 0){
				if(!$entity instanceof Player){
					continue;
				}
				$mode = strtolower(self::gameModeName($entity->getGamemode()));
				if(($gameMode !== null && $mode !== $gameMode) || in_array($mode, $excludeGameModes, true)){
					continue;
				}
			}
			$position = $entity->getPosition();
			if($block !== null && !$position->floor()->equals($block)){
				continue;
			}
			if($location !== null){
				$distance = $position->distance($location);
				if(($maxDistance !== null && $distance > $maxDistance) || ($minDistance !== null && $distance < $minDistance)){
					continue;
				}
			}
			$matches[] = $entity;
		}

		$closest = is_numeric($options["closest"] ?? null) ? (int) $options["closest"] : null;
		$farthest = is_numeric($options["farthest"] ?? null) ? (int) $options["farthest"] : null;
		if($location !== null && ($closest !== null || $farthest !== null)){
			usort($matches, fn(Entity $x, Entity $y) : int => $x->getPosition()->distanceSquared($location) <=> $y->getPosition()->distanceSquared($location));
			if($farthest !== null){
				$matches = array_reverse($matches);
			}
			$matches = array_slice($matches, 0, max(0, $closest ?? $farthest ?? 0));
		}

		$result = [];
		foreach($matches as $entity){
			$result[] = $this->values->entityRef($entity);
		}
		return $result;
	}

	private function loadedBlock(mixed $dimension, mixed $location) : ?Block{
		$world = $this->values->world($dimension);
		$position = $this->values->vector($location)->floor();
		$x = (int) $position->x;
		$y = (int) $position->y;
		$z = (int) $position->z;
		if(!$world->isInWorld($x, $y, $z) || !$world->isChunkLoaded($x >> 4, $z >> 4)){
			return null;
		}
		return $world->getBlockAt($x, $y, $z);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function getBlock(mixed $dimension, mixed $location) : ?array{
		$block = $this->loadedBlock($dimension, $location);
		return $block === null ? null : $this->values->blockRef($block);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function blockInfo(mixed $dimension, mixed $location) : ?array{
		$block = $this->loadedBlock($dimension, $location);
		if($block === null){
			return null;
		}
		$tile = $block->getPosition()->getWorld()->getTile($block->getPosition());
		return [
			"p" => $this->values->permutation($block),
			"l" => $block instanceof Liquid,
			"s" => $block->isSolid(),
			"i" => $tile instanceof ContainerTile
		];
	}

	private function setBlock(mixed $dimension, mixed $location, mixed $permutation) : bool{
		$world = $this->values->world($dimension);
		$position = $this->values->vector($location)->floor();
		if(!$world->isInWorld((int) $position->x, (int) $position->y, (int) $position->z) || !$world->isChunkLoaded(((int) $position->x) >> 4, ((int) $position->z) >> 4)){
			throw new ScriptException("The location is not in a loaded chunk");
		}
		$world->setBlock($position, $this->values->block($permutation));
		return true;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function topBlock(mixed $dimension, mixed $x, mixed $z) : ?array{
		$world = $this->values->world($dimension);
		$x = (int) $this->float($x);
		$z = (int) $this->float($z);
		if(!$world->isChunkLoaded($x >> 4, $z >> 4)){
			return null;
		}
		$y = $world->getHighestBlockAt($x, $z);
		return $y === null ? null : $this->values->blockRef($world->getBlockAt($x, $y, $z));
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function blockItem(mixed $permutation, mixed $amount) : ?array{
		$item = $this->values->block($permutation)->asItem();
		$item->setCount(max(1, $this->int($amount)));
		return $this->values->item($item);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function itemInfo(string $typeId) : array{
		$item = StringToItemParser::getInstance()->parse(ScriptValues::namespaced($typeId));
		if($item === null || $item->isNull()){
			throw new ScriptException("Unknown item type: " . $typeId);
		}
		return [
			"t" => $this->values->itemTypeId($item),
			"m" => $item->getMaxStackSize(),
			"md" => $item instanceof Durable ? $item->getMaxDurability() : 0
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function spawnEntity(mixed $dimension, mixed $typeId, mixed $location) : array{
		$world = $this->values->world($dimension);
		$typeId = ScriptValues::namespaced($this->string($typeId));
		if($typeId === "minecraft:player"){
			throw new ScriptException("Players cannot be spawned");
		}
		$position = $this->values->vector($location);
		$nbt = CompoundTag::create()
			->setString(EntityFactory::TAG_IDENTIFIER, $typeId)
			->setTag(Entity::TAG_POS, new ListTag([new DoubleTag($position->x), new DoubleTag($position->y), new DoubleTag($position->z)]))
			->setTag(Entity::TAG_ROTATION, new ListTag([new FloatTag(0.0), new FloatTag(0.0)]));
		$entity = EntityFactory::getInstance()->createFromData($world, $nbt);
		if($entity === null){
			throw new ScriptException("Unknown entity type: " . $typeId);
		}
		$entity->spawnToAll();
		return $this->values->entityRef($entity);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function spawnItem(mixed $dimension, mixed $item, mixed $location) : ?array{
		$entity = $this->values->world($dimension)->dropItem($this->values->vector($location), $this->values->decodeItem($item), new Vector3(0, 0, 0), 10);
		return $entity === null ? null : $this->values->entityRef($entity);
	}

	private function runCommand(string $command, ?Entity $source) : int{
		$command = ltrim($command, "/ ");
		if($source instanceof Player){
			$command = str_replace("@s", "\"" . $source->getName() . "\"", $command);
		}
		if($this->commandSender === null){
			$this->commandSender = new ScriptCommandSender($this->server, $this->server->getLanguage());
		}
		return $this->server->dispatchCommand($this->commandSender, $command) ? 1 : 0;
	}

	/**
	 * @param list<Player> $players
	 */
	private function playSound(array $players, mixed $soundId, Vector3 $location, mixed $options) : bool{
		$options = is_array($options) ? $options : [];
		$packet = PlaySoundPacket::create(
			$this->string($soundId),
			$location->x,
			$location->y,
			$location->z,
			is_numeric($options["volume"] ?? null) ? (float) $options["volume"] : 1.0,
			is_numeric($options["pitch"] ?? null) ? (float) $options["pitch"] : 1.0,
			0,
			false,
			null,
			null
		);
		foreach($players as $player){
			if($player->isConnected()){
				$player->getNetworkSession()->sendDataPacket($packet);
			}
		}
		return true;
	}

	private function playPlayerSound(Player $player, mixed $soundId, mixed $options) : bool{
		$options = is_array($options) ? $options : [];
		$location = isset($options["location"]) ? $this->values->vector($options["location"]) : $player->getPosition();
		return $this->playSound([$player], $soundId, $location, $options);
	}

	private function explode(mixed $dimension, mixed $location, mixed $radius, mixed $options) : bool{
		$world = $this->values->world($dimension);
		$vector = $this->values->vector($location);
		$options = is_array($options) ? $options : [];
		$source = isset($options["source"]) ? $this->values->findEntity($options["source"]) : null;
		$explosion = new Explosion(Position::fromObject($vector, $world), max(0.1, $this->float($radius)), $source);
		if(($options["causesFire"] ?? false) === true){
			$explosion->setFireChance(1 / 3);
		}
		if(($options["breaksBlocks"] ?? true) !== false && !$explosion->explodeA()){
			return false;
		}
		return $explosion->explodeB();
	}

	private function getEntityField(Entity $entity, string $field) : mixed{
		$location = $entity->getLocation();
		return match($field){
			"location" => $this->values->vectorOut($location),
			"rotation" => ["x" => $location->getPitch(), "y" => $location->getYaw()],
			"dimension" => $this->values->dimensionId($entity->getWorld()),
			"nameTag" => $entity->getNameTag(),
			"velocity" => $this->values->vectorOut($entity->getMotion()),
			"head" => $this->values->vectorOut($entity->getEyePos()),
			"view" => $this->values->vectorOut($entity->getDirectionVector()),
			"onGround" => $entity->isOnGround(),
			"onFire" => $entity->isOnFire(),
			"sneaking" => $entity instanceof Living && $entity->isSneaking(),
			"sprinting" => $entity instanceof Living && $entity->isSprinting(),
			"swimming" => $entity instanceof Living && $entity->isSwimming(),
			"gliding" => $entity instanceof Living && $entity->isGliding(),
			"underwater" => $entity instanceof Living && $entity->isUnderwater(),
			"flying" => $entity instanceof Player && $entity->isFlying(),
			"selectedSlot" => $entity instanceof Human ? $entity->getInventory()->getHeldItemIndex() : 0,
			"op" => $entity instanceof Player && $this->server->isOp($entity->getName()),
			default => throw new ScriptException("Unknown entity property " . $field)
		};
	}

	private function setEntityField(Entity $entity, string $field, mixed $value) : bool{
		if($field === "nameTag"){
			$entity->setNameTag($this->string($value));
			return true;
		}
		if($field === "sneaking" && $entity instanceof Living){
			$entity->setSneaking($value === true);
			return true;
		}
		throw new ScriptException("Cannot set entity property " . $field);
	}

	private function teleport(Entity $entity, mixed $location, mixed $options) : bool{
		$options = is_array($options) ? $options : [];
		$world = isset($options["dimension"]) ? $this->values->world($options["dimension"]) : $entity->getWorld();
		$vector = $this->values->vector($location);
		$yaw = null;
		$pitch = null;
		if(is_array($options["rotation"] ?? null)){
			$pitch = is_numeric($options["rotation"]["x"] ?? null) ? (float) $options["rotation"]["x"] : null;
			$yaw = is_numeric($options["rotation"]["y"] ?? null) ? (float) $options["rotation"]["y"] : null;
		}
		$velocity = $entity->getMotion();
		$result = $entity->teleport(Position::fromObject($vector, $world), $yaw, $pitch);
		if($result && isset($options["facingLocation"])){
			if($entity instanceof Living){
				$entity->lookAt($this->values->vector($options["facingLocation"]));
			}
		}
		if($result && ($options["keepVelocity"] ?? false) === true){
			$entity->setMotion($velocity);
		}
		return $result;
	}

	private function impulse(Entity $entity, Vector3 $impulse) : bool{
		$entity->addMotion($impulse->x, $impulse->y, $impulse->z);
		return true;
	}

	private function triggerEvent(Entity $entity, string $event) : bool{
		return $entity instanceof BehaviorEntity && $entity->triggerEvent($event);
	}

	private function kill(Entity $entity) : bool{
		if(!$entity->isAlive()){
			return false;
		}
		$entity->kill();
		return true;
	}

	private function remove(Entity $entity) : bool{
		if($entity instanceof Player){
			throw new ScriptException("Players cannot be removed");
		}
		$entity->flagForDespawn();
		return true;
	}

	private function damage(Entity $entity, mixed $amount, mixed $options) : bool{
		$options = is_array($options) ? $options : [];
		$cause = self::DAMAGE_CAUSES[is_string($options["cause"] ?? null) ? $options["cause"] : "none"] ?? EntityDamageEvent::CAUSE_CUSTOM;
		$damager = isset($options["damagingEntity"]) ? $this->values->findEntity($options["damagingEntity"]) : null;
		if($damager !== null){
			$event = new EntityDamageByEntityEvent($damager, $entity, $cause === EntityDamageEvent::CAUSE_CUSTOM ? EntityDamageEvent::CAUSE_ENTITY_ATTACK : $cause, $this->float($amount));
		}else{
			$event = new EntityDamageEvent($entity, $cause, $this->float($amount));
		}
		$entity->attack($event);
		return !$event->isCancelled();
	}

	private function addEffect(Living $entity, mixed $type, mixed $duration, mixed $amplifier, mixed $particles) : bool{
		$effect = $this->values->effect($type);
		$instance = new EffectInstance($effect, max(1, $this->int($duration)), max(0, min(255, $this->int($amplifier))), $particles !== false);
		return $entity->getEffects()->add($instance);
	}

	private function removeEffect(Living $entity, mixed $type) : bool{
		$effect = $this->values->effect($type);
		if(!$entity->getEffects()->has($effect)){
			return false;
		}
		$entity->getEffects()->remove($effect);
		return true;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function getEffect(Living $entity, mixed $type) : ?array{
		$instance = $entity->getEffects()->get($this->values->effect($type));
		return $instance === null ? null : $this->effectData($instance);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function getEffects(Living $entity) : array{
		$effects = [];
		foreach($entity->getEffects()->all() as $instance){
			$effects[] = $this->effectData($instance);
		}
		return $effects;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function effectData(EffectInstance $instance) : array{
		$name = $instance->getType()->getName();
		return [
			"t" => $this->values->effectName($instance->getType()),
			"d" => $instance->getDuration(),
			"a" => $instance->getAmplifier(),
			"n" => is_string($name) ? $name : $name->getText()
		];
	}

	private function hasComponent(Entity $entity, string $name) : bool{
		return match(ScriptValues::namespaced($name)){
			"minecraft:health" => $entity instanceof Living,
			"minecraft:inventory" => $entity instanceof Human,
			"minecraft:equippable" => $entity instanceof Living,
			"minecraft:onfire" => $entity->isOnFire(),
			default => false
		};
	}

	/**
	 * @return array<string, float>
	 */
	private function health(Entity $entity) : array{
		return ["c" => $entity->getHealth(), "m" => (float) $entity->getMaxHealth()];
	}

	private function setHealth(Entity $entity, mixed $value) : bool{
		$health = $this->float($value);
		if($health <= 0){
			$entity->kill();
			return true;
		}
		$entity->setHealth(min($health, (float) $entity->getMaxHealth()));
		return true;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function getEquipment(Entity $entity, string $slot) : ?array{
		if(!$entity instanceof Living){
			throw new ScriptException("The entity has no equipment");
		}
		if(isset(self::EQUIPMENT_SLOTS[$slot])){
			return $this->values->item($entity->getArmorInventory()->getItem(self::EQUIPMENT_SLOTS[$slot]));
		}
		if(!$entity instanceof Human){
			return null;
		}
		return match($slot){
			"Mainhand" => $this->values->item($entity->getInventory()->getItemInHand()),
			"Offhand" => $this->values->item($entity->getOffHandInventory()->getItem(0)),
			default => throw new ScriptException("Unknown equipment slot " . $slot)
		};
	}

	private function setEquipment(Entity $entity, string $slot, mixed $item) : bool{
		if(!$entity instanceof Living){
			throw new ScriptException("The entity has no equipment");
		}
		$decoded = $this->values->decodeItem($item);
		if(isset(self::EQUIPMENT_SLOTS[$slot])){
			$entity->getArmorInventory()->setItem(self::EQUIPMENT_SLOTS[$slot], $decoded);
			return true;
		}
		if(!$entity instanceof Human){
			return false;
		}
		match($slot){
			"Mainhand" => $entity->getInventory()->setItemInHand($decoded),
			"Offhand" => $entity->getOffHandInventory()->setItem(0, $decoded),
			default => throw new ScriptException("Unknown equipment slot " . $slot)
		};
		return true;
	}

	private function setOnFire(Entity $entity, mixed $seconds) : bool{
		$duration = $this->int($seconds);
		if($duration <= 0){
			$entity->extinguish();
			return false;
		}
		$entity->setOnFire($duration);
		return true;
	}

	/**
	 * @param array<string, mixed>|mixed $options
	 */
	private function sendTitle(Player $player, string $title, mixed $options) : bool{
		$options = is_array($options) ? $options : [];
		$player->sendTitle(
			$title,
			is_string($options["subtitle"] ?? null) ? $options["subtitle"] : "",
			is_numeric($options["fadeInDuration"] ?? null) ? (int) $options["fadeInDuration"] : -1,
			is_numeric($options["stayDuration"] ?? null) ? (int) $options["stayDuration"] : -1,
			is_numeric($options["fadeOutDuration"] ?? null) ? (int) $options["fadeOutDuration"] : -1
		);
		return true;
	}

	private function setGameMode(Player $player, string $mode) : bool{
		$gameMode = GameMode::fromString(strtolower($mode));
		if($gameMode === null){
			throw new ScriptException("Unknown game mode " . $mode);
		}
		return $player->setGamemode($gameMode);
	}

	private function addExperience(Player $player, int $amount) : int{
		$manager = $player->getXpManager();
		if($amount >= 0){
			$manager->addXp($amount);
		}else{
			$manager->subtractXp(min(abs($amount), $manager->getCurrentTotalXp()));
		}
		return $manager->getCurrentTotalXp();
	}

	private function addLevels(Player $player, int $amount) : int{
		$manager = $player->getXpManager();
		if($amount >= 0){
			$manager->addXpLevels($amount);
		}else{
			$manager->subtractXpLevels(min(abs($amount), $manager->getXpLevel()));
		}
		return $manager->getXpLevel();
	}

	/**
	 * @return array<string, int|float>
	 */
	private function experience(Player $player) : array{
		$manager = $player->getXpManager();
		return [
			"level" => $manager->getXpLevel(),
			"total" => $manager->getCurrentTotalXp(),
			"remainder" => $manager->getRemainderXp(),
			"progress" => $manager->getXpProgress()
		];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function spawnPoint(Player $player) : ?array{
		$spawn = $player->getSpawn();
		if(!$spawn instanceof Position || !$spawn->isValid()){
			return null;
		}
		$data = $this->values->vectorOut($spawn);
		$data["dimension"] = ["\$d" => $this->values->dimensionId($spawn->getWorld())];
		return $data;
	}

	private function setSpawnPoint(Player $player, mixed $point) : bool{
		if(!is_array($point)){
			$player->setSpawn(null);
			return true;
		}
		$world = isset($point["dimension"]) ? $this->values->world($point["dimension"]) : $player->getWorld();
		$player->setSpawn(Position::fromObject($this->values->vector($point), $world));
		return true;
	}

	private function sendForm(Player $player, int $formId, mixed $data) : bool{
		if(!is_array($data)){
			throw new ScriptException("Invalid form");
		}
		$player->sendForm(new ScriptForm($data, function(Player $player, mixed $response) use ($formId) : void{
			$this->loader->queueEvent("__form", ["id" => $formId, "r" => $response]);
		}));
		return true;
	}

	private function findInventory(mixed $ref) : ?Inventory{
		if(is_array($ref) && is_array($ref["\$c"] ?? null)){
			$ref = $ref["\$c"];
		}
		if(!is_array($ref)){
			return null;
		}
		if(isset($ref["e"])){
			$entity = $this->values->findEntity($ref["e"]);
			return $entity instanceof Human ? $entity->getInventory() : null;
		}
		if(is_array($ref["b"] ?? null) && count($ref["b"]) === 4){
			[$dimension, $x, $y, $z] = $ref["b"];
			$block = $this->loadedBlock($dimension, ["x" => $x, "y" => $y, "z" => $z]);
			if($block === null){
				return null;
			}
			$tile = $block->getPosition()->getWorld()->getTile($block->getPosition());
			return $tile instanceof ContainerTile ? $tile->getInventory() : null;
		}
		return null;
	}

	private function inventory(mixed $ref) : Inventory{
		$inventory = $this->findInventory($ref);
		if($inventory === null){
			throw new ScriptException("The container is no longer valid");
		}
		return $inventory;
	}

	private function slot(mixed $ref, mixed $slot) : int{
		$index = $this->int($slot);
		if($index < 0 || $index >= $this->inventory($ref)->getSize()){
			throw new ScriptException("Slot " . $index . " is out of bounds");
		}
		return $index;
	}

	private function emptySlots(Inventory $inventory) : int{
		$count = 0;
		for($slot = 0, $size = $inventory->getSize(); $slot < $size; ++$slot){
			if($inventory->isSlotEmpty($slot)){
				++$count;
			}
		}
		return $count;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function addItem(Inventory $inventory, mixed $item) : ?array{
		$leftovers = $inventory->addItem($this->values->decodeItem($item));
		foreach($leftovers as $leftover){
			if(!$leftover->isNull()){
				return $this->values->item($leftover);
			}
		}
		return null;
	}
}
