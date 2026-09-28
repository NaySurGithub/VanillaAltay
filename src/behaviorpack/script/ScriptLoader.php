<?php

declare(strict_types=1);

namespace behaviorpack\script;

use behaviorpack\BehaviorPack;
use behaviorpack\BehaviorPackException;
use behaviorpack\ContentLoader;
use pocketmine\entity\Entity;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use Throwable;
use function count;
use function is_array;
use function is_file;
use function is_numeric;
use function is_string;
use function round;
use function rtrim;
use function str_replace;
use function str_starts_with;

/**
 * Runs the scripts of the behavior packs (the "script" modules of their
 * manifests) with the embedded JavaScript interpreter, which provides
 * @minecraft/server and @minecraft/server-ui. Server events are turned into
 * script events by ScriptEventListener; after-events are delivered on the
 * next tick, together with the due timers.
 */
final class ScriptLoader implements ContentLoader{

	private const SAVE_INTERVAL = 1200;

	private ScriptValues $values;
	private ScriptStorage $storage;
	private ScriptApi $api;
	private ScriptPlayerState $state;
	private ScriptStructures $structures;
	private ScriptFeatures $features;
	private ScriptJigsaw $jigsaw;
	private ScriptExtendedApi $extended;
	private ?ScriptEventListener $listener = null;
	private ?ScriptRuntime $runtime = null;
	private ?TaskHandler $tickTask = null;

	/** @var array<string, int> */
	private array $useDurations = [];

	/** @var list<array{e: string, d: mixed}> */
	private array $queue = [];

	/** @var array<string, array<string, mixed>> */
	private array $blockComponents = [];

	/** @var array<string, array<string, mixed>> */
	private array $itemComponents = [];

	private bool $closed = false;

	public function __construct(
		private PluginBase $plugin,
		private int $timeoutMs = ScriptRuntime::WATCHDOG_MS
	){
		$this->values = new ScriptValues($plugin->getServer());
		$directory = rtrim(str_replace("\\", "/", $plugin->getDataFolder()), "/") . "/scripts";
		$this->storage = new ScriptStorage($directory);
		$this->state = new ScriptPlayerState();
		$this->structures = new ScriptStructures($directory . "/structures");
		$this->features = new ScriptFeatures($this->values, $this->structures);
		$this->jigsaw = new ScriptJigsaw($this->values, $this->structures);
		$this->extended = new ScriptExtendedApi($this->values, $this->structures, $this->features, $this->jigsaw, $this->state);
		$handlers = [];
		foreach(["cmd." => "CommandApi", "rule." => "GameRuleApi", "tag." => "TagApi", "dimx." => "DimensionApi"] as $prefix => $name){
			$class = "behaviorpack\\script\\api\\" . $name;
			if(\class_exists($class)){
				$handlers[$prefix] = new $class($this->values, $this);
			}
		}
		$this->api = new ScriptApi($this, $this->values, $this->storage, $this->extended, $handlers);
	}

	public function getPlugin() : PluginBase{
		return $this->plugin;
	}

	public function getRuntime() : ?ScriptRuntime{
		return $this->runtime;
	}

	public function getPlayerState() : ScriptPlayerState{
		return $this->state;
	}

	public function getExtendedApi() : ScriptExtendedApi{
		return $this->extended;
	}

	/**
	 * Returns how long an item of the packs takes to use, in ticks, when it
	 * declares a use duration or is food.
	 */
	public function useDuration(string $typeId) : ?int{
		return $this->useDurations[$typeId] ?? null;
	}

	public function getName() : string{
		return "scripts";
	}

	public function requiresCustomies() : bool{
		return false;
	}

