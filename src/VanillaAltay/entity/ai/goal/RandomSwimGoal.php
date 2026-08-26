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

final class RandomSwimGoal implements Goal
{
	private ?Vector3 $direction = null;

	private int $ticks = 0;

	public function __construct(private float $speed = 0.1)
	{

	}

	public function canStart(IntelligentMob $mob) : bool
	{
		return $mob->isUnderwater();
	}

	public function shouldContinue(IntelligentMob $mob) : bool
	{
		return $mob->isUnderwater();
	}

	public function start(IntelligentMob $mob) : void
	{
		$this->chooseDirection();
	}

	public function tick(IntelligentMob $mob, int $tickDiff) : void
	{
		$this->ticks -= $tickDiff;
		if ($this->direction === null || $this->ticks <= 0 || $mob->isCollided) {
			$this->chooseDirection();
		}
		$direction = $this->direction ?? Vector3::zero();
		$mob->setMotion($direction->multiply($this->speed));
		$horizontal = sqrt(($direction->x ** 2) + ($direction->z ** 2));
		$mob->setRotation(-atan2($direction->x, $direction->z) * 180 / M_PI, -atan2($direction->y, $horizontal) * 180 / M_PI);
	}

	public function stop(IntelligentMob $mob) : void
	{
		$this->direction = null;
		$mob->setMotion(Vector3::zero());
	}

	private function chooseDirection() : void
	{
		$this->direction = (new Vector3(mt_rand(-100, 100) / 100, mt_rand(-35, 35) / 100, mt_rand(-100, 100) / 100))->normalize();
		$this->ticks = mt_rand(40, 100);
	}
}
