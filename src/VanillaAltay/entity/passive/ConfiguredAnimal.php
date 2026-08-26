<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;

use function array_rand;
use function in_array;
use function mt_rand;
use function range;

abstract class ConfiguredAnimal extends Animal
{
	private int $variant = 0;

	private int $markVariant = 0;

	protected const NETWORK_ID = "";

	protected const NAME = "Animal";

	protected const HEIGHT = 1.0;

	protected const WIDTH = 1.0;

	protected const HEALTH = 10;

	protected const SPEED = 0.16;

	protected function initEntity(CompoundTag $nbt) : void
	{
		$variants = $this->getAvailableVariants();
		$marks = $this->getAvailableMarkVariants();
		$this->variant = $nbt->getInt("Variant", $variants[array_rand($variants)]);
		if (!in_array($this->variant, $variants, true)) {
			$this->variant = $variants[0];
		}$this->markVariant = $nbt->getInt("MarkVariant", $marks[array_rand($marks)]);
		if (!in_array($this->markVariant, $marks, true)) {
			$this->markVariant = $marks[0];
		}parent::initEntity($nbt);
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void
	{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::VARIANT, $this->variant);
		$properties->setInt(EntityMetadataProperties::MARK_VARIANT, $this->markVariant);
	}

	public function saveNBT() : CompoundTag
	{
		return parent::saveNBT()->setInt("Variant", $this->variant)->setInt("MarkVariant", $this->markVariant);
	}

	/** @return list<int> */
	protected function getAvailableVariants() : array
	{
		return match (static::NETWORK_ID) {
			EntityIds::RABBIT => range(0, 5),EntityIds::HORSE => range(0, 6),EntityIds::LLAMA,EntityIds::TRADER_LLAMA => range(0, 3),EntityIds::CAT => range(0, 10),EntityIds::FOX => [0,1],EntityIds::FROG => range(0, 2),EntityIds::MOOSHROOM => [0,1],EntityIds::VILLAGER_V2 => range(0, 14),default => [0],
		};
	}

	/** @return list<int> */
	protected function getAvailableMarkVariants() : array
	{
		return match (static::NETWORK_ID) {
			EntityIds::HORSE => range(0, 4),EntityIds::VILLAGER_V2 => range(0, 6),default => [0],
		};
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

	protected function getWanderSpeed() : float
	{
		return static::SPEED;
	}

	protected function getBreedingItemTypeIds() : array
	{
		return match (static::NETWORK_ID) {
			EntityIds::RABBIT => [ItemTypeIds::CARROT,ItemTypeIds::GOLDEN_CARROT,VanillaBlocks::DANDELION()->asItem()->getTypeId()],
			EntityIds::HORSE,EntityIds::DONKEY => [ItemTypeIds::GOLDEN_CARROT,ItemTypeIds::GOLDEN_APPLE],
			EntityIds::GOAT,EntityIds::LLAMA,EntityIds::TRADER_LLAMA,EntityIds::MOOSHROOM => [ItemTypeIds::WHEAT],
			EntityIds::CAMEL => [VanillaBlocks::CACTUS()->asItem()->getTypeId()],
			EntityIds::ARMADILLO => [ItemTypeIds::SPIDER_EYE],
			EntityIds::WOLF => [ItemTypeIds::RAW_BEEF,ItemTypeIds::RAW_CHICKEN,ItemTypeIds::RAW_MUTTON,ItemTypeIds::RAW_PORKCHOP],
			EntityIds::CAT,EntityIds::OCELOT => [ItemTypeIds::RAW_FISH,ItemTypeIds::RAW_SALMON],
			EntityIds::FOX => [ItemTypeIds::SWEET_BERRIES,ItemTypeIds::GLOW_BERRIES],
			EntityIds::PANDA => [ItemTypeIds::BAMBOO],
			EntityIds::TURTLE => [VanillaBlocks::SEAGRASS()->asItem()->getTypeId()],
			EntityIds::FROG => [ItemTypeIds::SLIMEBALL],
			EntityIds::SNIFFER => [ItemTypeIds::TORCHFLOWER_SEEDS],
			EntityIds::STRIDER => [VanillaBlocks::WARPED_FUNGUS()->asItem()->getTypeId()],
			default => [],
		};
	}

	public function getName() : string
	{
		return static::NAME;
	}

	public function getDrops() : array
	{
		return match (static::NETWORK_ID) {
			EntityIds::RABBIT => [VanillaItems::RAW_RABBIT(),VanillaItems::RABBIT_HIDE()->setCount(mt_rand(0, 1))],
			EntityIds::HORSE,EntityIds::DONKEY,EntityIds::MULE,EntityIds::LLAMA,EntityIds::TRADER_LLAMA => [VanillaItems::LEATHER()->setCount(mt_rand(0, 2))],
			EntityIds::MOOSHROOM => [VanillaItems::RAW_BEEF()->setCount(mt_rand(1, 3)),VanillaItems::LEATHER()->setCount(mt_rand(0, 2))],
			EntityIds::POLAR_BEAR => mt_rand(0, 1) === 0 ? [VanillaItems::RAW_FISH()->setCount(mt_rand(0, 2))] : [VanillaItems::RAW_SALMON()->setCount(mt_rand(0, 2))],
			EntityIds::STRIDER => [VanillaItems::STRING()->setCount(mt_rand(2, 5))],
			EntityIds::SKELETON_HORSE => [VanillaItems::BONE()->setCount(mt_rand(0, 2))],
			EntityIds::ZOMBIE_HORSE => [VanillaItems::ROTTEN_FLESH()->setCount(mt_rand(0, 2))],
			EntityIds::COPPER_GOLEM => [VanillaItems::COPPER_INGOT()->setCount(mt_rand(1, 3))],
			EntityIds::IRON_GOLEM => [VanillaItems::IRON_INGOT()->setCount(mt_rand(3, 5)),VanillaBlocks::POPPY()->asItem()->setCount(mt_rand(0, 2))],
			EntityIds::SNOW_GOLEM => [VanillaItems::SNOWBALL()->setCount(mt_rand(0, 16))],
			default => [],
		};
	}
}
