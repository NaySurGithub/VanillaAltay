<?php

declare(strict_types=1);

namespace vanillaaltay;

use behaviorpack\BehaviorPackModule;
use behaviorpack\BehaviorPackSettings;
use pocketmine\plugin\PluginBase;
use redstone\RedstoneModule;
use function is_array;

final class VanillaAltay extends PluginBase{

	private ?RedstoneModule $redstone = null;
	private ?BehaviorPackModule $behaviorPacks = null;

	protected function onLoad() : void{
		$this->saveDefaultConfig();
		$config = $this->getConfig();

		$redstone = $config->get("redstone");
		if(!is_array($redstone) || ($redstone["enabled"] ?? true) === true){
			$this->redstone = new RedstoneModule($this);
			$this->redstone->load();
		}

		$section = $config->get("behavior-packs");
		$settings = BehaviorPackSettings::fromConfig(is_array($section) ? $section : []);
		if($settings->enabled){
			$this->behaviorPacks = new BehaviorPackModule($this, $settings);
		}
	}

	protected function onEnable() : void{
		$this->redstone?->enable();
		$this->behaviorPacks?->enable();
	}

	protected function onDisable() : void{
		$this->behaviorPacks?->disable();
	}
}
