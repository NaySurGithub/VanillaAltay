<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

use function mt_rand;

class Spider extends Monster
{
	protected function initEntity(\pocketmine\nbt\tag\CompoundTag $nbt) : void
	{
		parent::initEntity($nbt);
		$this->setCanClimbWalls(true);
	}

	protected function registerGoals(\VanillaAltay\entity\ai\GoalSelector $targets, \VanillaAltay\entity\ai\GoalSelector $goals) : void
	{
		$targets->add(1, new \VanillaAltay\entity\ai\goal\NearestPlayerTargetGoal(32, fn(\pocketmine\player\Player $player) : bool => $this->getWorld()->getFullLight($this->getPosition()) <= 7));
		$goals->add(1, new \VanillaAltay\entity\ai\goal\MeleeAttackGoal(.3, $this->getAttackDamage()));
		$goals->add(10, new \VanillaAltay\entity\ai\goal\RandomStrollGoal(.2, 10));
	}

	public static function getNetworkTypeId() : string
	{
		return EntityIds::SPIDER;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(0.9, 1.4);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 16;
	}

	protected function getAttackSpeed() : float
	{
		return 0.3;
	}

	protected function getAttackDamage() : float
	{
		return 3.0;
	}

	public function getName() : string
	{
		return "Spider";
	}

	public function getDrops() : array
	{
		return [VanillaItems::STRING()->setCount(mt_rand(0, 2)), VanillaItems::SPIDER_EYE()->setCount(mt_rand(0, 1))];
	}

	public function getXpDropAmount() : int
	{
		return 5;
	}
}
