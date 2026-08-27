<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\world\Explosion;
use pocketmine\world\Position;
use VanillaAltay\entity\ai\goal\CreeperSwellGoal;
use VanillaAltay\entity\ai\goal\NearestPlayerTargetGoal;
use VanillaAltay\entity\ai\goal\RandomStrollGoal;
use VanillaAltay\entity\ai\GoalSelector;
use VanillaAltay\world\sound\CreeperFuseSound;

use function max;
use function min;
use function mt_rand;

final class Creeper extends Monster
{
	private const FUSE_TICKS = 30;

	private int $swellTicks = 0;

	private int $previousSwellTicks = 0;

	public static function getNetworkTypeId() : string
	{
		return EntityIds::CREEPER;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(1.8, 0.6);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 20;
	}

	protected function registerGoals(GoalSelector $targetGoals, GoalSelector $goals) : void
	{
		$targetGoals->add(1, new NearestPlayerTargetGoal(40));
		$goals->add(1, new CreeperSwellGoal());
		$goals->add(10, new RandomStrollGoal(0.16, 10));
	}

	public function getName() : string
	{
		return "Creeper";
	}

	public function getDrops() : array
	{
		return [VanillaItems::GUNPOWDER()->setCount(mt_rand(0, 2))];
	}

	public function getXpDropAmount() : int
	{
		return 5;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void
	{
		parent::syncNetworkData($properties);

		$properties->setInt(EntityMetadataProperties::CREEPER_SWELL, $this->swellTicks);
		$properties->setInt(EntityMetadataProperties::CREEPER_SWELL_PREVIOUS, $this->previousSwellTicks);
		$properties->setByte(EntityMetadataProperties::CREEPER_SWELL_DIRECTION, $this->swellTicks > 0 ? 1 : -1);
		$properties->setInt(EntityMetadataProperties::FUSE_LENGTH, self::FUSE_TICKS);
	}

	public function startSwelling() : void
	{
		$this->swellTicks = 0;
		$this->previousSwellTicks = 0;
		$this->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::IGNITED, true);
		$this->getNetworkProperties()->setByte(EntityMetadataProperties::CREEPER_SWELL_DIRECTION, 1);
		$this->getNetworkProperties()->setInt(EntityMetadataProperties::FUSE_LENGTH, self::FUSE_TICKS);
		$this->broadcastSound(new CreeperFuseSound());
	}

	public function setSwellTicks(int $ticks) : void
	{
		$this->previousSwellTicks = $this->swellTicks;
		$this->swellTicks = min(self::FUSE_TICKS, max(0, $ticks));

		$this->getNetworkProperties()->setInt(EntityMetadataProperties::CREEPER_SWELL_PREVIOUS, $this->previousSwellTicks);
		$this->getNetworkProperties()->setInt(EntityMetadataProperties::CREEPER_SWELL, $this->swellTicks);
	}

	public function stopSwelling() : void
	{
		$this->swellTicks = 0;
		$this->previousSwellTicks = 0;
		$this->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::IGNITED, false);
		$this->getNetworkProperties()->setInt(EntityMetadataProperties::CREEPER_SWELL, 0);
		$this->getNetworkProperties()->setInt(EntityMetadataProperties::CREEPER_SWELL_PREVIOUS, 0);
		$this->getNetworkProperties()->setByte(EntityMetadataProperties::CREEPER_SWELL_DIRECTION, -1);
	}

	public function explode() : void
	{
		if ($this->isFlaggedForDespawn()) {
			return;
		}
		$event = new EntityPreExplodeEvent($this, 3.0);
		$event->call();
		if ($event->isCancelled()) {
			return;
		}
		$this->flagForDespawn();
		$explosion = new Explosion(Position::fromObject($this->location->add(0, 0.9, 0), $this->getWorld()), $event->getRadius(), $this, $event->getFireChance());
		if ($event->isBlockBreaking()) {
			$explosion->explodeA();
		}
		$explosion->explodeB();
	}
}
