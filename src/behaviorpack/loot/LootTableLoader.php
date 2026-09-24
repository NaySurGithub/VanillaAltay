<?php

declare(strict_types=1);

namespace behaviorpack\loot;

use behaviorpack\BehaviorPack;
use behaviorpack\BehaviorPackException;
use behaviorpack\ContentLoader;
use pocketmine\event\HandlerListManager;
use pocketmine\plugin\PluginBase;
use function array_keys;
use function count;
use function is_array;
use function is_string;

/**
 * Loads the loot_tables of every pack and applies the loot tables declared
 * by pack blocks (minecraft:loot) and entities (minecraft:loot.table).
 */
final class LootTableLoader implements ContentLoader{

	private ?LootListener $listener = null;

	public function __construct(
		private PluginBase $plugin
	){
	}

	public function getName() : string{
		return "loot tables";
	}

	public function requiresCustomies() : bool{
		return false;
	}

	public function load(array $packs) : void{
		$logger = $this->plugin->getLogger();
		foreach($packs as $pack){
			foreach($pack->listFiles("loot_tables", "json") as $file){
				$path = $pack->relativePath($file);
				try{
					LootTableRegistry::register(LootTableParser::parse($path, BehaviorPack::readJson($file)));
				}catch(BehaviorPackException $e){
					$logger->warning("Behavior packs: skipped loot table $path of " . $pack->getName() . ": " . $e->getMessage());
				}
			}
		}

		$blockTables = [];
		$entityTables = [];
		foreach($packs as $pack){
			foreach($pack->listFiles("blocks", "json") as $file){
				$this->readDefinition($pack, $file, "minecraft:block", $blockTables);
			}
			foreach($pack->listFiles("entities", "json") as $file){
				$this->readDefinition($pack, $file, "minecraft:entity", $entityTables);
			}
		}

		if(count($blockTables) > 0 || count($entityTables) > 0){
			$this->listener = new LootListener($blockTables, $entityTables);
			$this->plugin->getServer()->getPluginManager()->registerEvents($this->listener, $this->plugin);
		}

		$logger->info("Behavior packs: " . LootTableRegistry::count() . " loot tables");
	}

	/**
	 * @param array<string, string> $tables
	 */
	private function readDefinition(BehaviorPack $pack, string $file, string $root, array &$tables) : void{
		try{
			$json = BehaviorPack::readJson($file);
		}catch(BehaviorPackException $e){
			$this->plugin->getLogger()->warning("Behavior packs: skipped " . $pack->relativePath($file) . " of " . $pack->getName() . ": " . $e->getMessage());
			return;
		}
		$definition = $json[$root] ?? null;
		if(!is_array($definition)){
			return;
		}
		$identifier = $definition["description"]["identifier"] ?? null;
		if(!is_string($identifier) || $identifier === ""){
			return;
		}
		$path = $root === "minecraft:block" ? self::blockLootPath($definition) : self::entityLootPath($definition);
		if($path === null){
			return;
		}
		if(LootTableRegistry::get($path) === null){
			$this->plugin->getLogger()->warning("Behavior packs: $identifier references the missing loot table $path");
			return;
		}
		$tables[$identifier] = LootTableRegistry::normalizePath($path);
	}

	/**
	 * Returns the loot table of a block, or the one shared by all of its
	 * permutations when the base components declare none.
	 *
	 * @param array<mixed> $definition
	 */
	private static function blockLootPath(array $definition) : ?string{
		$loot = $definition["components"]["minecraft:loot"] ?? null;
		if(is_string($loot) && $loot !== ""){
			return $loot;
		}
		$permutations = $definition["permutations"] ?? null;
		if(!is_array($permutations)){
			return null;
		}
		$paths = [];
		foreach($permutations as $permutation){
			$permutationLoot = is_array($permutation) ? ($permutation["components"]["minecraft:loot"] ?? null) : null;
			if(is_string($permutationLoot) && $permutationLoot !== ""){
				$paths[$permutationLoot] = true;
			}
		}
		$paths = array_keys($paths);
		return count($paths) === 1 ? (string) $paths[0] : null;
	}

	/**
	 * @param array<mixed> $definition
	 */
	private static function entityLootPath(array $definition) : ?string{
		$table = $definition["components"]["minecraft:loot"]["table"] ?? null;
		return is_string($table) && $table !== "" ? $table : null;
	}

	public function close() : void{
		if($this->listener !== null){
			HandlerListManager::global()->unregisterAll($this->listener);
			$this->listener = null;
		}
		LootTableRegistry::clear();
	}
}
