<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

use function in_array;

class Piglin extends ConfiguredMonster
{
	protected const NETWORK_ID = EntityIds::PIGLIN;

	protected const NAME = "Piglin";

	protected const HEIGHT = 1.9;

	protected const WIDTH = 0.6;

	protected const HEALTH = 16;

	protected const SPEED = 0.25;

	protected const DAMAGE = 5.0;

	protected function registerGoals(\VanillaAltay\entity\ai\GoalSelector $t, \VanillaAltay\entity\ai\GoalSelector $g) : void
	{
		$gold = [\pocketmine\item\ItemTypeIds::GOLDEN_BOOTS,\pocketmine\item\ItemTypeIds::GOLDEN_CHESTPLATE,\pocketmine\item\ItemTypeIds::GOLDEN_HELMET,\pocketmine\item\ItemTypeIds::GOLDEN_LEGGINGS];
		$t->add(1, new \VanillaAltay\entity\ai\goal\NearestPlayerTargetGoal(32, static function (\pocketmine\player\Player $player) use ($gold) : bool {
			foreach ($player->getArmorInventory()->getContents() as $item) {
				if (in_array($item->getTypeId(), $gold, true)) {
					return false;
				}
			}return true;
		}));
		$g->add(1, new \VanillaAltay\entity\ai\goal\MeleeAttackGoal(.25, 5));
		$g->add(10, new \VanillaAltay\entity\ai\goal\RandomStrollGoal(.18, 10));
	}
}
