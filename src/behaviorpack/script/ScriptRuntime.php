<?php

declare(strict_types=1);

namespace behaviorpack\script;

use behaviorpack\script\binding\ClassFactory;
use behaviorpack\script\binding\ServerModule;
use behaviorpack\script\binding\UiModule;
use behaviorpack\script\js\Interpreter;
use behaviorpack\script\js\JsCallable;
use behaviorpack\script\js\JsObject;
use behaviorpack\script\js\JsPromise;
use behaviorpack\script\js\JsThrow;
use behaviorpack\script\js\ModuleLoader;
use behaviorpack\script\js\ScriptTimeoutException;
use behaviorpack\script\js\SyntaxErrorException;
use Closure;
use Logger;
use Throwable;
use function array_keys;
use function array_shift;
use function count;
use function hrtime;
use function implode;
use function is_array;
use function is_string;
use function max;
use function substr;
use function ucfirst;

/**
 * Runs the scripts of the behavior packs in the embedded JavaScript
 * interpreter. Every entry into script code goes through run(): it names
 * the pack, starts the watchdog for the outermost entry, runs the promise
 * jobs, and disables the pack whose code exceeds the watchdog delay.
 */
final class ScriptRuntime{

	public const WATCHDOG_MS = 2000;

	private const JOB_BUDGET_NS = 4_000_000;

	public Interpreter $js;
	public ServerModule $server;
	private ModuleLoader $modules;

	/** @var array<string, list<array{0: JsCallable, 1: ?string}>> */
	private array $subscribers = [];

	/** @var array<int, array{callback: JsCallable, due: int, interval: int, pack: ?string}> */
	private array $timers = [];

	/** @var array<int, array{generator: JsObject, pack: ?string}> */
	private array $jobs = [];

	private int $nextRunId = 1;

	public int $currentTick = 0;

	/** @var list<array<string, mixed>> */
	private array $localEvents = [];

	/** @var array<string, array{0: JsObject, 1: ?string}> */
	private array $blockComponents = [];

	/** @var array<string, array{0: JsObject, 1: ?string}> */
	private array $itemComponents = [];

	/** @var array<string, array<string, mixed>> */
	private array $declaredBlocks = [];

	/** @var array<string, array<string, mixed>> */
	private array $declaredItems = [];

	/** @var array<string, true> */
	private array $blockHooks = [];

	/** @var array<string, true> */
	private array $itemHooks = [];

	/** @var array<int, array{promise: JsPromise, player: int, map: Closure, pack: ?string}> */
	private array $forms = [];

	private int $nextFormId = 1;

	/** @var array<string, true> */
	private array $missing = [];

	/** @var list<string> */
	private array $newMissing = [];

	/** @var array<string, true> */
	private array $disabled = [];

	private bool $allDisabled = false;

	private int $depth = 0;

	public bool $scoreboardDirty = false;

	public function __construct(
		public ScriptApi $api,
		public ScriptStorage $storage,
		private Logger $logger,
		private int $watchdogMs = self::WATCHDOG_MS
	){
		$this->js = new Interpreter();
		$this->js->printer = function(string $level, string $message, ?string $context) : void{
			$this->log($level, ($context !== null ? "[" . $context . "] " : "") . $message);
		};
		$factory = new ClassFactory($this->js, function(string $name) : void{
			$this->recordMissing($name);
		});
		$this->server = new ServerModule($this, $factory);
		$ui = new UiModule($this, $factory, $this->server);
		$this->modules = new ModuleLoader($this->js, function(string $module, string $name) : mixed{
			return $this->server->stub($module . ":" . $name);
		});
		$this->modules->addHostModule("@minecraft/server", $this->server->exports(), function(string $name) : mixed{
			return $this->server->stub($name);
		});
		$this->modules->addHostModule("@minecraft/server-ui", $ui->exports(), function(string $name) : mixed{
			return $this->server->stub("@minecraft/server-ui:" . $name);
		});
		$this->modules->addHostModule("@minecraft/common", $this->commonExports(), function(string $name) : mixed{
			return $this->server->stub("@minecraft/common:" . $name);
		});
	}

