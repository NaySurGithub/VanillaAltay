<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\combat;

use behaviorpack\entity\BehaviorEntity;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use function atan2;
use function is_array;
use function is_string;
use function max;
use function sqrt;
use const M_PI;

/**
 * minecraft:behavior.ranged_attack: keeps the target within the attack
 * radius and shoots the projectile of the "minecraft:shooter" component at
 * it, in bursts, with an optional charge-up.
 */
class RangedAttackGoal extends CombatGoal{

	private int $seeTicks = 0;
	private int $attackTimer = -1;
	private int $chargeTicks = -1;
	private int $burstLeft = 0;
	private int $burstTimer = 0;

	public function getControls() : int{
		return self::MOVE | self::LOOK;
	}

	public function canUse() : bool{
		if($this->isSitting()){
			return false;
		}
		$target = $this->entity->getTargetEntity();
		return CombatGoal::isAttackable($this->entity, $target)
			&& $this->entity->getPosition()->distanceSquared($target->getPosition()) <= $this->followRange() ** 2;
	}

	public function start() : void{
		$this->seeTicks = 0;
		$this->attackTimer = -1;
		$this->chargeTicks = -1;
		$this->burstLeft = 0;
	}

	public function stop() : void{
		$this->entity->getNavigator()->stop();
		$this->endCharge();
		$this->burstLeft = 0;
	}

	private function attackRadius() : float{
		$radius = $this->float("attack_radius", 0.0);
		return $radius > 0 ? $radius : $this->followRange();
	}

	private function nextInterval() : int{
		$min = $this->float("attack_interval_min", 0.0);
		$max = $this->float("attack_interval_max", $min);
		if(isset($this->config["attack_interval"])){
			return max(1, (int) (BehaviorEntity::rangeValue($this->config["attack_interval"], 1.0) * 20));
		}
		return max(1, (int) (BehaviorEntity::rangeValue([$min, max($min, $max)], 1.0) * 20));
	}

	public function tick(int $tickDiff) : void{
		$target = $this->entity->getTargetEntity();
		if($target === null){
			return;
		}
		$seen = CombatGoal::canSee($this->entity, $target);
		$this->seeTicks = $seen ? $this->seeTicks + $tickDiff : 0;
		$distanceSquared = $this->entity->getPosition()->distanceSquared($target->getPosition());
		$radius = $this->attackRadius();
		$minRadius = $this->float("attack_radius_min", 0.0);
		$sightTicks = (int) ($this->float("target_in_sight_time", 1.0) * 20);
		$navigator = $this->entity->getNavigator();
		if($distanceSquared <= $radius * $radius && $this->seeTicks >= $sightTicks && $distanceSquared >= $minRadius * $minRadius){
			$navigator->stop();
		}else{
			$navigator->moveTo($target->getPosition(), $this->float("speed_multiplier", 1.0), 0.5);
		}
		$this->lookAtEntity($target);

		if($this->burstLeft > 0){
			$this->burstTimer -= $tickDiff;
			if($this->burstTimer <= 0){
				$this->fire($target);
			}
			return;
		}
		if($this->attackTimer < 0){
			$this->attackTimer = $this->nextInterval();
		}
		$this->attackTimer -= $tickDiff;
		$chargeShoot = (int) ($this->float("charge_shoot_trigger", 0.0) * 20);
		if($this->attackTimer > $chargeShoot || !$seen || $distanceSquared > $radius * $radius){
			if(!$seen){
				$this->endCharge();
			}
			return;
		}
		if(!$this->isWithinFov($target, $this->float("ranged_fov", 90.0))){
			return;
		}
		if($chargeShoot > 0){
			if($this->chargeTicks < 0){
				$this->chargeTicks = 0;
				$this->entity->setFlag(EntityMetadataFlags::CHARGE_ATTACK, true);
			}
			$this->chargeTicks += $tickDiff;
			if($this->chargeTicks >= (int) ($this->float("charge_charged_trigger", 0.0) * 20)){
				$this->entity->setFlag(EntityMetadataFlags::CHARGED, true);
			}
			if($this->chargeTicks < $chargeShoot){
				return;
			}
		}
		$this->endCharge();
		$this->burstLeft = max(1, (int) $this->float("burst_shots", 1.0));
		$this->fire($target);
		$this->attackTimer = -1;
	}

