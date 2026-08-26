<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\item\ItemTypeIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use VanillaAltay\entity\ai\goal\AvoidMonsterGoal;
use VanillaAltay\entity\ai\goal\RandomStrollGoal;
use VanillaAltay\entity\ai\GoalSelector;

class Villager extends ConfiguredAnimal
{
	protected const NETWORK_ID = EntityIds::VILLAGER_V2;

	protected const NAME = "Villager";

	protected const HEIGHT = 1.9;

	protected const WIDTH = .6;

	protected const HEALTH = 20;

	protected const SPEED = .2;

	protected function getBreedingItemTypeIds() : array
	{
		return [ItemTypeIds::BREAD,ItemTypeIds::CARROT,ItemTypeIds::POTATO,ItemTypeIds::BEETROOT];
	}

	protected function registerGoals(GoalSelector $targets, GoalSelector $goals) : void
	{
		$goals->add(1, new AvoidMonsterGoal(12, .28));
		$goals->add(10, new RandomStrollGoal(.18, 10));
	}
}
