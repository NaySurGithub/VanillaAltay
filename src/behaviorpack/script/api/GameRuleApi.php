<?php

declare(strict_types=1);

namespace behaviorpack\script\api;

use behaviorpack\script\ScriptException;
use behaviorpack\script\ScriptLoader;
use behaviorpack\script\ScriptValues;
use pocketmine\block\Fire;
use pocketmine\entity\object\PrimedTNT;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockBurnEvent;
use pocketmine\event\block\BlockSpreadEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\network\mcpe\protocol\GameRulesChangedPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\StopSoundPacket;
use pocketmine\network\mcpe\protocol\types\BoolGameRule;
use pocketmine\network\mcpe\protocol\types\GameRule;
use pocketmine\network\mcpe\protocol\types\IntGameRule;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\World;
use function array_shift;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function in_array;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_string;
use function json_decode;
use function json_encode;
use function max;
use function microtime;
use function rtrim;
use function strtolower;
use const JSON_PRETTY_PRINT;

/**
 * Handles the "rule." requests: the world game rules (kept and persisted
 * server side, applied through event listeners where the server can), the
 * music of the world and of players, and clearing titles.
 */
final class GameRuleApi{

	public const DEFAULTS = [
		"commandBlockOutput" => true,
		"commandBlocksEnabled" => true,
		"doDayLightCycle" => true,
		"doEntityDrops" => true,
		"doFireTick" => true,
		"doImmediateRespawn" => false,
		"doInsomnia" => true,
		"doLimitedCrafting" => false,
		"doMobLoot" => true,
		"doMobSpawning" => true,
		"doTileDrops" => true,
		"doWeatherCycle" => true,
		"drowningDamage" => true,
		"fallDamage" => true,
		"fireDamage" => true,
		"freezeDamage" => true,
		"keepInventory" => false,
		"maxCommandChainLength" => 65536,
		"mobGriefing" => true,
		"naturalRegeneration" => true,
		"playersSleepingPercentage" => 100,
		"projectilesCanBreakBlocks" => true,
		"pvp" => true,
		"randomTickSpeed" => 1,
		"recipesUnlock" => true,
		"respawnBlocksExplode" => true,
		"sendCommandFeedback" => true,
		"showBorderEffect" => true,
		"showCoordinates" => false,
		"showDaysPlayed" => false,
		"showDeathMessages" => true,
		"showRecipeMessages" => true,
		"showTags" => true,
		"spawnRadius" => 5,
		"tntExplodes" => true,
		"tntExplosionDropDecay" => false,
		"locatorBar" => true
	];

	/** Rules the server handles itself and must not be sent to clients. */
	private const SERVER_ONLY = ["naturalRegeneration", "locatorBar"];

	private const MUSIC_DURATION = 180.0;

	/** @var array<string, bool|int> */
	private array $rules = self::DEFAULTS;

	private bool $listening = false;

	/** @var array<string, array{track: ?string, endsAt: float, loop: bool, queue: list<array{track: string, options: array<string, mixed>}>}> */
	private array $music = [];

	public function __construct(
		private ScriptValues $values,
		private ScriptLoader $loader
	){
		$this->load();
		if($loader->getPlugin()->isEnabled()){
			$this->listen();
		}
	}

	/**
	 * @param list<mixed> $a
	 */
	public function handle(string $method, array $a) : mixed{
		$this->listen();
		return match($method){
			"rule.get" => $this->get($this->name($a[0] ?? null)),
			"rule.set" => $this->set($this->name($a[0] ?? null), $a[1] ?? null),
			"rule.all" => $this->rules,
			"rule.music.play" => $this->playMusic($this->targets($a[0] ?? null), $this->string($a[1] ?? null), is_array($a[2] ?? null) ? $a[2] : [], false),
			"rule.music.queue" => $this->playMusic($this->targets($a[0] ?? null), $this->string($a[1] ?? null), is_array($a[2] ?? null) ? $a[2] : [], true),
			"rule.music.stop" => $this->stopMusic($this->targets($a[0] ?? null)),
			"rule.clearTitle" => $this->values->player($a[0] ?? null)->removeTitles(),
			default => throw new ScriptException("Unknown request " . $method)
		};
	}

	private function name(mixed $value) : string{
		if(!is_string($value) || !isset(self::DEFAULTS[$value])){
			throw new ScriptException("Unknown game rule");
		}
		return $value;
	}

	private function string(mixed $value) : string{
		if(!is_string($value) || $value === ""){
			throw new ScriptException("Expected a track id");
		}
		return $value;
	}

	public function get(string $name) : bool|int{
		return $this->rules[$name];
	}