	/**
	 * @return array<string, mixed>
	 */
	private function commonExports() : array{
		$exports = [];
		foreach(["ArgumentOutOfBoundsError", "EngineError", "InvalidArgumentError", "PropertyOutOfBoundsError", "UnsupportedFunctionalityError"] as $name){
			$exports[$name] = $this->server->exports()[$name] ?? $this->js->constructors["Error"];
		}
		return $exports;
	}

	public function log(string $level, string $message) : void{
		$message = "[script] " . $message;
		match($level){
			"error" => $this->logger->error($message),
			"warning" => $this->logger->warning($message),
			"debug" => $this->logger->debug($message),
			default => $this->logger->info($message)
		};
	}

	public function recordMissing(string $name) : void{
		if(!isset($this->missing[$name])){
			$this->missing[$name] = true;
			$this->newMissing[] = $name;
		}
	}

	/**
	 * Runs script code for a pack, with the watchdog, error reporting and
	 * the promise jobs.
	 *
	 * @param Closure() : void $code
	 */
	public function run(?string $pack, Closure $code) : void{
		if($this->allDisabled || ($pack !== null && isset($this->disabled[$pack]))){
			return;
		}
		$outer = $this->depth === 0;
		$savedContext = $this->js->context;
		$this->js->context = $pack;
		if($outer){
			$this->js->resetStack();
			$this->js->startWatchdog($this->watchdogMs);
		}
		$this->depth++;
		$failure = null;
		$offender = $pack;
		try{
			try{
				$code();
			}catch(JsThrow $e){
				$this->js->reportError($e->value);
			}catch(SyntaxErrorException $e){
				$this->log("error", ($pack !== null ? "[" . $pack . "] " : "") . "SyntaxError: " . $e->getMessage());
			}
			if($outer){
				$this->js->drainJobs();
			}
		}catch(ScriptTimeoutException $e){
			$failure = $e;
			$offender = $this->js->context ?? $pack;
		}catch(Throwable $e){
			$failure = $e;
		}
		$this->depth--;
		$this->js->context = $savedContext;
		if($outer){
			$this->js->stopWatchdog();
		}
		if($failure !== null){
			if(!$outer){
				throw $failure;
			}
			if($failure instanceof ScriptTimeoutException){
				$this->disable($offender);
			}else{
				$this->logger->error("Scripts: internal error while running " . ($pack === null ? "scripts" : "pack \"" . $pack . "\""));
				$this->logger->logException($failure);
			}
		}
		if($outer){
			$this->afterEntry();
		}
	}

	private function afterEntry() : void{
		$this->js->reportUnhandledRejections();
		if(count($this->newMissing) > 0){
			$this->logger->warning("Scripts: unimplemented API used: " . implode(", ", $this->newMissing));
			$this->newMissing = [];
		}
		if($this->scoreboardDirty){
			$this->scoreboardDirty = false;
			$this->storage->setScoreboard($this->server->scoreboardData);
		}
	}

	private function disable(?string $pack) : void{
		if($pack === null){
			$this->logger->error("Scripts: a script did not return control within " . $this->watchdogMs . " ms, scripts are disabled");
			$this->allDisabled = true;
			$this->subscribers = [];
			$this->timers = [];
			$this->jobs = [];
			$this->blockHooks = [];
			$this->itemHooks = [];
			return;
		}
		$this->logger->error("Scripts: pack \"" . $pack . "\" did not return control within " . $this->watchdogMs . " ms, its scripts are disabled");
		$this->disabled[$pack] = true;
		foreach($this->subscribers as $key => $list){
			$kept = [];
			foreach($list as $subscriber){
				if($subscriber[1] !== $pack){
					$kept[] = $subscriber;
				}
			}
			$this->subscribers[$key] = $kept;
		}
		foreach($this->timers as $id => $timer){
			if($timer["pack"] === $pack){
				unset($this->timers[$id]);
			}
		}
		foreach($this->jobs as $id => $job){
			if($job["pack"] === $pack){
				unset($this->jobs[$id]);
			}
		}
		foreach([&$this->blockComponents, &$this->itemComponents] as &$registry){
			foreach($registry as $name => $entry){
				if($entry[1] === $pack){
					unset($registry[$name]);
				}
			}
		}
		unset($registry);
		$this->js->removeJobs($pack);
		$this->computeHooks();
	}

