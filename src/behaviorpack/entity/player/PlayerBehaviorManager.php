<?php

declare(strict_types=1);

namespace behaviorpack\entity\player;

use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use Throwable;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function mkdir;
use const JSON_PRESERVE_ZERO_FRACTION;

/**
 * Gives every online player the behavior of the "minecraft:player"
 * definition: properties synced to the clients and saved per UUID, events,
 * component groups and animation controllers ticked every tick.
 */
final class PlayerBehaviorManager{

	private static ?PluginBase $plugin = null;

	private static ?TaskHandler $task = null;

	/** @var array<int, PlayerBehavior> */
	private static array $behaviors = [];

	private function __construct(){
	}

	public static function register(PluginBase $plugin) : void{
		if(self::$plugin !== null || PlayerDefinition::get() === null){
			return;
		}
		self::$plugin = $plugin;
		$manager = $plugin->getServer()->getPluginManager();
		$manager->registerEvent(PlayerLoginEvent::class, function(PlayerLoginEvent $event) : void{
			self::open($event->getPlayer());
		}, EventPriority::MONITOR, $plugin);
		$manager->registerEvent(PlayerJoinEvent::class, function(PlayerJoinEvent $event) : void{
			self::get($event->getPlayer())?->onJoin();
		}, EventPriority::MONITOR, $plugin);
		$manager->registerEvent(PlayerQuitEvent::class, function(PlayerQuitEvent $event) : void{
			self::closePlayer($event->getPlayer());
		}, EventPriority::MONITOR, $plugin);
		$manager->registerEvent(DataPacketSendEvent::class, function(DataPacketSendEvent $event) : void{
			foreach($event->getPackets() as $packet){
				if(!$packet instanceof AddPlayerPacket){
					continue;
				}
				foreach(self::$behaviors as $behavior){
					if($behavior->getPlayer()->getId() === $packet->actorRuntimeId){
						$packet->syncedProperties = $behavior->syncData();
						break;
					}
				}
			}
		}, EventPriority::HIGHEST, $plugin);
		self::$task = $plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
			foreach(self::$behaviors as $behavior){
				$player = $behavior->getPlayer();
				if(!$player->isConnected() || !$player->spawned || !$player->isAlive()){
					continue;
				}
				try{
					$behavior->tick(1);
				}catch(Throwable $e){
					self::$plugin?->getLogger()->logException($e);
				}
			}
		}), 1);
		foreach($plugin->getServer()->getOnlinePlayers() as $player){
			self::open($player)->onJoin();
		}
	}

	public static function get(Player $player) : ?PlayerBehavior{
		return self::$behaviors[$player->getId()] ?? null;
	}

	private static function open(Player $player) : PlayerBehavior{
		$existing = self::$behaviors[$player->getId()] ?? null;
		if($existing !== null){
			return $existing;
		}
		return self::$behaviors[$player->getId()] = new PlayerBehavior($player, self::read($player));
	}

	private static function closePlayer(Player $player) : void{
		$behavior = self::$behaviors[$player->getId()] ?? null;
		if($behavior === null){
			return;
		}
		unset(self::$behaviors[$player->getId()]);
		self::write($player, $behavior);
	}

	private static function directory() : ?string{
		if(self::$plugin === null){
			return null;
		}
		$directory = self::$plugin->getDataFolder() . "player_behavior";
		if(!is_dir($directory)){
			@mkdir($directory, 0777, true);
		}
		return $directory;
	}

	private static function file(Player $player) : ?string{
		$directory = self::directory();
		return $directory === null ? null : $directory . "/" . $player->getUniqueId()->toString() . ".json";
	}

	/**
	 * @return array<mixed>
	 */
	private static function read(Player $player) : array{
		$file = self::file($player);
		if($file === null || !is_file($file)){
			return [];
		}
		$contents = @file_get_contents($file);
		$decoded = $contents === false ? null : json_decode($contents, true);
		return is_array($decoded) ? $decoded : [];
	}

	private static function write(Player $player, PlayerBehavior $behavior) : void{
		$file = self::file($player);
		$encoded = json_encode($behavior->save(), JSON_PRESERVE_ZERO_FRACTION);
		if($file !== null && $encoded !== false){
			@file_put_contents($file, $encoded);
		}
	}

	public static function close() : void{
		foreach(self::$behaviors as $behavior){
			self::write($behavior->getPlayer(), $behavior);
		}
		self::$behaviors = [];
		self::$task?->cancel();
		self::$task = null;
		self::$plugin = null;
	}
}
