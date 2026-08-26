<?php

declare(strict_types=1);

namespace VanillaAltay\entity\ai\goal;

use pocketmine\math\Vector3;
use VanillaAltay\entity\ai\Goal;
use VanillaAltay\entity\IntelligentMob;

use function atan2;
use function mt_rand;
use function sqrt;

use const M_PI;

final class RandomFlyGoal implements Goal
{
	private ?Vector3 $destination = null;

	private int $ticks = 0;

	public function __construct(private float $speed = 0.15, private int $radius = 10)
	{

	}

	public function canStart(IntelligentMob $mob) : bool
	{
		return true;
	}

	public function shouldContinue(IntelligentMob $mob) : bool
	{
		return true;
	}

	public function start(IntelligentMob $mob) : void
	{
		$this->choose($mob);
	}

	public function tick(IntelligentMob $mob, int $tickDiff) : void
	{
		$this->ticks -= $tickDiff;
		if ($this->destination === null || $this->ticks <= 0 || $mob->getPosition()->distanceSquared($this->destination) < 1 || $mob->isCollided) {
			$this->choose($mob);
		}
		$delta = ($this->destination ?? $mob->getPosition())->subtractVector($mob->getPosition());
		if ($delta->lengthSquared() < 0.01) {
			return;
		}
		$direction = $delta->normalize();
		$mob->setMotion($direction->multiply($this->speed));
		$horizontal = sqrt(($direction->x ** 2) + ($direction->z ** 2));
		$mob->setRotation(-atan2($direction->x, $direction->z) * 180 / M_PI, -atan2($direction->y, $horizontal) * 180 / M_PI);
	}

	public function stop(IntelligentMob $mob) : void
	{
		$this->destination = null;
		$mob->setMotion(Vector3::zero());
	}

	private function choose(IntelligentMob $mob) : void
	{
		$this->destination = $mob->getPosition()->add(mt_rand(-$this->radius, $this->radius), mt_rand(-4, 4), mt_rand(-$this->radius, $this->radius));
		$this->ticks = mt_rand(30, 80);
	}
}