	/**
	 * Imports the entry module of every pack, then fires the startup and
	 * world load events. Returns the number of packs whose scripts loaded.
	 *
	 * @param list<array{name: string, root: string, entry: string}> $entries
	 * @param array<string, array<string, mixed>>                   $blocks
	 * @param array<string, array<string, mixed>>                   $items
	 */
	public function start(array $entries, array $blocks, array $items, int $tick) : int{
		$this->declaredBlocks = $blocks;
		$this->declaredItems = $items;
		$this->currentTick = $tick;
		$loaded = 0;
		foreach($entries as $entry){
			$this->run($entry["name"], function() use ($entry, &$loaded) : void{
				$this->modules->run($entry["entry"], $entry["root"], $entry["name"]);
				$loaded++;
			});
		}
		$registries = $this->server->createRegistries();
		$startup = $this->server->createEvent("StartupEvent", $registries, false);
		foreach($this->subscribers["system.startup"] ?? [] as [$callback, $pack]){
			$this->run($pack, function() use ($callback, $startup) : void{
				$this->js->call($callback, null, [$startup]);
			});
		}
		$this->dispatch("after.worldInitialize", "WorldInitializeAfterEvent", [
			"blockComponentRegistry" => $registries["blockComponentRegistry"],
			"itemComponentRegistry" => $registries["itemComponentRegistry"]
		], false);
		$this->dispatch("after.worldLoad", "WorldLoadAfterEvent", [], false);
		return $loaded;
	}

	public function subscribe(string $key, JsCallable $callback) : void{
		if($this->js->context !== null && isset($this->disabled[$this->js->context])){
			return;
		}
		$this->subscribers[$key][] = [$callback, $this->js->context];
	}

	public function unsubscribe(string $key, JsCallable $callback) : void{
		$kept = [];
		foreach($this->subscribers[$key] ?? [] as $subscriber){
			if($subscriber[0] !== $callback){
				$kept[] = $subscriber;
			}
		}
		$this->subscribers[$key] = $kept;
	}

	public function wants(string $key) : bool{
		return count($this->subscribers[$key] ?? []) > 0;
	}

	/**
	 * Dispatches an event to its subscribers and returns the event object,
	 * or null when nobody subscribed.
	 *
	 * @param array<string, mixed> $data
	 */
	public function dispatch(string $key, string $className, array $data, bool $before) : ?JsObject{
		$subscribers = $this->subscribers[$key] ?? [];
		if(count($subscribers) === 0){
			return null;
		}
		$event = $this->server->createEvent($className, $data, $before);
		foreach($subscribers as [$callback, $pack]){
			$this->run($pack, function() use ($callback, $event) : void{
				$this->js->call($callback, null, [$event]);
			});
		}
		return $event;
	}

	/**
	 * Dispatches a before-event and returns whether it was cancelled and the
	 * changed fields.
	 *
	 * @param array<string, mixed> $data
	 * @return array{c: bool, m?: array<string, mixed>}|null
	 */
	public function dispatchBefore(string $name, array $data) : ?array{
		$event = $this->dispatch("before." . $name, ucfirst($name) . "BeforeEvent", $data, true);
		if($event === null){
			return null;
		}
		$result = ["c" => $this->js->toBoolean($event->props["cancel"] ?? false)];
		if($name === "chatSend"){
			$message = $event->props["message"] ?? null;
			if(is_string($message) && $message !== ($data["message"] ?? null)){
				$result["m"] = ["message" => $message];
			}
		}
		return $result;
	}

