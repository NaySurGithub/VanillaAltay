<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\taming;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use behaviorpack\loot\LootItems;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use function max;
use function min;

/**
 * minecraft:balloonable: a balloon tied to the entity lifts it, more slowly
 * the heavier it is. The balloon holder is kept as "balloon" (player name).
 */
final class BalloonableSystem extends EntitySystem{

	public const BALLOON = "minecraft:balloon";

	public function isBallooned() : bool{
		return $this->entity->getData("balloon") !== null;
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if($this->isBallooned()){
			if($player->getInventory()->getItemInHand()->isNull()){
				$this->unballoon($player, true);
				return true;
			}
			return false;
		}
		if(!TamingHelper::isItem($player->getInventory()->getItemInHand(), self::BALLOON)){
			return false;
		}
		TamingHelper::consumeHeldItem($player);
		$this->balloon($player);
		return true;
	}

	public function balloon(Player $player) : void{
		$this->entity->setData("balloon", $player->getName());
		$this->entity->runTrigger($this->config["on_balloon"] ?? null, ["player" => $player, "other" => $player]);
	}

	public function unballoon(?Player $player, bool $dropBalloon) : void{
		$this->entity->setData("balloon", null);
		if($dropBalloon){
			$item = LootItems::resolve(self::BALLOON);
			if($item !== null){
				$this->entity->getWorld()->dropItem($this->entity->getPosition(), $item);
			}
		}
		$context = $player === null ? [] : ["player" => $player, "other" => $player];
		$this->entity->runTrigger($this->config["on_unballoon"] ?? null, $context);
	}

	public function onRemove() : void{
		if($this->isBallooned()){
			$this->unballoon(null, true);
		}
	}

	public function onDeath() : void{
		if($this->isBallooned()){
			$this->unballoon(null, true);
		}
	}

	public function tick(int $tickDiff) : void{
		if(!$this->isBallooned()){
			return;
		}
		$mass = max(0.1, BehaviorEntity::toFloat($this->config["mass"] ?? null, 1.0));
		$motion = $this->entity->getMotion();
		$lift = min(0.2, 0.08 / $mass);
		$this->entity->setMotion(new Vector3($motion->x, min($motion->y + $lift, 0.3 / $mass), $motion->z));
	}
}
