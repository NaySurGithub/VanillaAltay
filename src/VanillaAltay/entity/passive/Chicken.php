<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

use function mt_rand;

final class Chicken extends Animal
{
	private int $eggTimer = 0;

	protected function initEntity(CompoundTag $nbt) : void
	{
		$this->eggTimer = $nbt->getInt("EggLayTime", mt_rand(6000, 12000));
		parent::initEntity($nbt);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool
	{
		$changed = parent::entityBaseTick($tickDiff);
		if (!$this->isBaby() && ($this->eggTimer -= $tickDiff) <= 0) {
			$this->getWorld()->dropItem($this->getPosition(), VanillaItems::EGG());
			$this->eggTimer = mt_rand(6000, 12000);
			return true;
		}return $changed;
	}

	public function saveNBT() : CompoundTag
	{
		return parent::saveNBT()->setInt("EggLayTime", $this->eggTimer);
	}

	public static function getNetworkTypeId() : string
	{
		return EntityIds::CHICKEN;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(0.7, 0.4);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 4;
	}

	protected function getBreedingItemTypeIds() : array
	{
		return [ItemTypeIds::WHEAT_SEEDS,ItemTypeIds::BEETROOT_SEEDS,ItemTypeIds::MELON_SEEDS,ItemTypeIds::PUMPKIN_SEEDS,ItemTypeIds::TORCHFLOWER_SEEDS];
	}

	protected function getWanderSpeed() : float
	{
		return 0.2;
	}

	public function getName() : string
	{
		return "Chicken";
	}

	public function getDrops() : array
	{
		return [VanillaItems::RAW_CHICKEN(), VanillaItems::FEATHER()->setCount(mt_rand(0, 2))];
	}

	public function getXpDropAmount() : int
	{
		return mt_rand(1, 3);
	}
}
