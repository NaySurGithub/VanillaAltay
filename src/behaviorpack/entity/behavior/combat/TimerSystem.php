<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\combat;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\utils\Utils;
use function array_is_list;
use function count;
use function is_array;
use function is_int;
use function max;
use function mt_rand;

/**
 * minecraft:timer: runs time_down_event when the time runs out, then starts
 * again when looping. The remaining ticks are stored as "timer_remaining"
 * (-1 once a non looping timer ended).
 */
final class TimerSystem extends EntitySystem{

	private const KEY = "timer_remaining";

	public function onAdd() : void{
		if($this->entity->getData(self::KEY) === null){
			$this->entity->setData(self::KEY, $this->rollTicks());
		}
	}

	public function onRemove() : void{
		$this->entity->setData(self::KEY, null);
	}

	private function rollTicks() : int{
		$choices = $this->config["random_time_choices"] ?? null;
		if(is_array($choices) && count($choices) > 0){
			$total = 0.0;
			foreach($choices as $choice){
				if(is_array($choice)){
					$total += max(0.0, BehaviorEntity::toFloat($choice["weight"] ?? null, 1.0));
				}
			}
			$roll = Utils::getRandomFloat() * $total;
			foreach($choices as $choice){
				if(!is_array($choice)){
					continue;
				}
				$roll -= max(0.0, BehaviorEntity::toFloat($choice["weight"] ?? null, 1.0));
				if($roll <= 0){
					return max(1, (int) (BehaviorEntity::toFloat($choice["value"] ?? null, 0.0) * 20));
				}
			}
		}
		$time = $this->config["time"] ?? 0.0;
		if(is_array($time) && array_is_list($time) && count($time) === 2 && ($this->config["randomInterval"] ?? true) === true){
			$min = (int) (BehaviorEntity::toFloat($time[0], 0.0) * 20);
			$max = (int) (BehaviorEntity::toFloat($time[1], 0.0) * 20);
			return max(1, $max > $min ? mt_rand($min, $max) : $min);
		}
		if(is_array($time) && array_is_list($time) && count($time) > 0){
			return max(1, (int) (BehaviorEntity::toFloat($time[0], 0.0) * 20));
		}
		return max(1, (int) (BehaviorEntity::rangeValue($time, 0.0) * 20));
	}

	public function tick(int $tickDiff) : void{
		$remaining = $this->entity->getData(self::KEY);
		if(!is_int($remaining)){
			$remaining = $this->rollTicks();
		}
		if($remaining < 0){
			return;
		}
		$remaining -= $tickDiff;
		if($remaining > 0){
			$this->entity->setData(self::KEY, $remaining);
			return;
		}
		$looping = ($this->config["looping"] ?? true) === true;
		$this->entity->setData(self::KEY, $looping ? $this->rollTicks() : -1);
		$event = $this->config["time_down_event"] ?? null;
		if($event !== null){
			$this->entity->runTrigger($event);
		}
	}
}