	private function set(string $name, mixed $value) : bool{
		if(is_bool(self::DEFAULTS[$name])){
			if(!is_bool($value)){
				throw new ScriptException("Game rule " . $name . " expects a boolean");
			}
		}else{
			if(!is_numeric($value)){
				throw new ScriptException("Game rule " . $name . " expects a number");
			}
			$value = max(0, (int) $value);
		}
		if($this->rules[$name] === $value){
			return false;
		}
		$this->rules[$name] = $value;
		$this->save();
		$this->applyWorlds();
		if(!in_array($name, self::SERVER_ONLY, true)){
			$packet = GameRulesChangedPacket::create([strtolower($name) => $this->networkRule($value)]);
			foreach($this->values->getServer()->getOnlinePlayers() as $player){
				$player->getNetworkSession()->sendDataPacket($packet);
			}
		}
		return true;
	}

	private function networkRule(bool|int $value) : GameRule{
		return is_bool($value) ? new BoolGameRule($value, false) : new IntGameRule($value, false);
	}

	private function file() : string{
		return rtrim($this->loader->getPlugin()->getDataFolder(), "/\\") . "/gamerules.json";
	}

	private function load() : void{
		$file = $this->file();
		if(!file_exists($file)){
			return;
		}
		$data = json_decode((string) file_get_contents($file), true);
		if(!is_array($data)){
			return;
		}
		foreach($data as $name => $value){
			if(!isset(self::DEFAULTS[$name])){
				continue;
			}
			if(is_bool(self::DEFAULTS[$name]) && is_bool($value)){
				$this->rules[$name] = $value;
			}elseif(!is_bool(self::DEFAULTS[$name]) && is_numeric($value)){
				$this->rules[$name] = max(0, (int) $value);
			}
		}
	}

	private function save() : void{
		$changed = [];
		foreach($this->rules as $name => $value){
			if($value !== self::DEFAULTS[$name]){
				$changed[$name] = $value;
			}
		}
		@file_put_contents($this->file(), (string) json_encode($changed, JSON_PRETTY_PRINT));
	}

	private function sendAll(Player $player) : void{
		$rules = [];
		foreach($this->rules as $name => $value){
			if(!in_array($name, self::SERVER_ONLY, true)){
				$rules[strtolower($name)] = $this->networkRule($value);
			}
		}
		$player->getNetworkSession()->sendDataPacket(GameRulesChangedPacket::create($rules));
	}

	private function applyWorlds() : void{
		foreach($this->values->getServer()->getWorldManager()->getWorlds() as $world){
			$this->applyWorld($world);
		}
	}

	private function applyWorld(World $world) : void{
		if($this->rules["doDayLightCycle"]){
			$world->startTime();
		}else{
			$world->stopTime();
		}
		$speed = (int) $this->rules["randomTickSpeed"] * World::DEFAULT_TICKED_BLOCKS_PER_SUBCHUNK_PER_TICK;
		$setter = \Closure::bind(function(int $value) : void{
			$this->tickedBlocksPerSubchunkPerTick = $value;
		}, $world, World::class);
		$setter($speed);
	}

