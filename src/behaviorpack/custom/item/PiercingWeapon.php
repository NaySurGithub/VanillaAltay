<?php

declare(strict_types=1);

namespace behaviorpack\custom\item;

use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\ServerProperties;
use function array_multisort;
use function is_array;
use function is_float;
use function is_int;
use function max;

/**
 * minecraft:piercing_weapon: an attack that hits every entity along the
 * look direction between the minimum and maximum reach.
 */
final class PiercingWeapon{

	private float $minReach;
	private float $maxReach;
	private float $creativeMinReach;
	private float $creativeMaxReach;
	private float $hitboxMargin;

	public function __construct(mixed $value){
		$value = is_array($value) ? $value : [];
		[$this->minReach, $this->maxReach] = self::range($value["reach"] ?? null, 0.0, 3.0);
		[$this->creativeMinReach, $this->creativeMaxReach] = self::range($value["creative_reach"] ?? null, $this->minReach, max($this->maxReach, 5.0));
		$margin = $value["hitbox_margin"] ?? 0.0;
		$this->hitboxMargin = is_int($margin) || is_float($margin) ? (float) $margin : 0.0;
	}

	/**
	 * @return array{float, float}
	 */
	private static function range(mixed $value, float $min, float $max) : array{
		if(!is_array($value)){
			return [$min, $max];
		}
		$low = $value["min"] ?? $min;
		$high = $value["max"] ?? $max;
		return [
			is_int($low) || is_float($low) ? (float) $low : $min,
			is_int($high) || is_float($high) ? (float) $high : $max
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function networkValue() : array{
		return [
			"reach" => ["min" => $this->minReach, "max" => $this->maxReach],
			"creative_reach" => ["min" => $this->creativeMinReach, "max" => $this->creativeMaxReach],
			"hitbox_margin" => $this->hitboxMargin
		];
	}

	/**
	 * Returns the entities the attacker pierces, nearest first.
	 *
	 * @return list<Entity>
	 */
	public function findTargets(Player $attacker) : array{
		$creative = !$attacker->hasFiniteResources();
		$min = $creative ? $this->creativeMinReach : $this->minReach;
		$max = $creative ? $this->creativeMaxReach : $this->maxReach;
		$eyes = $attacker->getEyePos();
		$direction = $attacker->getDirectionVector();
		$start = $eyes->addVector($direction->multiply($min));
		$end = $eyes->addVector($direction->multiply($max));
		$search = $attacker->getBoundingBox()->addCoord($direction->x * $max, $direction->y * $max, $direction->z * $max)->expand(1.0 + $this->hitboxMargin, 1.0 + $this->hitboxMargin, 1.0 + $this->hitboxMargin);

		$targets = [];
		$distances = [];
		foreach($attacker->getWorld()->getNearbyEntities($search, $attacker) as $entity){
			if(!$entity instanceof Living || !$entity->isAlive() || $entity->isFlaggedForDespawn()){
				continue;
			}
			$box = $entity->getBoundingBox()->expandedCopy($this->hitboxMargin, $this->hitboxMargin, $this->hitboxMargin);
			if(!$box->isVectorInside($start) && $box->calculateIntercept($start, $end) === null){
				continue;
			}
			$targets[] = $entity;
			$distances[] = $eyes->distanceSquared($entity->getPosition());
		}
		array_multisort($distances, $targets);
		return $targets;
	}

	/**
	 * Attacks every pierced entity except the given one with the held item.
	 */
	public function attackAlong(Player $attacker, Item $item, ?Entity $exclude) : void{
		foreach($this->findTargets($attacker) as $entity){
			if($exclude !== null && $entity->getId() === $exclude->getId()){
				continue;
			}
			if($entity instanceof Player && !$attacker->getServer()->getConfigGroup()->getConfigBool(ServerProperties::PVP)){
				continue;
			}
			$event = new EntityDamageByEntityEvent($attacker, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $item->getAttackPoints());
			$entity->attack($event);
		}
	}
}
