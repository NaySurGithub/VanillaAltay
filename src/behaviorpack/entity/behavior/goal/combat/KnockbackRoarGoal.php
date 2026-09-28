<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\combat;

use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\AxisAlignedBB;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use function is_array;
use function max;
use function sqrt;

/**
 * minecraft:behavior.knockback_roar: roars for "duration" seconds; at
 * "attack_time" the nearby entities accepted by "knockback_filters" are
 * pushed away, and those accepted by "damage_filters" are hurt.
 */
class KnockbackRoarGoal extends CombatGoal{

	private int $startTick = -1;
	private bool $knocked = false;
	private int $nextUseTick = 0;

	public function getControls() : int{
		return self::MOVE | self::LOOK;
	}

	public function canUse() : bool{
		return $this->now() >= $this->nextUseTick;
	}

	public function canContinue() : bool{
		return $this->startTick >= 0 && $this->now() - $this->startTick < $this->durationTicks();
	}

	public function isInterruptable() : bool{
		return false;
	}

	private function durationTicks() : int{
		return max(1, (int) ($this->float("duration", 1.0) * 20));
	}

	public function start() : void{
		$this->startTick = $this->now();
		$this->knocked = false;
		$this->entity->getNavigator()->stop();
		$this->entity->setFlag(EntityMetadataFlags::ROARING, true);
	}

	public function stop() : void{
		$this->entity->setFlag(EntityMetadataFlags::ROARING, false);
		$this->nextUseTick = $this->now() + (int) ($this->float("cooldown_time", 0.1) * 20);
		$this->startTick = -1;
		if(isset($this->config["on_roar_end"])){
			$this->entity->runTrigger($this->config["on_roar_end"]);
		}
	}

	public function tick(int $tickDiff) : void{
		$this->entity->getNavigator()->stop();
		if($this->knocked || $this->now() - $this->startTick < (int) ($this->float("attack_time", 0.5) * 20)){
			return;
		}
		$this->knocked = true;
		$this->roar();
	}

	private function roar() : void{
		$range = $this->float("knockback_range", 4.0);
		$strength = $this->float("knockback_strength", 4.0);
		$horizontal = $this->float("knockback_horizontal_strength", $strength);
		$vertical = $this->float("knockback_vertical_strength", $strength);
		$height = $this->float("knockback_height", 0.4);
		$damage = $this->float("knockback_damage", 6.0);
		$knockbackFilters = $this->config["knockback_filters"] ?? null;
		$damageFilters = $this->config["damage_filters"] ?? null;
		$position = $this->entity->getPosition();
		$box = new AxisAlignedBB($position->x - $range, $position->y - $range, $position->z - $range, $position->x + $range, $position->y + $range, $position->z + $range);
		foreach($this->entity->getWorld()->getNearbyEntities($box, $this->entity) as $other){
			if(!$other instanceof Living || !CombatGoal::isAttackable($this->entity, $other)){
				continue;
			}
			if($position->distanceSquared($other->getPosition()) > $range * $range){
				continue;
			}
			$context = ["other" => $other, "target" => $other];
			if(is_array($damageFilters) && $this->entity->testFilter($damageFilters, $context) && $damage > 0){
				$other->attack(new EntityDamageByEntityEvent($this->entity, $other, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage, [], 0.0));
			}
			if(is_array($knockbackFilters) && !$this->entity->testFilter($knockbackFilters, $context)){
				continue;
			}
			$dx = $other->getPosition()->x - $position->x;
			$dz = $other->getPosition()->z - $position->z;
			$distance = max(0.01, sqrt($dx * $dx + $dz * $dz));
			$factor = $horizontal * 0.1;
			$motion = $other->getMotion();
			$other->setMotion($motion->add($dx / $distance * $factor, $height * $vertical * 0.25, $dz / $distance * $factor));
		}
	}
}
