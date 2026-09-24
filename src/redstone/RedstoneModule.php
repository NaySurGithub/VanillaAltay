<?php

declare(strict_types=1);

namespace redstone;

use pocketmine\crafting\ShapedRecipe;
use pocketmine\crafting\ShapelessRecipe;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\ItemSpawnEvent;
use pocketmine\event\Listener;
use pocketmine\item\ItemTypeIds;
use pocketmine\plugin\PluginBase;
use redstone\block\RedstoneBlockFactory;
use redstone\block\tile\RedstoneTileFactory;
use redstone\inventory\RedstoneInventoryManager;
use redstone\vanilla\ExtraVanillaData;
use redstone\vanilla\ExtraVanillaItems;
use redstone\world\RedstoneWorldManager;
use ReflectionProperty;

final class RedstoneModule implements Listener{

	public function __construct(
		private readonly PluginBase $plugin
	){
	}

	public function load() : void{
		ExtraVanillaData::registerOnAllThreads($this->plugin->getServer()->getAsyncPool());
		RedstoneBlockFactory::init();
		RedstoneTileFactory::init();
	}

	public function enable() : void{
		new RedstoneInventoryManager($this->plugin);
		new RedstoneWorldManager($this->plugin);

		$this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);

		$crafting_manager = $this->plugin->getServer()->getCraftingManager();
		foreach($crafting_manager->getCraftingRecipeIndex() as $recipe){
			if($recipe instanceof ShapedRecipe || $recipe instanceof ShapelessRecipe){
				$results = $recipe->getResults();
				$changed = false;
				foreach($results as $index => $result){
					if($result->getTypeId() === ItemTypeIds::REDSTONE_DUST){
						$results[$index] = ExtraVanillaItems::REDSTONE_DUST()->setCount($result->getCount());
						$changed = true;
					}
				}
				if($changed){
					(new ReflectionProperty($recipe, "results"))->setValue($recipe, $results);
				}
			}
		}
	}

	/**
	 * @param BlockBreakEvent $event
	 * @priority LOWEST
	 */
	public function handleBlockBreak(BlockBreakEvent $event) : void{
		$drops = $event->getDrops();
		$changed = false;
		foreach($drops as $index => $drop){
			if($drop->getTypeId() === ItemTypeIds::REDSTONE_DUST){
				$drops[$index] = ExtraVanillaItems::REDSTONE_DUST()->setCount($drop->getCount());
				$changed = true;
			}
		}
		if($changed){
			$event->setDrops($drops);
		}
	}

	/**
	 * @param ItemSpawnEvent $event
	 * @priority LOWEST
	 */
	public function handleItemSpawn(ItemSpawnEvent $event) : void{
		$entity = $event->getEntity();
		$item = $entity->getItem();
		if($item->getTypeId() === ItemTypeIds::REDSTONE_DUST){
			$pos = $entity->getLocation();
			$mot = $entity->getMotion();
			$entity->flagForDespawn();
			$pos->world->dropItem($pos, ExtraVanillaItems::REDSTONE_DUST()->setCount($item->getCount()), $mot);
		}
	}
}
