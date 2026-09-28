<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\state;

use behaviorpack\entity\BehaviorEntity;
use pocketmine\block\Bed;
use pocketmine\block\Bell;
use pocketmine\entity\effect\Effect;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\StringToEffectParser;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use function floor;
use function is_array;
use function is_int;
use function max;
use function min;

/**
 * The Bad Omen and Raid Omen states. The status effects are used when the
 * server knows them; the data store of behavior entities always keeps the
 * level and the remaining time so that the state works without them.
 */
final class RaidOmen{

	public const BAD_OMEN_DURATION = 120000;
	public const RAID_OMEN_DURATION = 600;
	public const MAX_LEVEL = 5;

	private const VILLAGE_RADIUS = 12;
	private const VILLAGE_HEIGHT = 6;

	private function __construct(){
	}

	private static function effect(string $name) : ?Effect{
		return StringToEffectParser::getInstance()->parse($name);
	}

	/**
	 * Returns the Bad Omen level, 0 when the entity has none.
	 */
	public static function getBadOmenLevel(Entity $entity) : int{
		return self::level($entity, "bad_omen");
	}

	public static function getRaidOmenLevel(Entity $entity) : int{
		return self::level($entity, "raid_omen");
	}

	private static function level(Entity $entity, string $name) : int{
		$effect = self::effect($name);
		if($effect !== null && $entity instanceof Living){
			$instance = $entity->getEffects()->get($effect);
			if($instance !== null){
				return $instance->getAmplifier() + 1;
			}
		}
		if($entity instanceof BehaviorEntity){
			$state = $entity->getData($name);
			if(is_array($state) && is_int($state["level"] ?? null)){
				return $state["level"];
			}
		}
		return 0;
	}

	public static function addBadOmen(Entity $entity, int $level = 1, int $duration = self::BAD_OMEN_DURATION) : void{
		self::apply($entity, "bad_omen", $level, $duration);
	}

	public static function addRaidOmen(Entity $entity, int $level = 1, int $duration = self::RAID_OMEN_DURATION) : void{
		self::apply($entity, "raid_omen", $level, $duration);
	}

	public static function clearBadOmen(Entity $entity) : void{
		self::clear($entity, "bad_omen");
	}

	public static function clearRaidOmen(Entity $entity) : void{
		self::clear($entity, "raid_omen");
	}

	/**
	 * Turns the Bad Omen of the entity into a Raid Omen of the same level.
	 * Returns whether the entity had a Bad Omen.
	 */
	public static function convertToRaidOmen(Entity $entity) : bool{
		$level = self::getBadOmenLevel($entity);
		if($level <= 0){
			return false;
		}
		self::clearBadOmen($entity);
		self::addRaidOmen($entity, $level);
		return true;
	}

	private static function apply(Entity $entity, string $name, int $level, int $duration) : void{
		$level = max(1, min(self::MAX_LEVEL, $level));
		$effect = self::effect($name);
		if($effect !== null && $entity instanceof Living){
			$entity->getEffects()->add(new EffectInstance($effect, $duration, $level - 1));
		}
		if($entity instanceof BehaviorEntity){
			$entity->setData($name, ["level" => $level, "ticks" => $duration]);
		}
	}

	private static function clear(Entity $entity, string $name) : void{
		$effect = self::effect($name);
		if($effect !== null && $entity instanceof Living){
			$entity->getEffects()->remove($effect);
		}
		if($entity instanceof BehaviorEntity){
			$entity->setData($name, null);
		}
	}

	/**
	 * Counts down the omen timers kept in the data store. Returns true when
	 * the Raid Omen ran out this tick, which starts the raid.
	 */
	public static function tickData(BehaviorEntity $entity, int $tickDiff) : bool{
		$expired = false;
		foreach(["bad_omen", "raid_omen"] as $name){
			$state = $entity->getData($name);
			if(!is_array($state) || !is_int($state["ticks"] ?? null)){
				continue;
			}
			$state["ticks"] -= $tickDiff;
			if($state["ticks"] > 0){
				$entity->setData($name, $state);
				continue;
			}
			$entity->setData($name, null);
			if($name === "raid_omen"){
				$expired = true;
			}
		}
		return $expired;
	}

	/**
	 * Villages are not tracked: a village is where a bell or a bed is close.
	 * Returns the position of the village center found, or null.
	 */
	public static function findVillage(Entity $entity) : ?Vector3{
		$world = $entity->getWorld();
		$position = $entity->getPosition();
		$baseX = (int) floor($position->x);
		$baseY = (int) floor($position->y);
		$baseZ = (int) floor($position->z);
		$bed = null;
		for($y = $baseY - self::VILLAGE_HEIGHT; $y <= $baseY + self::VILLAGE_HEIGHT; ++$y){
			for($x = $baseX - self::VILLAGE_RADIUS; $x <= $baseX + self::VILLAGE_RADIUS; ++$x){
				for($z = $baseZ - self::VILLAGE_RADIUS; $z <= $baseZ + self::VILLAGE_RADIUS; ++$z){
					if(!$world->isInWorld($x, $y, $z)){
						continue;
					}
					$block = $world->getBlockAt($x, $y, $z, true, false);
					if($block instanceof Bell){
						return new Vector3($x + 0.5, $y, $z + 0.5);
					}
					if($bed === null && $block instanceof Bed){
						$bed = new Vector3($x + 0.5, $y, $z + 0.5);
					}
				}
			}
		}
		return $bed;
	}
}
