<?php

declare(strict_types=1);

namespace VanillaAltay\entity;

use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use VanillaAltay\entity\ai\GoalSelector;
use VanillaAltay\VanillaAltayConfig;

use function atan2;
use function sqrt;

use const M_PI;

abstract class IntelligentMob extends Living
{
	private const TAG_NATURAL_SPAWN = "VanillaAltayNaturalSpawn";

	private GoalSelector $targetGoals;

	private GoalSelector $goals;

	private ?Living $target = null;

	private bool $naturallySpawned = false;

	private int $despawnCheckTicks = 0;

	protected function initEntity(\pocketmine\nbt\tag\CompoundTag $nbt) : void
	{
		$this->setMaxHealth($this->getVanillaMaxHealth());
		parent::initEntity($nbt);
		$this->naturallySpawned = $nbt->getByte(self::TAG_NATURAL_SPAWN, 0) !== 0;
		$this->targetGoals = new GoalSelector();
		$this->goals = new GoalSelector();
		$this->registerGoals($this->targetGoals, $this->goals);
	}

	abstract protected function getVanillaMaxHealth() : int;

	abstract protected function registerGoals(GoalSelector $targetGoals, GoalSelector $goals) : void;

	public function isHostile() : bool
	{
		return false;
	}

	protected function canRunAi() : bool
	{
		return true;
	}

	protected function burnsInDaylight() : bool
	{
		return false;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool
	{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if ($this->burnsInDaylight() && !$this->isUnderwater() && ($this->getWorld()->getTime() % 20) < $tickDiff && $this->getWorld()->getSkyLightReduction() < 4) {
			$highest = $this->getWorld()->getHighestBlockAt($this->location->getFloorX(), $this->location->getFloorZ());
			if ($highest !== null && $highest <= $this->location->getFloorY()) {
				$this->setOnFire(8);
			}
		}
		if ($this->naturallySpawned && ($this->despawnCheckTicks += $tickDiff) >= 100) {
			$this->despawnCheckTicks = 0;
			$nearPlayer = false;
			foreach ($this->getWorld()->getPlayers() as $player) {
				if ($this->getPosition()->distanceSquared($player->getPosition()) <= 128 ** 2) {
					$nearPlayer = true;
					break;
				}
			}
			if (!$nearPlayer) {
				$this->flagForDespawn();
				return true;
			}
		}
		if ($this->isAlive() && $this->canRunAi() && VanillaAltayConfig::entityAiEnabled()) {
			$this->targetGoals->tick($this, $tickDiff);
			$this->goals->tick($this, $tickDiff);
			$hasUpdate = true;
		}
		return $hasUpdate;
	}

	public function setNaturallySpawned(bool $value = true) : void
	{
		$this->naturallySpawned = $value;
	}

	public function saveNBT() : \pocketmine\nbt\tag\CompoundTag
	{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_NATURAL_SPAWN, $this->naturallySpawned ? 1 : 0);
		return $nbt;
	}

	public function getTarget() : ?Living
	{
		return $this->target;
	}

	public function setTarget(?Living $target) : void
	{
		$this->target = $target;
	}

	public function hasLivingTarget() : bool
	{
		return $this->target !== null && !$this->target->isClosed() && $this->target->isAlive() && $this->target->getWorld() === $this->getWorld();
	}

	public function isValidTarget(Living $target, float $range) : bool
	{
		return !($target instanceof Player && ($target->isCreative() || $target->isSpectator())) &&
			!$target->isClosed() && $target->isAlive() && $target->getWorld() === $this->getWorld() &&
			$this->getPosition()->distanceSquared($target->getPosition()) <= $range ** 2;
	}

	public function attack(EntityDamageEvent $source) : void
	{
		parent::attack($source);
		if (!$source->isCancelled() && $source instanceof EntityDamageByEntityEvent && ($damager = $source->getDamager()) instanceof Living) {
			$this->setTarget($damager);
		}
	}

	public function walkToward(Vector3 $destination, float $speed) : void
	{
		$dx = $destination->x - $this->location->x;
		$dz = $destination->z - $this->location->z;
		$length = sqrt(($dx ** 2) + ($dz ** 2));
		if ($length < 0.001) {
			$this->stopHorizontalMovement();
			return;
		}
		$this->setMotion($this->motion->withComponents(($dx / $length) * $speed, null, ($dz / $length) * $speed));
		if ($this->isCollidedHorizontally && $this->onGround) {
			$this->jump();
		}
		$this->setRotation(-atan2($dx, $dz) * 180 / M_PI, $this->location->pitch);
	}

	public function stopHorizontalMovement() : void
	{
		$this->setMotion($this->motion->withComponents(0, null, 0));
	}

	public function lookAtEntity(Entity $entity) : void
	{
		$dx = $entity->getPosition()->x - $this->location->x;
		$dy = ($entity->getPosition()->y + ($entity->getSize()->getHeight() * 0.5)) - ($this->location->y + ($this->getSize()->getHeight() * 0.5));
		$dz = $entity->getPosition()->z - $this->location->z;
		$horizontal = sqrt(($dx ** 2) + ($dz ** 2));
		$this->setRotation(-atan2($dx, $dz) * 180 / M_PI, -atan2($dy, $horizontal) * 180 / M_PI);
	}
}
