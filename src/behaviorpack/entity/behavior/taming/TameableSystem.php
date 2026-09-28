<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\taming;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\Utils;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\particle\SmokeParticle;

/**
 * minecraft:tameable: feeding a tame item gives a chance to tame the entity.
 * On success the player becomes the owner and the tame event runs.
 */
final class TameableSystem extends EntitySystem{

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if($this->entity->hasComponent("minecraft:is_tamed") || $this->entity->getData("owner") !== null){
			return false;
		}
		$item = $player->getInventory()->getItemInHand();
		if(!TamingHelper::matches($item, $this->config["tame_items"] ?? [])){
			return false;
		}
		TamingHelper::consumeHeldItem($player);
		$probability = BehaviorEntity::toFloat($this->config["probability"] ?? null, 1.0);
		if(Utils::getRandomFloat() < $probability){
			$this->tame($player);
			return true;
		}
		TamingHelper::particles($this->entity, new SmokeParticle(), 7);
		return true;
	}

	public function tame(Player $player) : void{
		$this->entity->setOwner($player);
		$this->entity->runTrigger($this->config["tame_event"] ?? null, ["player" => $player, "other" => $player]);
		TamingHelper::particles($this->entity, new HeartParticle(), 7);
	}
}
