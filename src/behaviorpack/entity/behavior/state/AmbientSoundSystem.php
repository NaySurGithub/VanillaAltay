<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\state;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\utils\Utils;
use function array_is_list;
use function is_array;
use function is_string;
use function max;

/**
 * minecraft:ambient_sound_interval: plays the ambient sound of the entity
 * every "value" seconds plus a random part of "range" seconds. Newer formats
 * list event names chosen by filters.
 */
final class AmbientSoundSystem extends EntitySystem{

	private int $delay = -1;

	public function onAdd() : void{
		$this->delay = $this->nextDelay();
	}

	private function nextDelay() : int{
		$value = max(0.0, BehaviorEntity::toFloat($this->config["value"] ?? null, 8.0));
		$range = max(0.0, BehaviorEntity::toFloat($this->config["range"] ?? null, 16.0));
		return max(1, (int) (($value + Utils::getRandomFloat() * $range) * 20));
	}

	public function getEventName() : ?string{
		$names = $this->config["event_names"] ?? null;
		if(is_array($names)){
			$names = array_is_list($names) ? $names : [$names];
			foreach($names as $entry){
				if(!is_array($entry) || !is_string($entry["event_name"] ?? null)){
					continue;
				}
				if(!is_array($entry["condition"] ?? null) && !is_array($entry["filters"] ?? null)){
					return $entry["event_name"];
				}
				$filters = $entry["filters"] ?? $entry["condition"];
				if($this->entity->testFilter($filters)){
					return $entry["event_name"];
				}
			}
		}
		$name = $this->config["event_name"] ?? "ambient";
		return is_string($name) ? $name : null;
	}

	public function tick(int $tickDiff) : void{
		$this->delay -= $tickDiff;
		if($this->delay > 0){
			return;
		}
		$this->delay = $this->nextDelay();
		$this->play();
	}

	public function play() : void{
		$event = $this->getEventName();
		if($event === null || $event === ""){
			return;
		}
		$position = $this->entity->getPosition();
		$packet = LevelSoundEventPacket::create(
			$event,
			$position->add(0, $this->entity->getEyeHeight(), 0),
			-1,
			$this->entity->getIdentifier(),
			$this->entity->hasComponent("minecraft:is_baby"),
			false,
			$this->entity->getId(),
			null
		);
		$this->entity->getWorld()->broadcastPacketToViewers($position, $packet);
	}
}
