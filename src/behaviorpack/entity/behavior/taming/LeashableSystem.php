<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\taming;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use behaviorpack\loot\LootItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use function is_string;
use function max;
use function min;
use function sqrt;

/**
 * minecraft:leashable: a player holding a lead ties the entity to itself.
 * The entity is pulled back past the soft distance and the lead breaks past
 * the max distance. The holder is kept by name as "leash_holder".
 */
final class LeashableSystem extends EntitySystem{

	public const LEAD = "minecraft:lead";

	public function onAdd() : void{
		$this->syncHolder($this->getHolder());
	}

	public function onRemove() : void{
		if($this->entity->getData("leash_holder") !== null){
			$this->unleash(null, true);
		}
	}

	public function onDeath() : void{
		if($this->entity->getData("leash_holder") !== null){
			$this->unleash(null, true);
		}
	}

	public function getHolder() : ?Player{
		$name = $this->entity->getData("leash_holder");
		if(!is_string($name)){
			return null;
		}
		return $this->entity->getWorld()->getServer()->getPlayerExact($name);
	}

	public function isLeashed() : bool{
		return $this->entity->getData("leash_holder") !== null;
	}

	private function syncHolder(?Player $holder) : void{
		$this->entity->setFlag(EntityMetadataFlags::LEASHED, $holder !== null);
		$this->entity->setMetadata(EntityMetadataProperties::LEAD_HOLDER_EID, "long", $holder?->getId() ?? -1);
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$holding = TamingHelper::isItem($player->getInventory()->getItemInHand(), self::LEAD);
		$holder = $this->entity->getData("leash_holder");
		if($holder === null){
			if(!$holding || $this->entity->isRiding()){
				return false;
			}
			TamingHelper::consumeHeldItem($player);
			$this->leash($player);
			return true;
		}
		if($holder === $player->getName()){
			$this->unleash($player, false);
			if($player->hasFiniteResources()){
				$lead = LootItems::resolve(self::LEAD);
				if($lead !== null){
					TamingHelper::giveItem($player, $lead);
				}
			}
			return true;
		}
		if($holding && ($this->config["can_be_stolen"] ?? false) === true){
			TamingHelper::consumeHeldItem($player);
			$this->unleash($player, true);
			$this->leash($player);
			return true;
		}
		return false;
	}

	public function leash(Player $holder) : void{
		$this->entity->setData("leash_holder", $holder->getName());
		$this->syncHolder($holder);
		$this->entity->runTrigger($this->config["on_leash"] ?? null, ["player" => $holder, "other" => $holder]);
	}

	/**
	 * Releases the entity, dropping a lead where it stands when asked.
	 */
	public function unleash(?Player $player, bool $dropLead) : void{
		$this->entity->setData("leash_holder", null);
		$this->syncHolder(null);
		if($dropLead){
			$lead = LootItems::resolve(self::LEAD);
			if($lead !== null){
				$this->entity->getWorld()->dropItem($this->entity->getPosition(), $lead);
			}
		}
		$context = $player === null ? [] : ["player" => $player, "other" => $player];
		$this->entity->runTrigger($this->config["on_unleash"] ?? null, $context);
	}

	public function tick(int $tickDiff) : void{
		if(!$this->isLeashed()){
			return;
		}
		$holder = $this->getHolder();
		if($holder === null || !$holder->isAlive() || $holder->getWorld() !== $this->entity->getWorld()){
			$this->unleash(null, true);
			return;
		}
		$this->syncHolder($holder);
		$soft = BehaviorEntity::toFloat($this->config["soft_distance"] ?? null, 4.0);
		$hard = max($soft, BehaviorEntity::toFloat($this->config["hard_distance"] ?? null, 6.0));
		$maxDistance = max($hard, BehaviorEntity::toFloat($this->config["max_distance"] ?? null, 10.0));
		$from = $this->entity->getPosition();
		$to = $holder->getPosition();
		$dx = $to->x - $from->x;
		$dy = $to->y - $from->y;
		$dz = $to->z - $from->z;
		$distance = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
		if($distance > $maxDistance){
			$this->unleash($holder, true);
			return;
		}
		if($distance <= $soft || $distance < 0.0001){
			return;
		}
		$this->entity->getNavigator()->moveTo($to, 1.0, $soft);
		$strength = $distance > $hard ? 0.4 : min(0.4, ($distance - $soft) * 0.08);
		$motion = $this->entity->getMotion();
		$this->entity->setMotion(new Vector3(
			$motion->x + $dx / $distance * $strength,
			$motion->y + ($dy > 0 ? $dy / $distance * $strength * 0.5 : 0.0),
			$motion->z + $dz / $distance * $strength
		));
	}
}
