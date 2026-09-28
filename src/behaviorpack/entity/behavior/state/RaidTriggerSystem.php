<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\state;

use behaviorpack\entity\behavior\EntitySystem;

/**
 * minecraft:raid_trigger: when the entity carries a Bad Omen into a village,
 * the omen becomes a Raid Omen and "triggered_event" runs.
 */
final class RaidTriggerSystem extends EntitySystem{

	private const CHECK_INTERVAL = 100;

	private int $checkTicks = 0;

	public function isRemoved() : bool{
		return $this->entity->getData("raid_trigger_removed") === true;
	}

	public function tick(int $tickDiff) : void{
		$this->checkTicks += $tickDiff;
		if($this->checkTicks < self::CHECK_INTERVAL){
			return;
		}
		$this->checkTicks = 0;
		if($this->isRemoved() || RaidOmen::getBadOmenLevel($this->entity) <= 0){
			return;
		}
		$village = RaidOmen::findVillage($this->entity);
		if($village === null){
			return;
		}
		$this->entity->setData("raid_village", [$village->x, $village->y, $village->z]);
		RaidOmen::convertToRaidOmen($this->entity);
		$trigger = $this->config["triggered_event"] ?? null;
		if($trigger !== null){
			$this->entity->runTrigger($trigger);
		}
	}
}
