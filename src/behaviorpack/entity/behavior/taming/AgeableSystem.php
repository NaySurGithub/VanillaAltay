<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\taming;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use behaviorpack\loot\LootItems;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\particle\HappyVillagerParticle;
use function is_array;
use function is_int;
use function is_string;
use function max;

/**
 * minecraft:ageable: the baby grows up once its age reaches the duration.
 * Feeding one of the feed items adds its growth share of the duration. The
 * age is kept in ticks as "age_ticks".
 */
final class AgeableSystem extends EntitySystem{

	private const DEFAULT_GROWTH = 0.1;

	public function getDurationTicks() : int{
		return max(1, (int) (BehaviorEntity::toFloat($this->config["duration"] ?? null, 1200.0) * 20));
	}

	public function getAgeTicks() : int{
		$age = $this->entity->getData("age_ticks", 0);
		return is_int($age) ? $age : 0;
	}

	public function onRemove() : void{
		$this->entity->setData("age_ticks", null);
	}

	public function tick(int $tickDiff) : void{
		$this->addAge($tickDiff);
	}

	public function addAge(int $ticks) : void{
		$age = $this->getAgeTicks() + $ticks;
		if($age >= $this->getDurationTicks()){
			$this->growUp();
			return;
		}
		$this->entity->setData("age_ticks", $age);
	}

	public function growUp() : void{
		$this->entity->setData("age_ticks", null);
		$world = $this->entity->getWorld();
		$drops = $this->config["drop_items"] ?? [];
		foreach(is_array($drops) ? $drops : [$drops] as $drop){
			if(is_string($drop)){
				$item = LootItems::resolve(TamingHelper::normalizeItemName($drop));
				if($item !== null){
					$world->dropItem($this->entity->getPosition(), $item);
				}
			}
		}
		$this->entity->runTrigger($this->config["grow_up"] ?? null);
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$entry = TamingHelper::findItem($player->getInventory()->getItemInHand(), $this->config["feed_items"] ?? []);
		if($entry === null){
			return false;
		}
		$filters = $this->config["interact_filters"] ?? null;
		if(is_array($filters) && !$this->entity->testFilter($filters, ["player" => $player, "other" => $player])){
			return false;
		}
		$growth = BehaviorEntity::toFloat($entry["growth"] ?? null, self::DEFAULT_GROWTH);
		TamingHelper::consumeHeldItem($player);
		$transform = $this->config["transform_to_item"] ?? null;
		if(is_string($transform)){
			TamingHelper::transformHeldItem($player, $transform);
		}
		TamingHelper::particles($this->entity, new HappyVillagerParticle(), 5);
		$this->addAge((int) ($growth * $this->getDurationTicks()));
		return true;
	}
}
