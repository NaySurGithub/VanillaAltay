<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\movement;

use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use function is_array;

/**
 * Croaks for a while at random intervals while the filters pass.
 */
final class CroakGoal extends MovementGoal{

	private int $cooldown = -1;
	private int $remaining = 0;

	public function getControls() : int{
		return self::MOVE | self::LOOK;
	}

	public function canUse() : bool{
		if($this->cooldown < 0){
			$this->cooldown = (int) ($this->range("interval", 10.0) * 20);
		}
		if($this->cooldown > 0){
			--$this->cooldown;
			return false;
		}
		if(!$this->entity->isOnGround() && !$this->entity->isInWater()){
			return false;
		}
		$filters = $this->config["filters"] ?? null;
		return !is_array($filters) || $this->entity->testFilter($filters);
	}

	public function canContinue() : bool{
		return $this->remaining > 0;
	}

	public function start() : void{
		$this->remaining = (int) ($this->num("duration", 4.5) * 20);
		$this->entity->getNavigator()->stop();
		$this->entity->setFlag(EntityMetadataFlags::CROAKING, true);
		$this->entity->setData("croaking", true);
		$position = $this->entity->getPosition();
		$this->entity->getWorld()->broadcastPacketToViewers($position, LevelSoundEventPacket::create(
			LevelSoundEvent::AMBIENT,
			$position,
			-1,
			$this->entity->getIdentifier(),
			false,
			false,
			$this->entity->getId(),
			null
		));
	}

	public function stop() : void{
		$this->remaining = 0;
		$this->cooldown = (int) ($this->range("interval", 10.0) * 20);
		$this->entity->setFlag(EntityMetadataFlags::CROAKING, false);
		$this->entity->setData("croaking", null);
	}

	public function tick(int $tickDiff) : void{
		$this->remaining -= $tickDiff;
	}
}
