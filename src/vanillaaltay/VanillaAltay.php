<?php

declare(strict_types=1);

namespace vanillaaltay;

use behaviorpack\BehaviorPackModule;
use behaviorpack\BehaviorPackSettings;
use dimension\DimensionModule;
use dimension\DimensionSettings;
use dummy\DummyModule;
use pocketmine\plugin\PluginBase;
use redstone\RedstoneModule;
use function is_array;

final class VanillaAltay extends PluginBase{

	private ?RedstoneModule $redstone = null;
	private ?DimensionModule $dimensions = null;
	private ?BehaviorPackModule $behaviorPacks = null;
	private ?DummyModule $dummies = null;

	protected function onLoad() : void{
		$this->saveDefaultConfig();
		$config = $this->getConfig();

		$redstone = $config->get("redstone");
		if(!is_array($redstone) || ($redstone["enabled"] ?? true) === true){
			$this->redstone = new RedstoneModule($this);
			$this->redstone->load();
		}

		$dimensions = $config->get("dimensions");
		$dimensionSettings = DimensionSettings::fromConfig(is_array($dimensions) ? $dimensions : []);
		if($dimensionSettings->enabled){
			$this->dimensions = new DimensionModule($this, $dimensionSettings);
			$this->dimensions->load();
		}

		$section = $config->get("behavior-packs");
		$settings = BehaviorPackSettings::fromConfig(is_array($section) ? $section : []);
		if($settings->enabled){
			$this->behaviorPacks = new BehaviorPackModule($this, $settings);
		}

		$dummies = $config->get("dummies");
		if(!is_array($dummies) || ($dummies["enabled"] ?? true) === true){
			$this->dummies = new DummyModule($this);
		}
	}

	protected function onEnable() : void{
		$this->redstone?->enable();
		$this->dimensions?->enable();
		$this->behaviorPacks?->enable();
		$this->dummies?->enable();
	}

	protected function onDisable() : void{
		$this->behaviorPacks?->disable();
	}
}
