<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\movement;

use pocketmine\entity\Living;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use function abs;
use function is_array;
use function mt_rand;

/**
 * Sleeps in place while the nap filters pass and no disturbing mob is near.
 */
final class NapGoal extends MovementGoal{

	private int $cooldown = 0;
	private int $checkTimer = 0;

	public function getControls() : int{
		return self::MOVE | self::LOOK | self::JUMP;
	}

	public function canUse() : bool{
		if($this->cooldown > 0){
			--$this->cooldown;
			return false;
		}
		if(!$this->entity->isOnGround() || $this->entity->isInWater()){
			return false;
		}
		return $this->canNap() && !$this->isDisturbed();
	}

	private function canNap() : bool{
		$filters = $this->config["can_nap_filters"] ?? null;
		return !is_array($filters) || $this->entity->testFilter($filters);
	}

	private function isDisturbed() : bool{
		$distance = $this->num("mob_detect_dist", 6.0);
		$height = $this->num("mob_detect_height", 6.0);
		$exceptions = $this->config["wake_mob_exceptions"] ?? null;
		$position = $this->entity->getPosition();
		foreach($this->entity->getWorld()->getNearbyEntities($this->entity->getBoundingBox()->expandedCopy($distance, $height, $distance), $this->entity) as $entity){
			if(!$entity instanceof Living || !$entity->isAlive()){
				continue;
			}
			if($entity instanceof Player && ($entity->isSpectator() || $entity->isSneaking())){
				continue;
			}
			if(abs($entity->getPosition()->y - $position->y) > $height){
				continue;
			}
			if(is_array($exceptions) && $this->entity->testFilter($exceptions, ["other" => $entity])){
				continue;
			}
			return true;
		}
		return false;
	}

	public function canContinue() : bool{
		if(--$this->checkTimer > 0){
			return true;
		}
		$this->checkTimer = 20;
		return $this->canNap() && !$this->isDisturbed() && !$this->entity->isInWater();
	}

	public function start() : void{
		$this->checkTimer = 20;
		$this->entity->getNavigator()->stop();
		$this->entity->setFlag(EntityMetadataFlags::SLEEPING, true);
		$this->entity->setData("napping", true);
	}

	public function stop() : void{
		$this->entity->setFlag(EntityMetadataFlags::SLEEPING, false);
		$this->entity->setData("napping", null);
		$min = $this->num("cooldown_min", 0.0);
		$max = $this->num("cooldown_max", 0.0);
		$this->cooldown = mt_rand((int) ($min * 20), (int) (($max < $min ? $min : $max) * 20));
	}
}
