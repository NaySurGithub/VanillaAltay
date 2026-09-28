<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\movement;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\block\Block;
use pocketmine\block\Lava;
use pocketmine\block\Water;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageEvent;
use function count;
use function floor;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;
use function max;
use function min;
use function round;
use function str_contains;

/**
 * "minecraft:breathable": the air supply of the entity. The media it breathes
 * in refill the supply over the inhale time; elsewhere the supply drains and,
 * once exhausted, the entity takes drowning damage every suffocation interval.
 * It replaces the underwater breathing of the base mob.
 */
final class BreathableSystem extends EntitySystem{

	public const DATA_SUPPLY = "air_supply";

	private const DAMAGE = 2.0;

	private bool $damaging = false;

	/** @var list<string> */
	private array $breatheBlocks = [];

	/** @var list<string> */
	private array $nonBreatheBlocks = [];

	public function onAdd() : void{
		$this->breatheBlocks = self::blockList($this->config["breathe_blocks"] ?? null);
		$this->nonBreatheBlocks = self::blockList($this->config["non_breathe_blocks"] ?? null);
		$total = $this->getTotalSupply();
		$supply = $this->entity->getData(self::DATA_SUPPLY);
		$this->entity->setData(self::DATA_SUPPLY, $supply === null ? (float) $total : min((float) $supply, (float) $total));
		$this->entity->setMaxAirSupplyTicks($total);
		$this->sync();
	}

	public function onRemove() : void{
		$this->entity->setData(self::DATA_SUPPLY, null);
		$this->entity->setMaxAirSupplyTicks(300);
		$this->entity->setAirSupplyTicks(300);
		$this->entity->setBreathing(true);
	}

	/**
	 * @return list<string>
	 */
	private static function blockList(mixed $value) : array{
		$result = [];
		foreach(is_array($value) ? $value : [] as $entry){
			$name = is_array($entry) ? ($entry["name"] ?? null) : $entry;
			if(is_string($name)){
				$result[] = str_contains($name, ":") ? $name : "minecraft:" . $name;
			}
		}
		return $result;
	}

	private function flag(string $key, bool $default) : bool{
		$value = $this->config[$key] ?? null;
		return is_bool($value) ? $value : $default;
	}

	public function getTotalSupply() : int{
		return max(0, (int) round(BehaviorEntity::toFloat($this->config["total_supply"] ?? null, 15.0) * 20));
	}

	/**
	 * Returns the supply level, in ticks, at which the entity takes damage.
	 */
	private function getSuffocateThreshold() : float{
		$value = BehaviorEntity::toFloat($this->config["suffocate_time"] ?? null, -20.0);
		return $value < 0 ? $value : -$value * 20;
	}

	private function getInhaleTicks() : float{
		return max(0.0, BehaviorEntity::toFloat($this->config["inhale_time"] ?? null, 0.0) * 20);
	}

	private function headBlock() : Block{
		$location = $this->entity->getLocation();
		return $this->entity->getWorld()->getBlockAt(
			(int) floor($location->x),
			(int) floor($location->y + $this->entity->getEyeHeight()),
			(int) floor($location->z)
		);
	}

	/**
	 * Returns whether the entity breathes where its head currently is.
	 */
	public function canBreathe() : bool{
		$block = $this->headBlock();
		if(count($this->breatheBlocks) > 0 || count($this->nonBreatheBlocks) > 0){
			$name = PathNavigator::blockName($block);
			if(in_array($name, $this->breatheBlocks, true)){
				return true;
			}
			if(in_array($name, $this->nonBreatheBlocks, true)){
				return false;
			}
		}
		if($block instanceof Water && $this->entity->isUnderwater()){
			return $this->flag("breathes_water", false) || $this->entity->getEffects()->has(VanillaEffects::WATER_BREATHING()) || $this->entity->getEffects()->has(VanillaEffects::CONDUIT_POWER());
		}
		if($block instanceof Lava){
			return $this->flag("breathes_lava", true);
		}
		if($block->isSolid() && $block->isFullCube()){
			return $this->flag("breathes_solids", false);
		}
		return $this->flag("breathes_air", true);
	}

	public function tick(int $tickDiff) : void{
		$total = $this->getTotalSupply();
		$supply = (float) $this->entity->getData(self::DATA_SUPPLY, (float) $total);
		if($this->canBreathe()){
			$inhale = $this->getInhaleTicks();
			$supply = $inhale <= 0 ? (float) $total : min((float) $total, $supply + $total / $inhale * $tickDiff);
		}else{
			$supply -= $tickDiff;
			if($supply <= $this->getSuffocateThreshold()){
				$supply = 0.0;
				$this->damaging = true;
				$this->entity->attack(new EntityDamageEvent($this->entity, EntityDamageEvent::CAUSE_DROWNING, self::DAMAGE));
				$this->damaging = false;
				if($this->entity->isClosed() || !$this->entity->isAlive()){
					return;
				}
			}
		}
		$this->entity->setData(self::DATA_SUPPLY, $supply);
		$this->sync();
	}

	private function sync() : void{
		$total = $this->getTotalSupply();
		$supply = (float) $this->entity->getData(self::DATA_SUPPLY, (float) $total);
		if($this->entity->getMaxAirSupplyTicks() !== $total){
			$this->entity->setMaxAirSupplyTicks($total);
		}
		$ticks = (int) max(0.0, $supply);
		if($this->entity->getAirSupplyTicks() !== $ticks){
			$this->entity->setAirSupplyTicks($ticks);
		}
		$breathing = $supply >= $total || !$this->flag("generates_bubbles", true);
		if($this->entity->isBreathing() !== $breathing){
			$this->entity->setBreathing($breathing);
		}
	}

	public function beforeDamage(EntityDamageEvent $source) : void{
		if(!$this->damaging && $source->getCause() === EntityDamageEvent::CAUSE_DROWNING){
			$source->cancel();
		}
	}
}
