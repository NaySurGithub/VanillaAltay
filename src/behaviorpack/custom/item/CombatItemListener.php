<?php

declare(strict_types=1);

namespace behaviorpack\custom\item;

use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerMissSwingEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use function array_filter;
use function array_values;
use function count;

/**
 * Plays minecraft:swing_sounds and performs minecraft:piercing_weapon
 * attacks for behavior pack items.
 */
final class CombatItemListener{

	private bool $piercing = false;

	private function __construct(){
	}

	public static function register(PluginBase $plugin) : void{
		$listener = new self();
		$manager = $plugin->getServer()->getPluginManager();
		$manager->registerEvent(EntityDamageByEntityEvent::class, function(EntityDamageByEntityEvent $event) use ($listener) : void{
			$listener->onAttack($event);
		}, EventPriority::MONITOR, $plugin);
		$manager->registerEvent(PlayerMissSwingEvent::class, function(PlayerMissSwingEvent $event) use ($listener) : void{
			$listener->onMissSwing($event);
		}, EventPriority::MONITOR, $plugin);
	}

	private function onAttack(EntityDamageByEntityEvent $event) : void{
		$attacker = $event->getDamager();
		if($event->isCancelled() || $event->getCause() !== EntityDamageEvent::CAUSE_ENTITY_ATTACK || !$attacker instanceof Player){
			return;
		}
		$item = $attacker->getInventory()->getItemInHand();
		if(!$item instanceof CombatItem){
			return;
		}
		$victim = $event->getEntity();
		$sound = null;
		if($event->getModifier(EntityDamageEvent::MODIFIER_CRITICAL) > 0){
			$sound = $item->getSwingSound(CombatItem::SOUND_CRITICAL_HIT);
		}
		$sound ??= $item->getSwingSound(CombatItem::SOUND_HIT);
		if($sound !== null){
			$this->playSound($attacker, $victim, $sound);
		}

		$weapon = $item->getPiercingWeapon();
		if($weapon === null || $this->piercing){
			return;
		}
		$this->piercing = true;
		try{
			$weapon->attackAlong($attacker, $item, $victim);
		}finally{
			$this->piercing = false;
		}
	}

	private function onMissSwing(PlayerMissSwingEvent $event) : void{
		if($event->isCancelled()){
			return;
		}
		$player = $event->getPlayer();
		$item = $player->getInventory()->getItemInHand();
		if(!$item instanceof CombatItem){
			return;
		}
		$weapon = $item->getPiercingWeapon();
		if($weapon !== null && !$player->isSpectator() && count($weapon->findTargets($player)) > 0){
			$this->piercing = true;
			try{
				$weapon->attackAlong($player, $item, null);
			}finally{
				$this->piercing = false;
			}
			return;
		}
		$sound = $item->getSwingSound(CombatItem::SOUND_MISS);
		if($sound !== null){
			$this->playSound($player, $player, $sound);
		}
	}

	/**
	 * The attacker's client plays its own swing sounds, so only the other
	 * viewers receive it.
	 */
	private function playSound(Player $attacker, Entity $source, string $sound) : void{
		$position = $source->getPosition();
		$world = $position->getWorld();
		$viewers = array_values(array_filter($world->getViewersForPosition($position), static function(Player $viewer) use ($attacker) : bool{
			return $viewer !== $attacker;
		}));
		if(count($viewers) > 0){
			$world->addSound($position, new NamedSound($sound), $viewers);
		}
	}
}
