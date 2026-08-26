<?php

declare(strict_types=1);

namespace VanillaAltay\entity\flying;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\VanillaItems;
use pocketmine\math\Position;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\Explosion;
use VanillaAltay\entity\ai\goal\NearestPlayerTargetGoal;
use VanillaAltay\entity\ai\goal\ProjectileAttackGoal;
use VanillaAltay\entity\ai\goal\RandomFlyGoal;
use VanillaAltay\entity\ai\GoalSelector;
use VanillaAltay\entity\projectile\WitherSkull;

use function max;
use function min;

final class Wither extends BossFlyingMob
{
	private int $invulnerabilityTicks = 220;

	protected function initEntity(CompoundTag $nbt) : void
	{
		$this->invulnerabilityTicks = $nbt->getInt("Invul", 220);
		parent::initEntity($nbt);
		if (!$nbt->getTag("Health")) {
			$this->setHealth($this->getMaxHealth() / 3);
		}
	}

	public function saveNBT() : CompoundTag
	{
		return parent::saveNBT()->setInt("Invul", $this->invulnerabilityTicks);
	}

	protected function canRunAi() : bool
	{
		return $this->invulnerabilityTicks <= 0;
	}

	public function attack(EntityDamageEvent $source) : void
	{
		if ($this->invulnerabilityTicks > 0 && $source->getCause() !== EntityDamageEvent::CAUSE_VOID) {
			return;
		}parent::attack($source);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool
	{
		if ($this->invulnerabilityTicks > 0) {
			$before = $this->invulnerabilityTicks;
			$this->invulnerabilityTicks = max(0, $this->invulnerabilityTicks - $tickDiff);
			$this->setHealth(min($this->getMaxHealth(), $this->getHealth() + ($this->getMaxHealth() * $tickDiff / 660)));
			if ($before > 0 && $this->invulnerabilityTicks === 0) {
				$explosion = new Explosion(Position::fromObject($this->getPosition(), $this->getWorld()), 7, $this);
				$explosion->explodeA();
				$explosion->explodeB();
			}
		} elseif ($this->ticksLived % 20 === 0 && $this->getHealth() < $this->getMaxHealth()) {
			$this->setHealth($this->getHealth() + 1);
		}return parent::entityBaseTick($tickDiff);
	}

	public static function getNetworkTypeId() : string
	{
		return EntityIds::WITHER;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(3, 1);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 300;
	}

	protected function getFlySpeed() : float
	{
		return .18;
	}

	protected function getAttackDamage() : float
	{
		return 8;
	}

	protected function getFollowRange() : float
	{
		return 64;
	}

	protected function registerGoals(GoalSelector $t, GoalSelector $g) : void
	{
		$t->add(1, new NearestPlayerTargetGoal(64));
		$g->add(1, new ProjectileAttackGoal(WitherSkull::class, .18, .9, 8, 32, 30));
		$g->add(10, new RandomFlyGoal(.15, 20));
	}

	public function getName() : string
	{
		return "Wither";
	}

	public function getDrops() : array
	{
		return [VanillaItems::NETHER_STAR()];
	}

	public function getXpDropAmount() : int
	{
		return 50;
	}
}
