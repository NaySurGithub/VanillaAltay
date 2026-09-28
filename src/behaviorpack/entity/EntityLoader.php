<?php

declare(strict_types=1);

namespace behaviorpack\entity;

use behaviorpack\BehaviorPack;
use behaviorpack\BehaviorPackException;
use behaviorpack\ContentLoader;
use behaviorpack\entity\player\PlayerBehaviorManager;
use behaviorpack\entity\player\PlayerDefinition;
use customiesdevs\customies\entity\CustomiesEntityFactory;
use customiesdevs\customies\item\CreativeInventoryInfo;
use customiesdevs\customies\item\CustomiesItemFactory;
use pocketmine\entity\EntityFactory;
use pocketmine\event\EventPriority;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\item\ItemIdentifier;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\item\ItemTypeIds;
use pocketmine\plugin\PluginBase;
use Throwable;
use function class_exists;
use function is_array;
use function is_string;
use function md5;
use function preg_match;
use function str_starts_with;
use function strtolower;
use function var_export;

/**
 * Registers the custom entities ("minecraft:entity" files in entities/) of
 * the behavior packs through Customies.
 */
final class EntityLoader implements ContentLoader{

	private const GENERATED_NAMESPACE = "behaviorpack\\entity\\generated";

	/** @var array<string, class-string<BehaviorEntity>> */
	private array $classes = [];

	public function __construct(
		private PluginBase $plugin
	){
	}

	public function getName() : string{
		return "custom entities";
	}

	public function requiresCustomies() : bool{
		return true;
	}

	public function load(array $packs) : void{
		$logger = $this->plugin->getLogger();
		$registered = 0;
		$vanilla = 0;

		foreach($packs as $pack){
			foreach($pack->listFiles("entities", "json") as $file){
				try{
					$result = $this->loadFile($pack, $file);
				}catch(Throwable $e){
					$logger->warning("Behavior packs: skipped entity " . $pack->getName() . "/" . $pack->relativePath($file) . ": " . $e->getMessage());
					continue;
				}
				if($result === true){
					$registered++;
				}elseif($result === false){
					$vanilla++;
				}
			}
		}

		if($vanilla > 0){
			$logger->debug("Behavior packs: $vanilla vanilla entity overrides ignored");
		}
		$logger->info("Behavior packs: $registered custom entities");
		if($registered > 0 || PlayerDefinition::get() !== null){
			$this->registerPropertySync();
		}
		if(PlayerDefinition::get() !== null){
			PlayerBehaviorManager::register($this->plugin);
		}
		if($registered > 0){
			\behaviorpack\entity\behavior\PlayerInputTracker::register($this->plugin);
			\behaviorpack\entity\behavior\goal\combat\OwnerCombatTracker::register($this->plugin);
			$this->plugin->getServer()->getPluginManager()->registerEvent(\pocketmine\event\server\DataPacketReceiveEvent::class, function(\pocketmine\event\server\DataPacketReceiveEvent $event) : void{
				$packet = $event->getPacket();
				$player = $event->getOrigin()->getPlayer();
				if($player !== null && $packet instanceof \pocketmine\network\mcpe\protocol\ItemStackRequestPacket && \behaviorpack\entity\behavior\inventory\TradeRequestHandler::handle($player, $packet)){
					$event->cancel();
				}
			}, EventPriority::HIGH, $this->plugin);
		}
	}

	/**
	 * Sends the synced property registry of every custom entity right after
	 * the StartGame packet, before any of them can be spawned to the player.
	 */
	private function registerPropertySync() : void{
		$this->plugin->getServer()->getPluginManager()->registerEvent(DataPacketSendEvent::class, function(DataPacketSendEvent $event) : void{
			$packets = $event->getPackets();
			$result = [];
			$found = false;
			foreach($packets as $packet){
				$result[] = $packet;
				if($packet instanceof StartGamePacket){
					$found = true;
					foreach($this->classes as $identifier => $class){
						$registry = EntityProperties::registryPacket($identifier);
						if($registry !== null){
							$result[] = $registry;
						}
					}
					if(PlayerDefinition::get() !== null){
						$registry = EntityProperties::registryPacket(PlayerDefinition::IDENTIFIER);
						if($registry !== null){
							$result[] = $registry;
						}
					}
				}
			}
			if($found){
				$event->setPackets($result);
			}
		}, EventPriority::MONITOR, $this->plugin);
	}