	public function hasPending() : bool{
		return count($this->timers) > 0 || count($this->jobs) > 0 || count($this->localEvents) > 0 || $this->js->hasJobs();
	}

	/**
	 * Runs a server tick: queued events, script events, due timers and jobs.
	 *
	 * @param list<array{e: string, d: mixed}> $events
	 */
	public function tick(int $tick, array $events) : void{
		$this->currentTick = $tick;
		foreach($events as $entry){
			$this->dispatchQueued($entry["e"], is_array($entry["d"]) ? $entry["d"] : []);
		}
		while(count($this->localEvents) > 0){
			$event = \array_shift($this->localEvents);
			$this->dispatch("after.scriptEventReceive", "ScriptEventCommandMessageAfterEvent", $event, false);
		}
		$this->runTimers();
		$this->runJobs();
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function dispatchQueued(string $name, array $data) : void{
		switch($name){
			case "__form":
				$this->resolveForm((int) ($data["id"] ?? 0), $data["r"] ?? null);
				return;
			case "__quit":
				$player = (int) ($data["id"] ?? 0);
				foreach($this->forms as $id => $form){
					if($form["player"] === $player){
						$this->resolveForm($id, null);
					}
				}
				$this->server->forgetEntity($player);
				return;
			case "__comp":
				$this->dispatchComponent($data);
				return;
		}
		$this->dispatch("after." . $name, ucfirst($name) . "AfterEvent", $data, false);
		if($name === "entityDie"){
			$dead = $data["deadEntity"] ?? null;
			if(is_array($dead) && ($dead["t"] ?? null) !== "minecraft:player" && isset($dead['$e'])){
				$this->server->forgetEntity((int) $dead['$e']);
			}
		}
	}

	public function sendScriptEvent(string $id, string $message, ?array $source = null) : void{
		$event = ["id" => $id, "message" => $message, "sourceType" => $source === null ? "Server" : "Entity"];
		if($source !== null){
			$event["sourceEntity"] = $source;
		}
		$this->localEvents[] = $event;
	}

	public function schedule(JsCallable $callback, int $delay, int $interval) : int{
		$id = $this->nextRunId++;
		$this->timers[$id] = ["callback" => $callback, "due" => $this->currentTick + max(1, $delay), "interval" => $interval, "pack" => $this->js->context];
		return $id;
	}

	public function clearRun(int $id) : void{
		unset($this->timers[$id]);
	}

	public function addJob(JsObject $generator) : int{
		$id = $this->nextRunId++;
		$this->jobs[$id] = ["generator" => $generator, "pack" => $this->js->context];
		return $id;
	}

	public function clearJob(int $id) : void{
		unset($this->jobs[$id]);
	}

	private function runTimers() : void{
		$due = [];
		foreach($this->timers as $id => $timer){
			if($timer["due"] <= $this->currentTick){
				$due[] = $id;
			}
		}
		foreach($due as $id){
			$timer = $this->timers[$id] ?? null;
			if($timer === null){
				continue;
			}
			if($timer["interval"] > 0){
				$this->timers[$id]["due"] = max($timer["due"] + $timer["interval"], $this->currentTick + 1);
			}else{
				unset($this->timers[$id]);
			}
			$callback = $timer["callback"];
			$this->run($timer["pack"], function() use ($callback) : void{
				$this->js->call($callback, null, []);
			});
		}
	}

	private function runJobs() : void{
		$deadline = hrtime(true) + self::JOB_BUDGET_NS;
		while(count($this->jobs) > 0 && hrtime(true) < $deadline){
			foreach(array_keys($this->jobs) as $id){
				$job = $this->jobs[$id] ?? null;
				if($job === null){
					continue;
				}
				$this->run($job["pack"], function() use ($id, $job) : void{
					try{
						$generator = $job["generator"];
						$result = $this->js->call($this->js->get($generator, "next"), $generator, []);
						if(!$result instanceof JsObject || $this->js->toBoolean($this->js->get($result, "done"))){
							unset($this->jobs[$id]);
						}
					}catch(JsThrow $e){
						unset($this->jobs[$id]);
						throw $e;
					}
				});
				if(hrtime(true) >= $deadline){
					break;
				}
			}
		}
	}

	public function registerComponent(string $kind, string $name, JsObject $component) : void{
		$entry = [$component, $this->js->context];
		if($kind === "block"){
			$this->blockComponents[$name] = $entry;
		}else{
			$this->itemComponents[$name] = $entry;
		}
		$this->computeHooks();
	}

	private function computeHooks() : void{
		$this->blockHooks = [];
		foreach($this->declaredBlocks as $typeId => $declared){
			foreach($declared as $name => $params){
				if(isset($this->blockComponents[$name])){
					$this->blockHooks[$typeId] = true;
					break;
				}
			}
		}
		$this->itemHooks = [];
		foreach($this->declaredItems as $typeId => $declared){
			foreach($declared as $name => $params){
				if(isset($this->itemComponents[$name])){
					$this->itemHooks[$typeId] = true;
					break;
				}
			}
		}
	}

	public function hasBlockHook(string $typeId) : bool{
		return isset($this->blockHooks[$typeId]);
	}

	public function hasItemHook(string $typeId) : bool{
		return isset($this->itemHooks[$typeId]);
	}

	/**
	 * @param array<string, mixed> $message
	 */
	private function dispatchComponent(array $message) : void{
		$block = ($message["k"] ?? null) === "b";
		$typeId = (string) ($message["t"] ?? "");
		$hook = (string) ($message["h"] ?? "");
		$declared = $block ? ($this->declaredBlocks[$typeId] ?? null) : ($this->declaredItems[$typeId] ?? null);
		if($declared === null){
			return;
		}
		$registry = $block ? $this->blockComponents : $this->itemComponents;
		$data = is_array($message["d"] ?? null) ? $message["d"] : [];
		$className = ($block ? "BlockComponent" : "ItemComponent") . ucfirst(\substr($hook, 2)) . "Event";
		foreach($declared as $name => $params){
			$entry = $registry[$name] ?? null;
			if($entry === null){
				continue;
			}
			[$component, $pack] = $entry;
			$this->run($pack, function() use ($component, $hook, $className, $data, $params) : void{
				$function = $this->js->get($component, $hook);
				if(!$function instanceof JsCallable){
					return;
				}
				$event = $this->server->createEvent($className, $data, false);
				$parameters = $this->js->newObject();
				$parameters->props["params"] = $this->server->toJs($params);
				$this->js->call($function, $component, [$event, $parameters]);
			});
		}
	}

	/**
	 * Shows a form to a player and returns the promise resolved with the
	 * response built by $map from the raw form data.
	 *
	 * @param array<string, mixed>        $form
	 * @param Closure(mixed) : JsObject   $map
	 */
	public function showForm(int $player, array $form, Closure $map) : JsPromise{
		$promise = $this->js->newPromise();
		$id = $this->nextFormId++;
		$this->forms[$id] = ["promise" => $promise, "player" => $player, "map" => $map, "pack" => $this->js->context];
		try{
			$this->api->handle("pl.form", [$player, $id, $form]);
		}catch(ScriptException $e){
			unset($this->forms[$id]);
			$this->js->rejectPromise($promise, $this->js->makeError("Error", $e->getMessage()));
		}
		return $promise;
	}

	private function resolveForm(int $id, mixed $raw) : void{
		$form = $this->forms[$id] ?? null;
		if($form === null){
			return;
		}
		unset($this->forms[$id]);
		$this->run($form["pack"], function() use ($form, $raw) : void{
			$this->js->resolvePromise($form["promise"], ($form["map"])($raw));
		});
	}

	public function isStopped() : bool{
		return $this->allDisabled;
	}
}
