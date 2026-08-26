<?php

declare(strict_types=1);

namespace VanillaAltay\entity\aquatic;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;

use function max;

final class Pufferfish extends WaterMob
{
	private int $puffState = 0;

	private int $threatTicks = 0;

	public static function getNetworkTypeId() : string
	{
		return EntityIds::PUFFERFISH;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(0.8, 0.8);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 3;
	}

	protected function getSwimSpeed() : float
	{
		return 0.13;
	}

	public function getName() : string
	{
		return "Pufferfish";
	}

	public function getDrops() : array
	{
		return [VanillaItems::PUFFERFISH()];
	}

	public function getXpDropAmount() : int
	{
		return 1;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool
	{
		$threats = [];
		foreach ($this->getWorld()->getNearbyEntities($this->getBoundingBox()->expandedCopy(2, 2, 2), $this) as $entity) {
			if ($entity instanceof Living && $entity->isAlive()) {
				$threats[] = $entity;
			}
		}
		if ($threats !== []) {
			$this->threatTicks += $tickDiff;
			$newState = $this->threatTicks >= 40 ? 2 : ($this->threatTicks >= 20 ? 1 : 0);
			if ($newState !== $this->puffState) {
				$this->puffState = $newState;
				$this->networkPropertiesDirty = true;
			}
			if ($this->puffState > 0) {
				foreach ($threats as $threat) {
					if ($this->getPosition()->distanceSquared($threat->getPosition()) <= 1.5 ** 2) {
						$threat->attack(new EntityDamageByEntityEvent($this, $threat, EntityDamageEvent::CAUSE_ENTITY_ATTACK, (float) $this->puffState));
						$threat->getEffects()->add(new EffectInstance(VanillaEffects::POISON(), 60 * $this->puffState, 0));
					}
				}
			}
		} else {
			$this->threatTicks = max(0, $this->threatTicks - ($tickDiff * 2));
			if ($this->threatTicks === 0 && $this->puffState !== 0) {
				$this->puffState = 0;
				$this->networkPropertiesDirty = true;
			}
		}
		return parent::entityBaseTick($tickDiff);
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void
	{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::VARIANT, $this->puffState);
	}
}
