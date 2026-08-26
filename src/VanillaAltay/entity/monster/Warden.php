<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use VanillaAltay\entity\ai\goal\NearestPlayerTargetGoal;
use VanillaAltay\entity\ai\goal\RandomStrollGoal;
use VanillaAltay\entity\ai\goal\WardenAttackGoal;
use VanillaAltay\entity\ai\GoalSelector;

final class Warden extends ConfiguredMonster
{
	protected const NETWORK_ID = EntityIds::WARDEN;

	protected const NAME = "Warden";

	protected const HEIGHT = 2.9;

	protected const WIDTH = .9;

	protected const HEALTH = 500;

	protected const SPEED = .3;

	protected const DAMAGE = 30.0;

	protected const FOLLOW = 32.0;

	protected function registerGoals(GoalSelector $targets, GoalSelector $goals) : void
	{
		$targets->add(1, new NearestPlayerTargetGoal(32));
		$goals->add(1, new WardenAttackGoal());
		$goals->add(10, new RandomStrollGoal(.2, 12));
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool
	{
		if ($this->ticksLived % 120 === 0) {
			foreach ($this->getWorld()->getPlayers() as $player) {
				if ($this->getPosition()->distanceSquared($player->getPosition()) <= 20 ** 2) {
					$player->getEffects()->add(new EffectInstance(VanillaEffects::DARKNESS(), 260));
				}
			}
		}return parent::entityBaseTick($tickDiff);
	}
}
