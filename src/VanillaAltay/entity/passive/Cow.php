<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;

use function mt_rand;

final class Cow extends Animal
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::COW;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(1.4, 0.9);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 10;
	}

	protected function getBreedingItemTypeIds() : array
	{
		return [ItemTypeIds::WHEAT];
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool
	{
		if (parent::onInteract($player, $clickPos)) {
			return true;
		}$item = $player->getInventory()->getItemInHand();
		if ($item->getTypeId() !== ItemTypeIds::BUCKET) {
			return false;
		}if (!$player->isCreative()) {
			$item->pop();
			$player->getInventory()->setItemInHand($item);
		}$player->getInventory()->addItem(VanillaItems::MILK_BUCKET());
		return true;
	}

	public function getName() : string
	{
		return "Cow";
	}

	public function getDrops() : array
	{
		return [VanillaItems::RAW_BEEF()->setCount(mt_rand(1, 3)), VanillaItems::LEATHER()->setCount(mt_rand(0, 2))];
	}

	public function getXpDropAmount() : int
	{
		return mt_rand(1, 3);
	}
}
