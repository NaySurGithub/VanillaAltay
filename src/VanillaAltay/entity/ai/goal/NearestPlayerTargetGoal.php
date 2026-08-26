<?php

declare(strict_types=1);

namespace VanillaAltay\entity\ai\goal;

use pocketmine\player\Player;
use VanillaAltay\entity\ai\Goal;
use VanillaAltay\entity\IntelligentMob;

final class NearestPlayerTargetGoal implements Goal
{
	/** @param null|\Closure(Player):bool $predicate */
	public function __construct(private float $range = 40.0, private ?\Closure $predicate = null)
	{
	}

	public function canStart(IntelligentMob $mob) : bool
	{
		$target = $mob->getTarget();
		if ($target instanceof Player && $mob->isValidTarget($target, $this->range)) {
			return true;
		}

		$nearest = null;
		$nearestDistance = $this->range ** 2;
		foreach ($mob->getWorld()->getPlayers() as $player) {
			if (!$mob->isValidTarget($player, $this->range) || ($this->predicate !== null && !($this->predicate)($player))) {
				continue;
			}
			$distance = $mob->getPosition()->distanceSquared($player->getPosition());
			if ($distance < $nearestDistance) {
				$nearest = $player;
				$nearestDistance = $distance;
			}
		}
		$mob->setTarget($nearest);
		return $nearest !== null;
	}

	public function shouldContinue(IntelligentMob $mob) : bool
	{
		$target = $mob->getTarget();
		return $target instanceof Player && $mob->isValidTarget($target, $this->range);
	}

	public function start(IntelligentMob $mob) : void
	{
	}

	public function tick(IntelligentMob $mob, int $tickDiff) : void
	{
	}

	public function stop(IntelligentMob $mob) : void
	{
		$mob->setTarget(null);
	}
}
