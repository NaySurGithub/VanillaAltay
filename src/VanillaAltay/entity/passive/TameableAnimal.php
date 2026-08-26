<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use VanillaAltay\entity\ai\goal\FollowOwnerGoal;
use VanillaAltay\entity\ai\goal\RandomStrollGoal;
use VanillaAltay\entity\ai\GoalSelector;

use function in_array;
use function mt_rand;

abstract class TameableAnimal extends ConfiguredAnimal
{
	private const TAG_OWNER = "VanillaOwnerUuid";

	private const TAG_SITTING = "VanillaSitting";

	private string $ownerUuid = "";

	private bool $sitting = false;

	/** @return list<int> */
	abstract protected function getTamingItemTypeIds() : array;

	protected function registerGoals(GoalSelector $targets, GoalSelector $goals) : void
	{
		$goals->add(2, new FollowOwnerGoal(static::SPEED));
		$goals->add(10, new RandomStrollGoal(static::SPEED * .75, 10));
	}

	protected function initEntity(CompoundTag $nbt) : void
	{
		$this->ownerUuid = $nbt->getString(self::TAG_OWNER, "");
		$this->sitting = $nbt->getByte(self::TAG_SITTING, 0) !== 0;
		parent::initEntity($nbt);
	}

	public function saveNBT() : CompoundTag
	{
		return parent::saveNBT()->setString(self::TAG_OWNER, $this->ownerUuid)->setByte(self::TAG_SITTING, $this->sitting ? 1 : 0);
	}

	public function isOwnedBy(Player $player) : bool
	{
		return $this->ownerUuid !== "" && $player->getUniqueId()->toString() === $this->ownerUuid;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void
	{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::TAMED, $this->ownerUuid !== "");
		$properties->setGenericFlag(EntityMetadataFlags::SITTING, $this->sitting);
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool
	{
		$item = $player->getInventory()->getItemInHand();
		if ($this->ownerUuid === "" && in_array($item->getTypeId(), $this->getTamingItemTypeIds(), true)) {
			if (!$player->isCreative()) {
				$item->pop();
				$player->getInventory()->setItemInHand($item);
			}
			if (mt_rand(1, 3) === 1) {
				$this->ownerUuid = $player->getUniqueId()->toString();
				$this->setOwningEntity($player);
				$this->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::TAMED, true);
			}return true;
		}
		if (parent::onInteract($player, $clickPos)) {
			return true;
		}
		if ($this->isOwnedBy($player)) {
			$this->sitting = !$this->sitting;
			$this->stopHorizontalMovement();
			$this->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SITTING, $this->sitting);
			return true;
		}
		return false;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool
	{
		if ($this->ownerUuid !== "" && $this->getOwningEntity() === null) {
			foreach ($this->getWorld()->getPlayers() as $p) {
				if ($p->getUniqueId()->toString() === $this->ownerUuid) {
					$this->setOwningEntity($p);
					break;
				}
			}
		}if ($this->sitting) {
			$this->stopHorizontalMovement();
		}return parent::entityBaseTick($tickDiff);
	}

	public function walkToward(Vector3 $destination, float $speed) : void
	{
		if (!$this->sitting) {
			parent::walkToward($destination, $speed);
		}
	}
}
