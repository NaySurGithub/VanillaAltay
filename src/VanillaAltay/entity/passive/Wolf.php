<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\item\ItemTypeIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Wolf extends TameableAnimal
{
	protected const NETWORK_ID = EntityIds::WOLF;

	protected const NAME = "Wolf";

	protected const HEIGHT = 0.85;

	protected const WIDTH = 0.6;

	protected const HEALTH = 8;

	protected const SPEED = 0.3;

	protected function getTamingItemTypeIds() : array
	{
		return [ItemTypeIds::BONE];
	}

	protected function registerGoals(\VanillaAltay\entity\ai\GoalSelector $t, \VanillaAltay\entity\ai\GoalSelector $g) : void
	{
		$g->add(1, new \VanillaAltay\entity\ai\goal\MeleeAttackGoal(.3, 4));
		$g->add(2, new \VanillaAltay\entity\ai\goal\FollowOwnerGoal(.3));
		$g->add(10, new \VanillaAltay\entity\ai\goal\RandomStrollGoal(.22, 10));
	}
}
