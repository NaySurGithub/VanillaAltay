<?php

declare(strict_types=1);

namespace VanillaAltay\entity\aquatic;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;

use function array_rand;
use function in_array;
use function mt_rand;
use function range;

abstract class ConfiguredWaterMob extends WaterMob
{
	private int $variant = 0;

	protected const NETWORK_ID = "";

	protected const NAME = "Water Mob";

	protected const HEIGHT = 0.6;

	protected const WIDTH = 0.6;

	protected const HEALTH = 10;

	protected const SPEED = 0.1;

	protected function initEntity(CompoundTag $nbt) : void
	{
		$variants = $this->getAvailableVariants();
		$this->variant = $nbt->getInt("Variant", $variants[array_rand($variants)]);
		if (!in_array($this->variant, $variants, true)) {
			$this->variant = $variants[0];
		}parent::initEntity($nbt);
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

	/** @return list<int> */
	protected function getAvailableVariants() : array
	{
		return static::NETWORK_ID === EntityIds::AXOLOTL ? range(0, 4) : [0];
	}

	public static function getNetworkTypeId() : string
	{
		return static::NETWORK_ID;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(static::HEIGHT, static::WIDTH);
	}

	protected function getVanillaMaxHealth() : int
	{
		return static::HEALTH;
	}

	protected function getSwimSpeed() : float
	{
		return static::SPEED;
	}

	public function getName() : string
	{
		return static::NAME;
	}

	public function getDrops() : array
	{
		return match (static::NETWORK_ID) {
			EntityIds::DOLPHIN => [VanillaItems::RAW_FISH()->setCount(mt_rand(0, 1))],EntityIds::NAUTILUS => [VanillaItems::NAUTILUS_SHELL()->setCount(mt_rand(0, 1))],default => [],
		};
	}
}
