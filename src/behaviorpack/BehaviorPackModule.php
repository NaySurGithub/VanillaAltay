<?php

declare(strict_types=1);

namespace behaviorpack;

use behaviorpack\custom\CustomContentLoader;
use behaviorpack\entity\EntityLoader;
use behaviorpack\loot\LootTableLoader;
use behaviorpack\recipe\RecipeLoader;
use behaviorpack\script\ScriptLoader;
use pocketmine\event\EventPriority;
use pocketmine\event\plugin\PluginEnableEvent;
use pocketmine\plugin\PluginBase;
use Symfony\Component\Filesystem\Path;
use Throwable;
use function count;

/**
 * Loads the behavior packs dropped in the configured folder, behavior_packs
 * next to resource_packs by default.
 *
 * When Customies is installed, every loader runs as soon as Customies enables,
 * so custom items, blocks and entities are registered before Customies freezes
 * them and before the recipes and loot tables that reference them. Without
 * Customies, the loaders that need it are skipped and the others run when this
 * plugin enables.
 */
final class BehaviorPackModule{

	public const CUSTOMIES = "Customies";

	/** @var list<ContentLoader> */
	private array $loaders;

	/** @var list<BehaviorPack> */
	private array $packs = [];

	private bool $loaded = false;

	public function __construct(
		private PluginBase $plugin,
		private BehaviorPackSettings $settings
	){
		$this->loaders = [];
		if($settings->itemsAndBlocks){
			$this->loaders[] = new CustomContentLoader($plugin);
		}
		if($settings->entities){
			$this->loaders[] = new EntityLoader($plugin);
		}
		if($settings->recipes){
			$this->loaders[] = new RecipeLoader($plugin);
		}
		if($settings->lootTables){
			$this->loaders[] = new LootTableLoader($plugin);
		}
		if($settings->scripts){
			$this->loaders[] = new ScriptLoader($plugin, $settings->scriptTimeoutMs);
		}
	}

	public function getPacksDirectory() : string{
		return Path::isAbsolute($this->settings->folder) ? $this->settings->folder : Path::join($this->plugin->getServer()->getDataPath(), $this->settings->folder);
	}

	/**
	 * @return list<BehaviorPack>
	 */
	public function getPacks() : array{
		return $this->packs;
	}

	public function enable() : void{
		$discovery = new PackDiscovery($this->plugin->getDataFolder() . "cache/behavior_packs", $this->plugin->getLogger());
		$this->packs = $discovery->discover($this->getPacksDirectory());
		if(count($this->packs) === 0){
			return;
		}
		$this->plugin->getLogger()->info("Behavior packs found: " . count($this->packs));

		$customies = $this->plugin->getServer()->getPluginManager()->getPlugin(self::CUSTOMIES);
		if($customies === null){
			$this->plugin->getLogger()->warning("Customies is not installed: custom items, blocks and entities are skipped");
			$this->runLoaders(false);
			return;
		}
		if($customies->isEnabled()){
			$this->runLoaders(true);
			return;
		}
		$this->plugin->getServer()->getPluginManager()->registerEvent(PluginEnableEvent::class, function(PluginEnableEvent $event) : void{
			if($event->getPlugin()->getName() === self::CUSTOMIES){
				$this->runLoaders(true);
			}
		}, EventPriority::MONITOR, $this->plugin);
	}

	public function disable() : void{
		foreach($this->loaders as $loader){
			try{
				$loader->close();
			}catch(Throwable $e){
				$this->plugin->getLogger()->logException($e);
			}
		}
	}

	private function runLoaders(bool $customies) : void{
		if($this->loaded){
			return;
		}
		$this->loaded = true;

		foreach($this->loaders as $loader){
			if($loader->requiresCustomies() && !$customies){
				continue;
			}
			try{
				$loader->load($this->packs);
			}catch(Throwable $e){
				$this->plugin->getLogger()->error("Behavior packs: " . $loader->getName() . " failed");
				$this->plugin->getLogger()->logException($e);
			}
		}
	}
}
