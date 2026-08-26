<?php

declare(strict_types=1);

namespace VanillaAltay\entity;

use pocketmine\entity\Location;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\ItemTypeIds;
use VanillaAltay\entity\aquatic\Squid;
use VanillaAltay\entity\monster\Zombie;
use VanillaAltay\entity\passive\Villager;

use function lcg_value;

final class SpawnEggOverrideListener implements Listener
{
	public function onInteract(PlayerInteractEvent $event) : void
	{
		if ($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
			return;
		}
		$class = match ($event->getItem()->getTypeId()) {
			ItemTypeIds::ZOMBIE_SPAWN_EGG => Zombie::class,
			ItemTypeIds::SQUID_SPAWN_EGG => Squid::class,
			ItemTypeIds::VILLAGER_SPAWN_EGG => Villager::class,
			default => null,
		};
		if ($class === null) {
			return;
		}
		$event->setUseItem(false);
		$player = $event->getPlayer();
		$position = $event->getBlock()->getSide($event->getFace())->getPosition()->add(.5, 0, .5);
		$entity = new $class(Location::fromObject($position, $player->getWorld(), lcg_value() * 360, 0));
		$item = $event->getItem();
		if ($item->hasCustomName()) {
			$entity->setNameTag($item->getCustomName());
		}
		$entity->spawnToAll();
		if (!$player->isCreative()) {
			$hand = $player->getInventory()->getItemInHand();
			$hand->pop();
			$player->getInventory()->setItemInHand($hand);
		}
	}
}
