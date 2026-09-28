<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\social;

use behaviorpack\entity\behavior\Goal;
use behaviorpack\entity\BehaviorEntity;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\player\Player;
use function abs;
use function constant;
use function count;
use function defined;
use function is_string;
use function mt_rand;
use function strtoupper;

/**
 * "minecraft:behavior.tempt": follows a player holding one of the tempt
 * items.
 */
class TemptGoal extends Goal{

	private ?Player $player = null;
	private ?Vector3 $lastPlayerPosition = null;
	private int $cooldown = 0;
	private int $soundTicks = 0;

	public function getControls() : int{
		return self::MOVE | self::LOOK;
	}

	private function radius() : float{
		return BehaviorEntity::toFloat($this->config["within_radius"] ?? null, 10.0);
	}

	private function isTempting(Player $player) : bool{
		if($player->isSpectator() || !$player->isAlive() || $player->getWorld() !== $this->entity->getWorld()){
			return false;
		}
		$items = SocialHelper::readNames($this->config["items"] ?? []);
		return SocialHelper::itemMatches($player->getInventory()->getItemInHand(), $items);
	}

	public function canUse() : bool{
		if($this->cooldown > 0){
			--$this->cooldown;
			return false;
		}
		if(count($this->entity->getPassengers()) > 0 && ($this->config["can_tempt_while_ridden"] ?? false) !== true){
			return false;
		}
		if($this->entity->getData("sitting", false) === true){
			return false;
		}
		$radius = $this->radius();
		$best = null;
		$bestDistance = $radius * $radius;
		$vertical = ($this->config["can_tempt_vertically"] ?? false) === true;
		foreach($this->entity->getWorld()->getPlayers() as $player){
			$position = $player->getPosition();
			if(!$vertical && abs($position->y - $this->entity->getPosition()->y) > 4){
				continue;
			}
			$distance = $position->distanceSquared($this->entity->getPosition());
			if($distance < $bestDistance && $this->isTempting($player)){
				$best = $player;
				$bestDistance = $distance;
			}
		}
		$this->player = $best;
		return $best !== null;
	}

	public function canContinue() : bool{
		$player = $this->player;
		if($player === null || !$this->isTempting($player)){
			return false;
		}
		if($player->getPosition()->distanceSquared($this->entity->getPosition()) > $this->radius() ** 2 * 1.5){
			return false;
		}
		if(($this->config["can_get_scared"] ?? false) === true && $this->lastPlayerPosition !== null && $player->getPosition()->distanceSquared($this->lastPlayerPosition) > 0.01){
			if($player->getPosition()->distanceSquared($this->entity->getPosition()) < 36 && abs($player->getLocation()->yaw - $this->entity->getLocation()->yaw) > 5){
				return false;
			}
		}
		return true;
	}

	public function start() : void{
		$this->lastPlayerPosition = null;
		$this->soundTicks = $this->nextSoundDelay();
	}

	public function stop() : void{
		$this->player = null;
		$this->lastPlayerPosition = null;
		$this->cooldown = 100;
		$this->entity->getNavigator()->stop();
	}

	public function tick(int $tickDiff) : void{
		$player = $this->player;
		if($player === null){
			return;
		}
		$this->lastPlayerPosition = $player->getPosition()->asVector3();
		$this->entity->lookAt($player->getEyePos());
		$navigator = $this->entity->getNavigator();
		if($player->getPosition()->distanceSquared($this->entity->getPosition()) < 6.25){
			$navigator->stop();
		}else{
			$navigator->moveTo($player->getPosition(), BehaviorEntity::toFloat($this->config["speed_multiplier"] ?? null, 1.0), 2.5);
		}
		$this->tickSound($tickDiff);
	}

	private function nextSoundDelay() : int{
		$interval = $this->config["sound_interval"] ?? null;
		if($interval === null){
			return 0;
		}
		return (int) (BehaviorEntity::rangeValue($interval, 0.0) * 20);
	}

	private function tickSound(int $tickDiff) : void{
		$sound = $this->config["tempt_sound"] ?? null;
		if(!is_string($sound) || !isset($this->config["sound_interval"])){
			return;
		}
		$this->soundTicks -= $tickDiff;
		if($this->soundTicks > 0){
			return;
		}
		$this->soundTicks = $this->nextSoundDelay() + mt_rand(0, 5);
		$constant = LevelSoundEvent::class . "::" . strtoupper($sound);
		if(!defined($constant)){
			return;
		}
		$packet = LevelSoundEventPacket::nonActorSound(constant($constant), $this->entity->getPosition(), false);
		$this->entity->getWorld()->broadcastPacketToViewers($this->entity->getPosition(), $packet);
	}
}
