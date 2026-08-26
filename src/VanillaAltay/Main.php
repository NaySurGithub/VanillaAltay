<?php

declare(strict_types=1);

namespace VanillaAltay;

use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\generator\GeneratorManager;
use VanillaAltay\command\CommandHintsListener;
use VanillaAltay\command\LocateCommand;
use VanillaAltay\command\SummonCommand;
use VanillaAltay\entity\AngerPropagationListener;
use VanillaAltay\entity\mount\MountManager;
use VanillaAltay\entity\OwnerCombatListener;
use VanillaAltay\entity\spawn\NaturalSpawner;
use VanillaAltay\entity\SpawnEggOverrideListener;
use VanillaAltay\entity\VanillaEntityRegistry;
use VanillaAltay\world\biome\VanillaBiomes;
use VanillaAltay\world\generator\overworld\VanillaOverworld;
use VanillaAltay\world\generator\structure\StructureRegistry;

use function array_filter;
use function array_keys;
use function array_values;

final class Main extends PluginBase
{
	/**
	 * Everything has to be in place before the server loads its worlds, which happens right after STARTUP.
	 */
	public function onLoad() : void
	{
		$this->saveDefaultConfig();
		VanillaAltayConfig::load($this->getConfig());
		if (VanillaAltayConfig::entitiesEnabled()) {
			VanillaEntityRegistry::register(VanillaAltayConfig::overrideAltayEntities());
		}

		if (VanillaAltayConfig::customBiomesEnabled()) {
			VanillaBiomes::register();
		}

		if (VanillaAltayConfig::generationEnabled()) {
			GeneratorManager::getInstance()->addGenerator(
				VanillaOverworld::class,
				"vanilla_overworld",
				fn(string $preset) => null,
				true,
			);
			$this->getLogger()->info("Registered the vanilla_overworld generator");
		} else {
			$this->getLogger()->notice("The vanilla_overworld generator is disabled in config.yml");
		}
	}

	public function onEnable() : void
	{
		if (VanillaAltayConfig::mountsEnabled()) {
			$this->getServer()->getPluginManager()->registerEvents(new MountManager(), $this);
		}
		if (VanillaAltayConfig::ownerCombatEnabled()) {
			$this->getServer()->getPluginManager()->registerEvents(new OwnerCombatListener(), $this);
		}
		if (VanillaAltayConfig::angerPropagationEnabled()) {
			$this->getServer()->getPluginManager()->registerEvents(new AngerPropagationListener(), $this);
		}
		if (VanillaAltayConfig::entitySelfTestEnabled()) {
			$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function () : void {
				$world = $this->getServer()->getWorldManager()->getDefaultWorld();
				if ($world === null) {
					$this->getLogger()->error("Entity self-test: no default world");
					return;
				}$errors = VanillaEntityRegistry::selfTest($world);
				if ($errors === []) {
					$this->getLogger()->info("Entity self-test passed");
				} else {
					foreach ($errors as $class => $error) {
						$this->getLogger()->error("Entity self-test $class: $error");
					}
				}
			}), 1);
		}
		if (VanillaAltayConfig::spawnEggOverridesEnabled()) {
			$this->getServer()->getPluginManager()->registerEvents(new SpawnEggOverrideListener(), $this);
		}
		if (VanillaAltayConfig::naturalSpawningEnabled()) {
			$spawner = new NaturalSpawner(
				array_values(array_filter(VanillaEntityRegistry::getSpawnRules(), static fn($rule) : bool => VanillaAltayConfig::entitySpawnEnabled($rule->identifier))),
				VanillaAltayConfig::monsterCap(),
				VanillaAltayConfig::creatureCap(),
				VanillaAltayConfig::waterCreatureCap(),
				VanillaAltayConfig::ambientCap(),
			);
			$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function () use ($spawner) : void {
				foreach ($this->getServer()->getWorldManager()->getWorlds() as $world) {
					$spawner->tick($world);
				}
			}), VanillaAltayConfig::spawnInterval());
		}

		if (VanillaAltayConfig::locateCommandEnabled()) {
			$this->getServer()->getCommandMap()->register("vanillaaltay", new LocateCommand());
		}
		if (VanillaAltayConfig::summonCommandEnabled()) {
			$this->getServer()->getCommandMap()->register("vanillaaltay", new SummonCommand());
		}

		$commandOverloads = [];
		if (VanillaAltayConfig::locateCommandEnabled() && VanillaAltayConfig::commandHintsEnabled()) {
			$commandOverloads["locate"] = CommandHintsListener::locateOverloads(
				LocateCommand::getBiomeNames(),
				array_keys(VanillaAltayConfig::filterStructures(StructureRegistry::all())),
			);
		}
		if (VanillaAltayConfig::summonCommandHintsEnabled()) {
			$commandOverloads["summon"] = CommandHintsListener::summonOverloads(
				VanillaEntityRegistry::getSummonIdentifiers(),
			);
		}
		if ($commandOverloads !== []) {
			$this->getServer()->getPluginManager()->registerEvents(
				new CommandHintsListener($commandOverloads),
				$this,
			);
		}
	}
}
