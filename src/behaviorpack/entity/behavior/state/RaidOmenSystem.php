<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\state;

use behaviorpack\entity\behavior\EntitySystem;
use function is_array;
use function max;

/**
 * The raid omen components, each applying the state change it names when it
 * becomes active:
 * - add_raid_omen: gives a Raid Omen (of the Bad Omen level, if any) whose
 *   end starts a raid;
 * - gain_raid_omen: turns a Bad Omen into a Raid Omen;
 * - clear_add_raid_omen: removes the Bad Omen and the Raid Omen;
 * - clear_raid_omen_spell_effect: removes the Raid Omen effect only;
 * - remove_raid_trigger: disables minecraft:raid_trigger while active;
 * - trigger_raid: starts a raid at the village or the entity position.
 */
final class RaidOmenSystem extends EntitySystem{

	public function onAdd() : void{
		switch($this->component){
			case "minecraft:add_raid_omen":
				$level = max(1, RaidOmen::getBadOmenLevel($this->entity), RaidOmen::getRaidOmenLevel($this->entity));
				RaidOmen::clearBadOmen($this->entity);
				RaidOmen::addRaidOmen($this->entity, $level);
				break;
			case "minecraft:gain_raid_omen":
				RaidOmen::convertToRaidOmen($this->entity);
				break;
			case "minecraft:clear_add_raid_omen":
				RaidOmen::clearBadOmen($this->entity);
				RaidOmen::clearRaidOmen($this->entity);
				break;
			case "minecraft:clear_raid_omen_spell_effect":
				RaidOmen::clearRaidOmen($this->entity);
				break;
			case "minecraft:remove_raid_trigger":
				$this->entity->setData("raid_trigger_removed", true);
				break;
			case "minecraft:trigger_raid":
				$this->startRaid();
				break;
		}
	}

	public function onRemove() : void{
		if($this->component === "minecraft:remove_raid_trigger"){
			$this->entity->setData("raid_trigger_removed", null);
		}
	}

	public function tick(int $tickDiff) : void{
		if($this->component !== "minecraft:add_raid_omen" && $this->component !== "minecraft:gain_raid_omen"){
			return;
		}
		$level = RaidOmen::getRaidOmenLevel($this->entity);
		if(RaidOmen::tickData($this->entity, $tickDiff)){
			$this->startRaid(max(1, $level));
		}
	}

	/**
	 * Starts a raid: the raid is kept in the data store as its center, level
	 * and start tick, and the Raid Omen is consumed.
	 */
	public function startRaid(?int $level = null) : void{
		$level ??= max(1, RaidOmen::getRaidOmenLevel($this->entity), RaidOmen::getBadOmenLevel($this->entity));
		$village = $this->entity->getData("raid_village");
		$position = $this->entity->getPosition();
		$center = is_array($village) ? $village : [$position->x, $position->y, $position->z];
		RaidOmen::clearRaidOmen($this->entity);
		RaidOmen::clearBadOmen($this->entity);
		$this->entity->setData("raid", [
			"center" => $center,
			"level" => $level,
			"started" => $this->entity->getWorld()->getServer()->getTick()
		]);
		$trigger = $this->config["triggered_event"] ?? null;
		if($trigger !== null){
			$this->entity->runTrigger($trigger);
		}
	}
}
