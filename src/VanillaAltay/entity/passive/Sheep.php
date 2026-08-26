<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\DyeColorIdMap;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\Durable;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;

use function max;
use function min;
use function mt_rand;

final class Sheep extends Animal
{
	private bool $sheared = false;

	private int $regrowTimer = 0;

	private int $woolColor = 0;

	protected function initEntity(CompoundTag $nbt) : void
	{
		$this->sheared = $nbt->getByte("Sheared", 0) !== 0;
		$this->regrowTimer = $nbt->getInt("WoolRegrowTime", mt_rand(600, 1200));
		$roll = mt_rand(1, 1000);
		$default = $roll <= 5 ? 6 : ($roll <= 55 ? 15 : ($roll <= 105 ? 7 : ($roll <= 155 ? 8 : ($roll <= 205 ? 12 : 0))));
		$this->woolColor = max(0, min(15, $nbt->getByte("Color", $default)));
		parent::initEntity($nbt);
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void
	{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::SHEARED, $this->sheared);
		$properties->setByte(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties::COLOR, $this->woolColor);
	}

	public function saveNBT() : CompoundTag
	{
		return parent::saveNBT()->setByte("Sheared", $this->sheared ? 1 : 0)->setInt("WoolRegrowTime", $this->regrowTimer)->setByte("Color", $this->woolColor);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool
	{
		$changed = parent::entityBaseTick($tickDiff);
		if ($this->sheared && ($this->regrowTimer -= $tickDiff) <= 0 && $this->getWorld()->getBlock($this->getPosition()->down())->getTypeId() === \pocketmine\block\BlockTypeIds::GRASS) {
			$this->sheared = false;
			$this->regrowTimer = mt_rand(600, 1200);
			$this->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SHEARED, false);
			$this->getWorld()->setBlock($this->getPosition()->down(), VanillaBlocks::DIRT());
			return true;
		}return $changed;
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool
	{
		if (parent::onInteract($player, $clickPos)) {
			return true;
		}$item = $player->getInventory()->getItemInHand();
		if ($this->isBaby() || $this->sheared || $item->getTypeId() !== ItemTypeIds::SHEARS) {
			return false;
		}$this->sheared = true;
		$this->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SHEARED, true);
		$color = DyeColorIdMap::getInstance()->fromId($this->woolColor);
		$wool = VanillaBlocks::WOOL();
		if ($color !== null) {
			$wool->setColor($color);
		}$this->getWorld()->dropItem($this->getPosition(), $wool->asItem()->setCount(mt_rand(1, 3)));
		if (!$player->isCreative() && $item instanceof Durable) {
			$item->applyDamage(1);
			$player->getInventory()->setItemInHand($item);
		}return true;
	}

	public static function getNetworkTypeId() : string
	{
		return EntityIds::SHEEP;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(1.3, 0.9);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 8;
	}

	protected function getBreedingItemTypeIds() : array
	{
		return [ItemTypeIds::WHEAT];
	}

	public function getName() : string
	{
		return "Sheep";
	}

	public function getDrops() : array
	{
		$drops = [VanillaItems::RAW_MUTTON()->setCount(mt_rand(1, 2))];
		if (!$this->sheared) {
			$wool = VanillaBlocks::WOOL();
			$color = DyeColorIdMap::getInstance()->fromId($this->woolColor);
			if ($color !== null) {
				$wool->setColor($color);
			}$drops[] = $wool->asItem();
		}return $drops;
	}

	public function getXpDropAmount() : int
	{
		return mt_rand(1, 3);
	}
}
