<?php

declare(strict_types=1);

namespace VanillaAltay\entity\flying;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;

use function max;
use function min;
use function mt_rand;

final class Parrot extends FlyingMob
{
	private int $variant = 0;

	protected function initEntity(CompoundTag $nbt) : void
	{
		$this->variant = max(0, min(4, $nbt->getInt("Variant", mt_rand(0, 4))));
		parent::initEntity($nbt);
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void
	{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::VARIANT, $this->variant);
	}

	public function saveNBT() : CompoundTag
	{
		return parent::saveNBT()->setInt("Variant", $this->variant);
	}

	public static function getNetworkTypeId() : string
	{
		return EntityIds::PARROT;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(1.0, 0.5);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 6;
	}

	protected function getFlySpeed() : float
	{
		return 0.2;
	}

	public function getName() : string
	{
		return "Parrot";
	}

	public function getDrops() : array
	{
		return [];
	}

	public function getXpDropAmount() : int
	{
		return 1;
	}
}