	private function listen() : void{
		if($this->listening){
			return;
		}
		$plugin = $this->loader->getPlugin();
		if(!$plugin->isEnabled()){
			return;
		}
		$this->listening = true;
		$manager = $plugin->getServer()->getPluginManager();
		$priority = EventPriority::HIGH;
		$manager->registerEvent(PlayerJoinEvent::class, function(PlayerJoinEvent $event) : void{
			$this->sendAll($event->getPlayer());
		}, EventPriority::MONITOR, $plugin);
		$manager->registerEvent(PlayerQuitEvent::class, function(PlayerQuitEvent $event) : void{
			unset($this->music[$event->getPlayer()->getUniqueId()->toString()]);
		}, EventPriority::MONITOR, $plugin);
		$manager->registerEvent(WorldLoadEvent::class, function(WorldLoadEvent $event) : void{
			$this->applyWorld($event->getWorld());
		}, EventPriority::MONITOR, $plugin);
		$manager->registerEvent(EntityDamageEvent::class, function(EntityDamageEvent $event) : void{
			$rule = match($event->getCause()){
				EntityDamageEvent::CAUSE_FALL => "fallDamage",
				EntityDamageEvent::CAUSE_FIRE, EntityDamageEvent::CAUSE_FIRE_TICK, EntityDamageEvent::CAUSE_LAVA => "fireDamage",
				EntityDamageEvent::CAUSE_DROWNING => "drowningDamage",
				EntityDamageEvent::CAUSE_FREEZING => "freezeDamage",
				default => null
			};
			if($event->getEntity() instanceof Player && $rule !== null && !$this->rules[$rule]){
				$event->cancel();
				return;
			}
			if(!$this->rules["pvp"] && $event instanceof EntityDamageByEntityEvent && $event->getEntity() instanceof Player && $event->getDamager() instanceof Player){
				$event->cancel();
			}
		}, $priority, $plugin);
		$manager->registerEvent(EntityRegainHealthEvent::class, function(EntityRegainHealthEvent $event) : void{
			if(!$this->rules["naturalRegeneration"] && $event->getRegainReason() === EntityRegainHealthEvent::CAUSE_SATURATION){
				$event->cancel();
			}
		}, $priority, $plugin);
		$manager->registerEvent(EntityDeathEvent::class, function(EntityDeathEvent $event) : void{
			if($event instanceof PlayerDeathEvent){
				if($this->rules["keepInventory"]){
					$event->setKeepInventory(true);
					$event->setKeepXp(true);
				}
				if(!$this->rules["showDeathMessages"]){
					$event->setDeathMessage("");
				}
				return;
			}
			if(!$this->rules["doMobLoot"]){
				$event->setDrops([]);
				$event->setXpDropAmount(0);
			}
		}, $priority, $plugin);
		$manager->registerEvent(BlockBreakEvent::class, function(BlockBreakEvent $event) : void{
			if(!$this->rules["doTileDrops"]){
				$event->setDrops([]);
				$event->setXpDropAmount(0);
			}
		}, $priority, $plugin);
		$manager->registerEvent(BlockBurnEvent::class, function(BlockBurnEvent $event) : void{
			if(!$this->rules["doFireTick"]){
				$event->cancel();
			}
		}, $priority, $plugin);
		$manager->registerEvent(BlockSpreadEvent::class, function(BlockSpreadEvent $event) : void{
			if(!$this->rules["doFireTick"] && $event->getNewState() instanceof Fire){
				$event->cancel();
			}
		}, $priority, $plugin);
		$manager->registerEvent(EntityPreExplodeEvent::class, function(EntityPreExplodeEvent $event) : void{
			if($event->getEntity() instanceof PrimedTNT){
				if(!$this->rules["tntExplodes"]){
					$event->cancel();
				}
				return;
			}
			if(!$this->rules["mobGriefing"]){
				$event->setBlockBreaking(false);
			}
		}, $priority, $plugin);
		$plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
			$this->advanceMusic();
		}), 20);
		$this->applyWorlds();
	}

	/**
	 * @return list<Player>
	 */
	private function targets(mixed $handle) : array{
		if($handle === null){
			return \array_values($this->values->getServer()->getOnlinePlayers());
		}
		return [$this->values->player($handle)];
	}

	/**
	 * @param list<Player>         $players
	 * @param array<string, mixed> $options
	 */
	private function playMusic(array $players, string $track, array $options, bool $queue) : null{
		$volume = (float) ($options["volume"] ?? 1.0);
		if($volume < 0.0 || $volume > 1.0){
			throw new ScriptException("Music volume must be between 0 and 1");
		}
		$fade = (float) ($options["fade"] ?? 0.0);
		if($fade < 0.0 || $fade > 10.0){
			throw new ScriptException("Music fade must be between 0 and 10");
		}
		foreach($players as $player){
			$key = $player->getUniqueId()->toString();
			$state = $this->music[$key] ?? ["track" => null, "endsAt" => 0.0, "loop" => false, "queue" => []];
			if($queue && $state["track"] !== null && ($state["loop"] || $state["endsAt"] > microtime(true))){
				$state["queue"][] = ["track" => $track, "options" => $options];
				$this->music[$key] = $state;
				continue;
			}
			$state["queue"] = $queue ? $state["queue"] : [];
			$this->music[$key] = $state;
			$this->start($player, $track, $options);
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $options
	 */
	private function start(Player $player, string $track, array $options) : void{
		$key = $player->getUniqueId()->toString();
		$session = $player->getNetworkSession();
		$current = $this->music[$key]["track"] ?? null;
		if($current !== null){
			$session->sendDataPacket(StopSoundPacket::create($current, false, true));
		}
		$loop = (bool) ($options["loop"] ?? false);
		$position = $player->getPosition();
		$session->sendDataPacket(PlaySoundPacket::create($track, $position->x, $position->y, $position->z, (float) ($options["volume"] ?? 1.0), 1.0, $loop ? -1 : 0, true, null, null));
		$this->music[$key]["track"] = $track;
		$this->music[$key]["loop"] = $loop;
		$this->music[$key]["endsAt"] = microtime(true) + self::MUSIC_DURATION;
	}

	/**
	 * @param list<Player> $players
	 */
	private function stopMusic(array $players) : null{
		foreach($players as $player){
			$key = $player->getUniqueId()->toString();
			$track = $this->music[$key]["track"] ?? null;
			$player->getNetworkSession()->sendDataPacket(StopSoundPacket::create($track ?? "", $track === null, true));
			unset($this->music[$key]);
		}
		return null;
	}

	private function advanceMusic() : void{
		$now = microtime(true);
		foreach($this->values->getServer()->getOnlinePlayers() as $player){
			$key = $player->getUniqueId()->toString();
			$state = $this->music[$key] ?? null;
			if($state === null || $state["loop"] || $state["endsAt"] > $now){
				continue;
			}
			$next = array_shift($state["queue"]);
			if($next === null){
				unset($this->music[$key]);
				continue;
			}
			$this->music[$key] = $state;
			$this->start($player, $next["track"], $next["options"]);
		}
	}
}