	/**
	 * Returns true when a custom entity was registered, false for a vanilla
	 * override and null for a file that holds no entity.
	 *
	 * @throws BehaviorPackException
	 */
	private function loadFile(BehaviorPack $pack, string $file) : ?bool{
		$json = BehaviorPack::readJson($file);
		$definition = $json["minecraft:entity"] ?? null;
		if(!is_array($definition)){
			return null;
		}
		$identifier = $definition["description"]["identifier"] ?? null;
		if(!is_string($identifier) || preg_match('/^[a-z0-9_.\-]+:[a-z0-9_.\-\/]+$/', strtolower($identifier)) !== 1){
			throw new BehaviorPackException("missing or invalid description.identifier");
		}
		if(strtolower($identifier) === PlayerDefinition::IDENTIFIER){
			EntityDefinitionRegistry::register(PlayerDefinition::IDENTIFIER, $definition);
			PlayerDefinition::set($definition);
			return false;
		}
		if(str_starts_with(strtolower($identifier), "minecraft:")){
			return false;
		}
		if(isset($this->classes[$identifier])){
			throw new BehaviorPackException("entity $identifier is already registered");
		}

		EntityDefinitionRegistry::register($identifier, $definition);
		$class = $this->generateClass($identifier);
		CustomiesEntityFactory::getInstance()->registerEntity($class, $identifier);
		$this->classes[$identifier] = $class;

		if(($definition["description"]["is_spawnable"] ?? false) === true){
			$this->registerSpawnEgg($pack, $identifier, $class);
		}
		return true;
	}

	/**
	 * @phpstan-param class-string<BehaviorEntity> $class
	 */
	private function registerSpawnEgg(BehaviorPack $pack, string $identifier, string $class) : void{
		$eggIdentifier = $identifier . "_spawn_egg";
		try{
			$egg = new CustomSpawnEgg(new ItemIdentifier(ItemTypeIds::newId()), "Spawn Egg", $class);
			CustomiesItemFactory::getInstance()->registerItem(static function() use ($egg) : CustomSpawnEgg{
				return clone $egg;
			}, $eggIdentifier, new CreativeInventoryInfo(CreativeInventoryInfo::CATEGORY_NATURE, CreativeInventoryInfo::GROUP_MOB_EGGS));
		}catch(Throwable $e){
			$this->plugin->getLogger()->warning("Behavior packs: no spawn egg for $identifier (" . $pack->getName() . "): " . $e->getMessage());
		}
	}

	/**
	 * Entity network and save ids are static, so each identifier needs its
	 * own class: a two-line subclass of BehaviorEntity is declared at runtime.
	 *
	 * @return class-string<BehaviorEntity>
	 */
	private function generateClass(string $identifier) : string{
		$shortName = "Entity" . md5($identifier);
		$class = self::GENERATED_NAMESPACE . "\\" . $shortName;
		if(!class_exists($class, false)){
			eval(
				"namespace " . self::GENERATED_NAMESPACE . ";\n" .
				"final class $shortName extends \\" . BehaviorEntity::class . "{\n" .
				"\tpublic static function getNetworkTypeId() : string{\n" .
				"\t\treturn " . var_export($identifier, true) . ";\n" .
				"\t}\n" .
				"}\n"
			);
		}
		/** @var class-string<BehaviorEntity> $class */
		return $class;
	}

	public function close() : void{
		$this->classes = [];
		PlayerBehaviorManager::close();
		PlayerDefinition::clear();
		EntityDefinitionRegistry::clear();
		EntityProperties::clear();
	}

	/**
	 * @return array<string, class-string<BehaviorEntity>>
	 */
	public function getClasses() : array{
		return $this->classes;
	}

	public function isRegistered(string $identifier) : bool{
		return isset($this->classes[$identifier]) && EntityFactory::getInstance()->isRegistered($this->classes[$identifier]);
	}
}
