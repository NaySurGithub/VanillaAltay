<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\item\VanillaItems;
use pocketmine\math\Position;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\Explosion;
use VanillaAltay\entity\ai\goal\CreeperSwellGoal;
use VanillaAltay\entity\ai\goal\NearestPlayerTargetGoal;
use VanillaAltay\entity\ai\goal\RandomStrollGoal;
use VanillaAltay\entity\ai\GoalSelector;

use function mt_rand;

final class Creeper extends Monster
{
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