	private function endCharge() : void{
		if($this->chargeTicks >= 0){
			$this->entity->setFlag(EntityMetadataFlags::CHARGE_ATTACK, false);
			$this->entity->setFlag(EntityMetadataFlags::CHARGED, false);
		}
		$this->chargeTicks = -1;
	}

	private function fire(Entity $target) : void{
		$this->burstLeft--;
		$this->burstTimer = (int) ($this->float("burst_interval", 0.0) * 20);
		if($this->bool("swing", false)){
			$this->entity->broadcastAnimation(new ArmSwingAnimation($this->entity));
		}
		self::shoot($this->entity, $target);
	}

	/**
	 * Spawns the projectile of the "minecraft:shooter" component of an entity
	 * and launches it toward a target. Returns the projectile, or null.
	 */
	public static function shoot(BehaviorEntity $shooter, Entity $target) : ?Entity{
		$config = $shooter->getComponent("minecraft:shooter");
		if(!is_array($config) || !is_string($config["def"] ?? null)){
			return null;
		}
		$world = $shooter->getWorld();
		$eye = $shooter->getEyePos();
		$aim = $target->getPosition()->add(0, $target->getSize()->getHeight() / 3, 0);
		$dx = $aim->x - $eye->x;
		$dz = $aim->z - $eye->z;
		$horizontal = sqrt($dx * $dx + $dz * $dz);
		$yaw = atan2($dz, $dx) / M_PI * 180 - 90;
		$pitch = -atan2($aim->y - $eye->y, $horizontal) / M_PI * 180;
		$nbt = CompoundTag::create()
			->setTag(EntityFactory::TAG_IDENTIFIER, new StringTag($config["def"]))
			->setTag(Entity::TAG_POS, new ListTag([new DoubleTag($eye->x), new DoubleTag($eye->y), new DoubleTag($eye->z)]))
			->setTag(Entity::TAG_MOTION, new ListTag([new DoubleTag(0.0), new DoubleTag(0.0), new DoubleTag(0.0)]))
			->setTag(Entity::TAG_ROTATION, new ListTag([new FloatTag($yaw < 0 ? $yaw + 360.0 : $yaw), new FloatTag($pitch)]));
		$projectile = EntityFactory::getInstance()->createFromData($world, $nbt);
		if($projectile === null){
			return null;
		}
		$projectileConfig = $projectile instanceof BehaviorEntity ? ($projectile->getComponent("minecraft:projectile") ?? []) : [];
		$power = BehaviorEntity::toFloat($config["power"] ?? $projectileConfig["power"] ?? null, 1.6);
		$gravity = $projectile instanceof BehaviorEntity ? $projectile->getProjectileGravity() : 0.05;
		$direction = new Vector3($dx, $aim->y - $eye->y + ($gravity > 0 ? $horizontal * 0.2 : 0.0), $dz);
		$velocity = $direction->lengthSquared() > 0 ? $direction->normalize()->multiply($power) : new Vector3(0, 0, 0);
		if($projectile instanceof BehaviorEntity && $projectile->isCustomProjectile()){
			$difficulty = $world->getDifficulty();
			$uncertainty = BehaviorEntity::toFloat($projectileConfig["uncertainty_base"] ?? null, 0.0)
				- $difficulty * BehaviorEntity::toFloat($projectileConfig["uncertainty_multiplier"] ?? null, 0.0);
			$projectile->setProjectileOwner($shooter);
			$projectile->setOwningEntity($shooter);
			$projectile->spawnToAll();
			$projectile->shootProjectile($velocity, max(0.0, $uncertainty));
			return $projectile;
		}
		$projectile->setOwningEntity($shooter);
		$projectile->setMotion($velocity);
		$projectile->spawnToAll();
		return $projectile;
	}
}
