<?php

declare(strict_types=1);

namespace VanillaAltay\entity;

use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use VanillaAltay\entity\passive\Wolf;

final class OwnerCombatListener implements Listener
{
	public function onDamage(EntityDamageByEntityEvent $event) : void
	{
		$damager = $event->getDamager();
		$victim = $event->getEntity();
		$owner = null;
		$target = null;
		if ($damager instanceof Player && $victim instanceof Living) {
			$owner = $damager;
			$target = $victim;
		} elseif ($victim instanceof Player && $damager instanceof Living) {
			$owner = $victim;
			$target = $damager;
		}
		if ($owner === null || $target === null) {
			return;
		}foreach ($owner->getWorld()->getEntities() as $entity) {
			if ($entity instanceof Wolf && $entity->isOwnedBy($owner) && $entity->getPosition()->distanceSquared($owner->getPosition()) <= 32 ** 2) {
				$entity->setTarget($target);
			}
		}
	}
}
