<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\combat;

use behaviorpack\entity\behavior\combat\DamageCause;
use pocketmine\block\Water;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use function floor;
use function is_array;
use function is_string;
use function mt_rand;

/**
 * minecraft:behavior.panic: runs around at random after taking damage from
 * one of "damage_sources", toward water when burning and "prefer_water" is
 * set.
 */
class PanicGoal extends CombatGoal{

	private const DEFAULT_SOURCES = [
		"campfire", "fire", "fire_tick", "freezing", "lava", "lightning", "magma", "soul_campfire", "temperature",
		"entity_attack", "entity_explosion", "fireworks", "magic", "projectile", "ram_attack", "sonic_boom", "wither", "mace_smash"
	];

	private const PANIC_TICKS = 100;

	private int $handledHurtTick = -1;
	private int $panicUntilTick = 0;

	public function canUse() : bool{
		$event = $this->entity->getLastDamageCause();
		$hurtTick = $this->entity->getLastHurtTick();
		$burning = $this->entity->isOnFire();
		if($event === null || ($hurtTick === $this->handledHurtTick && !$burning)){
			return false;
		}
		if($hurtTick !== $this->handledHurtTick){
			$this->handledHurtTick = $hurtTick;
			if($this->now() - $hurtTick > 5 || !$this->matchesSource($event)){
				return false;
			}
		}elseif(!$this->matchesSource($event)){
			return false;
		}
		return true;
	}

	private function matchesSource(EntityDamageEvent $event) : bool{
		if($event instanceof EntityDamageByEntityEvent && $this->bool("ignore_mob_damage", false)){
			$damager = $event->getDamager();
			if($damager !== null && !$damager instanceof Player){
				return false;
			}
		}
		$sources = $this->config["damage_sources"] ?? self::DEFAULT_SOURCES;
		if(is_string($sources)){
			$sources = [$sources];
		}
		if(!is_array($sources)){
			return true;
		}
		foreach($sources as $source){
			if(DamageCause::matches($source, $event->getCause())){
				return true;
			}
		}
		return false;
	}

	public function canContinue() : bool{
		if($this->entity->getLastHurtTick() !== $this->handledHurtTick){
			$this->handledHurtTick = $this->entity->getLastHurtTick();
			$this->panicUntilTick = $this->now() + self::PANIC_TICKS;
		}
		return $this->now() < $this->panicUntilTick || !$this->entity->getNavigator()->isDone();
	}

	public function isInterruptable() : bool{
		return !$this->bool("force", false);
	}

	public function start() : void{
		$this->panicUntilTick = $this->now() + self::PANIC_TICKS;
		$this->pickDestination();
	}

	public function stop() : void{
		$this->entity->getNavigator()->stop();
	}

	public function tick(int $tickDiff) : void{
		if($this->entity->getNavigator()->isDone() && $this->now() < $this->panicUntilTick){
			$this->pickDestination();
		}
	}

	private function pickDestination() : void{
		$destination = null;
		if($this->entity->isOnFire() && $this->bool("prefer_water", false)){
			$destination = $this->findWater();
		}
		if($destination === null){
			$position = $this->entity->getPosition();
			$destination = new Vector3($position->x + mt_rand(-5, 5), $position->y + mt_rand(-4, 4), $position->z + mt_rand(-5, 5));
		}
		$this->entity->getNavigator()->moveTo($destination, $this->float("speed_multiplier", 1.25), 0.5);
	}

	private function findWater() : ?Vector3{
		$position = $this->entity->getPosition();
		$world = $this->entity->getWorld();
		$baseX = (int) floor($position->x);
		$baseY = (int) floor($position->y);
		$baseZ = (int) floor($position->z);
		$best = null;
		$bestDistance = 0.0;
		for($x = -5; $x <= 5; $x++){
			for($z = -5; $z <= 5; $z++){
				for($y = -2; $y <= 2; $y++){
					if(!$world->getBlockAt($baseX + $x, $baseY + $y, $baseZ + $z) instanceof Water){
						continue;
					}
					$distance = $x * $x + $y * $y + $z * $z;
					if($best === null || $distance < $bestDistance){
						$best = new Vector3($baseX + $x + 0.5, $baseY + $y, $baseZ + $z + 0.5);
						$bestDistance = $distance;
					}
				}
			}
		}
		return $best;
	}
}
