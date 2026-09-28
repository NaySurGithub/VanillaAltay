<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\social;

use behaviorpack\entity\BehaviorEntity;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use function in_array;
use function is_array;
use function is_string;
use function str_starts_with;
use function strtolower;
use function substr;

/**
 * Shared lookups of the social goals.
 */
final class SocialHelper{

	public static function normalizeName(string $name) : string{
		$name = strtolower($name);
		return str_starts_with($name, "minecraft:") ? substr($name, 10) : $name;
	}

	/**
	 * @param list<string> $names normalized item names
	 */
	public static function itemMatches(Item $item, array $names) : bool{
		if($item->isNull()){
			return false;
		}
		foreach(StringToItemParser::getInstance()->lookupAliases($item) as $alias){
			if(in_array(self::normalizeName($alias), $names, true)){
				return true;
			}
		}
		return false;
	}

	/**
	 * @return list<string>
	 */
	public static function readNames(mixed $value) : array{
		$names = [];
		if(is_string($value)){
			$value = [$value];
		}
		if(!is_array($value)){
			return $names;
		}
		foreach($value as $name){
			if(is_string($name)){
				$names[] = self::normalizeName($name);
			}elseif(is_array($name) && is_string($name["item"] ?? null)){
				$names[] = self::normalizeName($name["item"]);
			}
		}
		return $names;
	}

	/**
	 * Returns the nearest living entity of the same identifier around the
	 * entity that satisfies the predicate.
	 *
	 * @param \Closure(BehaviorEntity) : bool $predicate
	 */
	public static function nearestSameType(BehaviorEntity $entity, float $range, \Closure $predicate) : ?BehaviorEntity{
		$best = null;
		$bestDistance = $range * $range;
		foreach($entity->getWorld()->getNearbyEntities($entity->getBoundingBox()->expandedCopy($range, $range / 2, $range), $entity) as $other){
			if(!$other instanceof BehaviorEntity || $other->getIdentifier() !== $entity->getIdentifier() || !$other->isAlive()){
				continue;
			}
			$distance = $other->getPosition()->distanceSquared($entity->getPosition());
			if($distance < $bestDistance && $predicate($other)){
				$best = $other;
				$bestDistance = $distance;
			}
		}
		return $best;
	}

	public static function isValid(?Entity $entity, BehaviorEntity $self) : bool{
		return $entity !== null && !$entity->isClosed() && $entity->isAlive() && $entity->getWorld() === $self->getWorld();
	}
}
