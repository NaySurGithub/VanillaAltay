<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\state;

use behaviorpack\entity\behavior\EntitySystem;

/**
 * minecraft:game_event_movement_tracking: which movements of the entity emit
 * vibrations (flap, step, swim) heard by sculk sensors.
 */
final class MovementTrackingSystem extends EntitySystem{

	public function emitsFlap() : bool{
		return ($this->config["emit_flap"] ?? false) === true;
	}

	public function emitsMove() : bool{
		return ($this->config["emit_move"] ?? true) !== false;
	}

	public function emitsSwim() : bool{
		return ($this->config["emit_swim"] ?? true) !== false;
	}

	public function onAdd() : void{
		$this->entity->setData("movement_tracking", [
			"emit_flap" => $this->emitsFlap(),
			"emit_move" => $this->emitsMove(),
			"emit_swim" => $this->emitsSwim()
		]);
	}

	public function onRemove() : void{
		$this->entity->setData("movement_tracking", null);
	}
}
