<?php

declare(strict_types=1);

namespace behaviorpack\spawn;

use behaviorpack\BehaviorPack;
use behaviorpack\BehaviorPackException;
use behaviorpack\ContentLoader;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\TaskHandler;
use Throwable;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function str_starts_with;
use function strtolower;

/**
 * Loads the spawn rules ("minecraft:spawn_rules" files in spawn_rules/) of
 * the behavior packs and runs natural spawning of their custom entities.
 */
final class SpawnRuleLoader implements ContentLoader{

	public const CATEGORIES = ["animal", "water_animal", "monster", "ambient", "cat", "pillager", "villager"];

	/** @var array<string, SpawnRule> */
	private array $rules = [];

	private ?TaskHandler $handler = null;

	public function __construct(
		private PluginBase $plugin
	){
	}

	public function getName() : string{
		return "spawn rules";
	}

	public function requiresCustomies() : bool{
		return false;
	}

	public function load(array $packs) : void{
		$logger = $this->plugin->getLogger();
		foreach($packs as $pack){
			foreach($pack->listFiles("spawn_rules", "json") as $file){
				try{
					$rule = $this->loadFile($file);
				}catch(Throwable $e){
					$logger->warning("Behavior packs: skipped spawn rule " . $pack->getName() . "/" . $pack->relativePath($file) . ": " . $e->getMessage());
					continue;
				}
				if($rule !== null){
					$this->rules[$rule->identifier] = $rule;
				}
			}
		}
		$logger->info("Behavior packs: " . count($this->rules) . " spawn rules");
		if(count($this->rules) > 0){
			$this->handler = $this->plugin->getScheduler()->scheduleRepeatingTask(new NaturalSpawner($this->plugin->getServer(), $this->rules), NaturalSpawner::PERIOD);
		}
	}

	/**
	 * @throws BehaviorPackException
	 */
	private function loadFile(string $file) : ?SpawnRule{
		$json = BehaviorPack::readJson($file);
		$definition = $json["minecraft:spawn_rules"] ?? null;
		if(!is_array($definition)){
			return null;
		}
		$identifier = $definition["description"]["identifier"] ?? null;
		if(!is_string($identifier) || $identifier === ""){
			throw new BehaviorPackException("missing description.identifier");
		}
		$identifier = strtolower($identifier);
		if(str_starts_with($identifier, "minecraft:")){
			return null;
		}
		$category = $definition["description"]["population_control"] ?? "animal";
		if(!is_string($category) || !in_array($category, self::CATEGORIES, true)){
			$category = "animal";
		}
		$conditions = [];
		foreach(($definition["conditions"] ?? []) as $entry){
			if(is_array($entry)){
				$condition = SpawnCondition::parse($entry);
				if(!$condition->experimental && $condition->weight > 0){
					$conditions[] = $condition;
				}
			}
		}
		if(count($conditions) === 0){
			return null;
		}
		return new SpawnRule($identifier, $category, $conditions);
	}

	public function close() : void{
		$this->handler?->cancel();
		$this->handler = null;
		$this->rules = [];
	}

	/**
	 * @return array<string, SpawnRule>
	 */
	public function getRules() : array{
		return $this->rules;
	}
}
