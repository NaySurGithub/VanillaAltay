<?php

declare(strict_types=1);

namespace dimension;

use dimension\generator\end\EndCrystalSpawner;
use dimension\generator\GeneratorOptions;
use dimension\generator\VanillaGenerators;
use dimension\network\DimensionNetworkListener;
use dimension\portal\PortalListener;
use dimension\rule\DimensionRulesListener;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\WorldCreationOptions;

/**
 * The nether and the end: their generators, their worlds, the client side
 * dimension switch and the portals that link them to the overworld.
 */
final class DimensionModule{

	private Dimensions $dimensions;

	public function __construct(
		private PluginBase $plugin,
		private DimensionSettings $settings
	){
		$this->dimensions = new Dimensions($plugin->getServer(), $settings->getWorldNames());
	}

	public function load() : void{
		VanillaGenerators::register();
	}

	public function enable() : void{
		$this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() : void{
			$this->prepareWorld(DimensionIds::NETHER, VanillaGenerators::NETHER);
			$this->prepareWorld(DimensionIds::THE_END, VanillaGenerators::END);
		}), 0);

		$pluginManager = $this->plugin->getServer()->getPluginManager();
		$pluginManager->registerEvents(new DimensionNetworkListener($this->plugin, $this->dimensions), $this->plugin);
		$pluginManager->registerEvents(new PortalListener($this->plugin, $this->dimensions), $this->plugin);
		$pluginManager->registerEvents(new DimensionRulesListener($this->plugin, $this->dimensions), $this->plugin);
		$pluginManager->registerEvents(new EndCrystalSpawner($this->dimensions), $this->plugin);
	}

	public function getDimensions() : Dimensions{
		return $this->dimensions;
	}

	private function prepareWorld(int $dimension, string $generatorName) : void{
		$name = $this->dimensions->getWorldName($dimension);
		if($name === null){
			return;
		}
		$worldManager = $this->plugin->getServer()->getWorldManager();
		if($worldManager->getWorldByName($name) !== null){
			return;
		}
		if($worldManager->isWorldGenerated($name)){
			$worldManager->loadWorld($name, true);
			return;
		}

		$generator = GeneratorManager::getInstance()->getGenerator($generatorName);
		if($generator === null){
			$this->plugin->getLogger()->error("Dimensions: generator $generatorName is not registered, $name is not created");
			return;
		}
		$seed = $worldManager->getDefaultWorld()?->getSeed();
		$options = WorldCreationOptions::create()
			->setGeneratorClass($generator->getGeneratorClass())
			->setGeneratorOptions(GeneratorOptions::encode($this->plugin->getResourceFolder()));
		if($seed !== null){
			$options->setSeed($seed);
		}
		$worldManager->generateWorld($name, $options);
		$this->plugin->getLogger()->info("Dimensions: created $name");
	}
}
