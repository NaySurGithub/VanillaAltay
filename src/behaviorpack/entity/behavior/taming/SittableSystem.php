<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\taming;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;

/**
 * minecraft:sittable: the owner makes the entity sit or stand by
 * interacting with it. The state is kept as "sitting".
 */
final class SittableSystem extends EntitySystem{

	public function onAdd() : void{
		$this->entity->setFlag(EntityMetadataFlags::SITTING, self::isSitting($this->entity));
	}

	public function onRemove() : void{
		$this->entity->setFlag(EntityMetadataFlags::SITTING, false);
		$this->entity->setData("sitting", null);
	}

	public static function isSitting(BehaviorEntity $entity) : bool{
		return $entity->getData("sitting", false) === true;
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if(!$this->entity->isOwnedBy($player) || $player->isSneaking() || $this->entity->isRiding()){
			return false;
		}
		if(TamingHelper::isUsedByComponents($this->entity, $player->getInventory()->getItemInHand())){
			return false;
		}
		$this->setSitting(!self::isSitting($this->entity), $player);
		return true;
	}

	public function setSitting(bool $sitting, ?Player $player = null) : void{
		$this->entity->setData("sitting", $sitting ? true : null);
		$this->entity->setFlag(EntityMetadataFlags::SITTING, $sitting);
		$context = $player === null ? [] : ["player" => $player, "other" => $player];
		if($sitting){
			$this->entity->getNavigator()->stop();
			$this->entity->setTargetEntity(null);
			$this->entity->runTrigger($this->config["sit_event"] ?? null, $context);
			return;
		}
		$this->entity->runTrigger($this->config["stand_event"] ?? null, $context);
	}

	public function tick(int $tickDiff) : void{
		if(!self::isSitting($this->entity)){
			return;
		}
		$navigator = $this->entity->getNavigator();
		if(!$navigator->isDone()){
			$navigator->stop();
		}
		$motion = $this->entity->getMotion();
		if($motion->x !== 0.0 || $motion->z !== 0.0){
			$this->entity->setMotion(new Vector3(0, $motion->y, 0));
		}
	}

	public function afterDamage(\pocketmine\event\entity\EntityDamageEvent $source) : void{
		if(self::isSitting($this->entity)){
			$this->setSitting(false);
		}
	}
}