	public function load(array $packs) : void{
		$this->structures->registerPacks($packs);
		$this->features->registerPacks($packs);
		$this->jigsaw->registerPacks($packs);
		if(\class_exists(\behaviorpack\script\api\TagApi::class)){
			\behaviorpack\script\api\TagApi::registerPacks($packs);
		}
		$entries = [];
		foreach($packs as $pack){
			$entry = $pack->getScriptEntry();
			if($entry === null){
				continue;
			}
			$path = $pack->getPath() . "/" . $entry;
			if(!is_file($path) && is_file($pack->getPath() . "/scripts/" . $entry)){
				$path = $pack->getPath() . "/scripts/" . $entry;
			}
			if(!is_file($path)){
				$this->plugin->getLogger()->warning("Behavior packs: script entry " . $entry . " not found in " . $pack->getName());
				continue;
			}
			$entries[] = ["name" => $pack->getName(), "root" => $pack->getPath(), "entry" => $path];
			$this->collectComponents($pack);
		}
		if(count($entries) === 0){
			return;
		}
		$this->storage->load();
		$this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($entries) : void{
			$this->start($entries);
		}), 1);
	}

	/**
	 * @param list<array{name: string, root: string, entry: string}> $entries
	 */
	private function start(array $entries) : void{
		if($this->closed){
			return;
		}
		$server = $this->plugin->getServer();
		try{
			$this->runtime = new ScriptRuntime($this->api, $this->storage, $this->plugin->getLogger(), $this->timeoutMs);
		}catch(Throwable $e){
			$this->plugin->getLogger()->error("Behavior packs: the script runtime could not start");
			$this->plugin->getLogger()->logException($e);
			return;
		}
		if(\class_exists(\behaviorpack\entity\player\PlayerSelector::class)){
			\behaviorpack\entity\player\PlayerSelector::$tagResolver = function(\pocketmine\player\Player $player, string $tag) : bool{
				return \in_array($tag, $this->storage->getTags($this->values->scope($player)), true);
			};
			\behaviorpack\entity\player\PlayerSelector::$scoreResolver = function(\pocketmine\player\Player $player, string $objective) : ?int{
				$score = $this->runtime?->server->scoreboardData["objectives"][$objective]["scores"]["p:" . $player->getName()] ?? null;
				return \is_int($score) ? $score : null;
			};
		}
		$this->listener = new ScriptEventListener($this, $this->values, $this->storage, $this->state, $this->extended);
		$this->listener->register($this->plugin);
		$server->getCommandMap()->register($this->plugin->getName(), new ScriptEventCommand($this->plugin, $this));
		$loaded = $this->runtime->start($entries, $this->blockComponents, $this->itemComponents, $server->getTick());
		$this->tickTask = $this->plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
			$this->tick();
		}), 1);
		$this->plugin->getLogger()->info("Behavior packs: " . $loaded . " scripts running");
	}

	public function close() : void{
		$this->closed = true;
		if($this->tickTask !== null){
			$this->tickTask->cancel();
			$this->tickTask = null;
		}
		$this->queue = [];
		$this->storage->save();
	}

	private function tick() : void{
		$tick = $this->plugin->getServer()->getTick();
		if($tick % self::SAVE_INTERVAL === 0){
			$this->storage->save();
		}
		$runtime = $this->runtime;
		if($runtime === null || $runtime->isStopped()){
			$this->queue = [];
			return;
		}
		$this->listener?->flush();
		if(count($this->queue) === 0 && !$runtime->hasPending()){
			return;
		}
		$events = $this->queue;
		$this->queue = [];
		$runtime->tick($tick, $events);
	}

	/**
	 * Whether a script subscribed to an event, named "after.<event>" or
	 * "before.<event>".
	 */
	public function wants(string $event) : bool{
		return $this->runtime !== null && $this->runtime->wants($event);
	}

	public function hasBlockHook(string $typeId) : bool{
		return $this->runtime !== null && $this->runtime->hasBlockHook($typeId);
	}

	public function hasItemHook(string $typeId) : bool{
		return $this->runtime !== null && $this->runtime->hasItemHook($typeId);
	}

	/**
	 * Queues an event delivered to the scripts on the next tick.
	 */
	public function queueEvent(string $event, mixed $data) : void{
		if($this->runtime !== null && !$this->runtime->isStopped()){
			$this->queue[] = ["e" => $event, "d" => $data];
		}
	}

	/**
	 * Dispatches a before-event right away and returns whether a script
	 * cancelled it ("c") and the fields it changed ("m").
	 *
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>|null
	 */
	public function dispatchSync(string $event, array $data) : ?array{
		if($this->runtime === null || $this->runtime->isStopped()){
			return null;
		}
		return $this->runtime->dispatchBefore($event, $data);
	}

	/**
	 * Runs a custom component hook right away and returns whether a script
	 * cancelled it.
	 *
	 * @param array<string, mixed> $message
	 */
	public function dispatchComponentSync(array $message) : bool{
		if($this->runtime === null || $this->runtime->isStopped()){
			return false;
		}
		return $this->runtime->dispatchComponentSync($message);
	}

	public function sendScriptEvent(string $id, string $message, ?Entity $source) : void{
		if(!$this->wants("after.scriptEventReceive") || $this->runtime === null){
			return;
		}
		$this->runtime->sendScriptEvent($id, $message, $source === null ? null : $this->values->entityRef($source));
	}

	/**
	 * @param array<mixed> $components
	 */
	private function collectUseDuration(string $typeId, array $components) : void{
		$seconds = null;
		$modifiers = $components["minecraft:use_modifiers"] ?? null;
		if(is_array($modifiers) && is_numeric($modifiers["use_duration"] ?? null)){
			$seconds = (float) $modifiers["use_duration"];
		}elseif(is_numeric($components["minecraft:use_duration"] ?? null)){
			$seconds = (float) $components["minecraft:use_duration"];
		}elseif(isset($components["minecraft:food"])){
			$seconds = 1.6;
		}
		if($seconds !== null){
			$this->useDurations[$typeId] = (int) round($seconds * 20);
		}
	}

	/**
	 * Records the custom components declared by the blocks and items of a
	 * pack, with their parameters: the names listed in
	 * "minecraft:custom_components" and the non-vanilla component keys.
	 */
	private function collectComponents(BehaviorPack $pack) : void{
		foreach(["blocks" => "minecraft:block", "items" => "minecraft:item"] as $directory => $root){
			foreach($pack->listFiles($directory) as $file){
				try{
					$json = BehaviorPack::readJson($file);
				}catch(BehaviorPackException){
					continue;
				}
				$definition = $json[$root] ?? null;
				$typeId = is_array($definition) ? ($definition["description"]["identifier"] ?? null) : null;
				if(!is_string($typeId)){
					continue;
				}
				if($directory === "items"){
					$this->collectUseDuration($typeId, is_array($definition["components"] ?? null) ? $definition["components"] : []);
				}
				$groups = [$definition["components"] ?? []];
				foreach(is_array($definition["permutations"] ?? null) ? $definition["permutations"] : [] as $permutation){
					if(is_array($permutation)){
						$groups[] = $permutation["components"] ?? [];
					}
				}
				$declared = [];
				foreach($groups as $components){
					if(!is_array($components)){
						continue;
					}
					foreach($components as $key => $value){
						$key = (string) $key;
						if($key === "minecraft:custom_components" && is_array($value)){
							foreach($value as $name){
								if(is_string($name) && !isset($declared[$name])){
									$declared[$name] = null;
								}
							}
						}elseif(!str_starts_with($key, "minecraft:") && !str_starts_with($key, "tag:")){
							$declared[$key] = $value;
						}
					}
				}
				if(count($declared) === 0){
					continue;
				}
				if($directory === "blocks"){
					$this->blockComponents[$typeId] = $declared;
				}else{
					$this->itemComponents[$typeId] = $declared;
				}
			}
		}
	}
}
