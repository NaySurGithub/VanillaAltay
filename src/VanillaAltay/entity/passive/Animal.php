<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use VanillaAltay\entity\ai\goal\RandomStrollGoal;
use VanillaAltay\entity\ai\GoalSelector;
use VanillaAltay\entity\IntelligentMob;

use function in_array;
use function max;
use function min;

abstract class Animal extends IntelligentMob
{
	private const TAG_AGE = "VanillaAge";

	private const TAG_LOVE = "VanillaLove";

	private int $age = 0;

	private int $loveTicks = 0;

	protected function initEntity(CompoundTag $nbt) : void
	{
		$this->age = $nbt->getInt(self::TAG_AGE, 0);
		$this->loveTicks = $nbt->getInt(self::TAG_LOVE, 0);
		parent::initEntity($nbt);
		if ($this->age < 0) {
			$this->setScale(.5);
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool
	{
		$changed = parent::entityBaseTick($tickDiff);
		if ($this->age < 0) {
			$this->age = min(0, $this->age + $tickDiff);
			if ($this->age === 0) {
				$this->setScale(1);
			}$changed = true;
		}
		if ($this->loveTicks > 0) {
			$this->loveTicks = max(0, $this->loveTicks - $tickDiff);
		}
		return $changed;
	}

	public function saveNBT() : CompoundTag
	{
		return parent::saveNBT()->setInt(self::TAG_AGE, $this->age)->setInt(self::TAG_LOVE, $this->loveTicks);
	}

	public function isBaby() : bool
	{
		return $this->age < 0;
	}

	/** @return list<int> */
	protected function getBreedingItemTypeIds() : array
	{
		return [];
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool
	{
		$item = $player->getInventory()->getItemInHand();
		if ($this->age < 0 || !in_array($item->getTypeId(), $this->getBreedingItemTypeIds(), true)) {
			return false;
		}
		if (!$player->isCreative()) {
			$item->pop();
			$player->getInventory()->setItemInHand($item);
		}
		$this->loveTicks = 600;
		foreach ($this->getWorld()->getNearbyEntities($this->getBoundingBox()->expandedCopy(8, 4, 8), $this) as $other) {
			if ($other::class !== static::class || !$other instanceof self || $other->loveTicks <= 0 || $other->age !== 0) {
				continue;
			}
			$child = new static(Location::fromObject($this->getPosition()->add(.5, 0, .5), $this->getWorld()));
			$child->age = -24000;
			$child->setScale(.5);
			$child->spawnToAll();
			$this->loveTicks = 0;
			$other->loveTicks = 0;
			$this->age = $other->age = 6000;
			break;
		}
		return true;
	}

	protected function registerGoals(GoalSelector $targetGoals, GoalSelector $goals) : void
	{
		$goals->add(10, new RandomStrollGoal($this->getWanderSpeed(), 10));
	}

	protected function getWanderSpeed() : float
	{
		return 0.16;
	}
}
