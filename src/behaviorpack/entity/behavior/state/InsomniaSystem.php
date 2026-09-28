<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\state;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use function is_int;
use function max;

/**
 * minecraft:insomnia: counts the days since the entity last rested; after
 * "days_until_insomnia" days it is insomniac (phantoms may spawn).
 */
final class InsomniaSystem extends EntitySystem{

	private const DATA_LAST_REST = "insomnia_last_rest";
	private const DAY_LENGTH = 24000;

	private int $checkTicks = 0;

	public function getDaysUntilInsomnia() : float{
		return max(0.0, BehaviorEntity::toFloat($this->config["days_until_insomnia"] ?? null, 3.0));
	}

	public function onAdd() : void{
		if(!is_int($this->entity->getData(self::DATA_LAST_REST))){
			$this->markRested();
		}
	}

	public function onRemove() : void{
		$this->entity->setData(self::DATA_LAST_REST, null);
		$this->entity->setData("insomnia_days", null);
		$this->entity->setData("insomniac", null);
	}

	public function markRested() : void{
		$this->entity->setData(self::DATA_LAST_REST, $this->entity->getWorld()->getTime());
		$this->entity->setData("insomnia_days", 0.0);
		$this->entity->setData("insomniac", null);
	}

	public function getDaysSinceRest() : float{
		$last = $this->entity->getData(self::DATA_LAST_REST);
		if(!is_int($last)){
			return 0.0;
		}
		return max(0.0, ($this->entity->getWorld()->getTime() - $last) / self::DAY_LENGTH);
	}

	public function isInsomniac() : bool{
		return $this->getDaysSinceRest() >= $this->getDaysUntilInsomnia();
	}

	public function tick(int $tickDiff) : void{
		$this->checkTicks += $tickDiff;
		if($this->checkTicks < 20){
			return;
		}
		$this->checkTicks = 0;
		if($this->entity->getFlag(EntityMetadataFlags::SLEEPING)){
			$this->markRested();
			return;
		}
		$this->entity->setData("insomnia_days", $this->getDaysSinceRest());
		$this->entity->setData("insomniac", $this->isInsomniac() ? true : null);
	}
}
