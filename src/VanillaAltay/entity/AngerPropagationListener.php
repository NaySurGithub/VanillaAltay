<?php

declare(strict_types=1);

namespace VanillaAltay\entity;

use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use VanillaAltay\entity\monster\ZombiePigman;

final class AngerPropagationListener implements Listener
{
	public function onDamage(EntityDamageByEntityEvent $event) : void
	{
		$victim = $event->getEntity();
		$damager = $event->getDamager();
		if (!$victim instanceof ZombiePigman || !$damager instanceof Living) {
			return;
		}foreach ($victim->getWorld()->getNearbyEntities($victim->getBoundingBox()->expandedCopy(32, 16, 32), $victim) as $entity) {
			if ($entity instanceof ZombiePigman) {
				$entity->setTarget($damager);
			}
		}
	}
}
