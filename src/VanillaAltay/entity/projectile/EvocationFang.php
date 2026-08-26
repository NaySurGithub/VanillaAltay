<?php

declare(strict_types=1);

namespace VanillaAltay\entity\projectile;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class EvocationFang extends Entity
{
	private int $warmup = 10;

	private bool $attacked = false;

	public function __construct(Location $location, ?Living $owner = null, ?CompoundTag $nbt = null)
	{
		parent::__construct($location, $nbt);
		if ($owner !== null) {
			$this->setOwningEntity($owner);
		}
	}

	public static function getNetworkTypeId() : string
	{
		return EntityIds::EVOCATION_FANG;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(.8, .5);
	}

	protected function getInitialDragMultiplier() : float
	{
		return 0;
	}

	protected function getInitialGravity() : float
	{
		return 0;
	}

	public function getName() : string
	{
		return "Evocation Fang";
	}

	public function getDrops() : array
	{
		return [];
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool
	{
		$changed = parent::entityBaseTick($tickDiff);
		$this->warmup -= $tickDiff;
		if (!$this->attacked && $this->warmup <= 0) {
			$this->attacked = true;
			$owner = $this->getOwningEntity();
			foreach ($this->getWorld()->getNearbyEntities($this->getBoundingBox()->expandedCopy(.5, .5, .5), $this) as $entity) {
				if (!$entity instanceof Living || $entity === $owner) {
					continue;
				}$event = $owner instanceof Living ? new EntityDamageByChildEntityEvent($owner, $this, $entity, EntityDamageEvent::CAUSE_MAGIC, 6) : new EntityDamageByEntityEvent($this, $entity, EntityDamageEvent::CAUSE_MAGIC, 6);
				$entity->attack($event);
			}
		}if ($this->warmup <= -20) {
			$this->flagForDespawn();
		}return $changed;
	}
}
