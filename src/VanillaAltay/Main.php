<?php

declare(strict_types=1);

namespace VanillaAltay;

use pocketmine\plugin\PluginBase;
use pocketmine\world\generator\GeneratorManager;
use VanillaAltay\command\CommandHintsListener;
use VanillaAltay\command\LocateCommand;
use VanillaAltay\world\biome\VanillaBiomes;
use VanillaAltay\world\generator\overworld\VanillaOverworld;
use VanillaAltay\world\generator\structure\StructureRegistry;

final class Main extends PluginBase{

	/**
	 * Everything has to be in place before the server loads its worlds, which happens right after STARTUP.
	 */
	public function onLoad() : void{
		VanillaBiomes::register();

		GeneratorManager::getInstance()->addGenerator(
			VanillaOverworld::class,
			"vanilla_overworld",
			fn(string $preset) => null,
			true
		);

		$this->getLogger()->info("Registered the vanilla_overworld generator");
	}

	public function onEnable() : void{
		$this->getServer()->getCommandMap()->register("vanillaaltay", new LocateCommand());
		$this->getServer()->getPluginManager()->registerEvents(
			new CommandHintsListener(["locate" => CommandHintsListener::locateOverloads(LocateCommand::getBiomeNames(), StructureRegistry::getNames())]),
			$this
		);
	}
}
