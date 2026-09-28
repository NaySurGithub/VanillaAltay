<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\combat;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\StringToEffectParser;
use function is_array;
use function is_string;
use function max;

/**
 * minecraft:spell_effects: adds and removes effects once when the component
 * is added.
 */
final class SpellEffectsSystem extends EntitySystem{

	public function onAdd() : void{
		$parser = StringToEffectParser::getInstance();
		$effects = $this->entity->getEffects();
		foreach(is_array($this->config["remove_effects"] ?? null) ? $this->config["remove_effects"] : [] as $name){
			if(is_string($name) && ($effect = $parser->parse($name)) !== null){
				$effects->remove($effect);
			}
		}
		foreach(is_array($this->config["add_effects"] ?? null) ? $this->config["add_effects"] : [] as $entry){
			if(!is_array($entry) || !is_string($entry["effect"] ?? null)){
				continue;
			}
			$effect = $parser->parse($entry["effect"]);
			if($effect === null){
				continue;
			}
			$seconds = BehaviorEntity::toFloat($entry["duration"] ?? null, 0.6);
			$infinite = $seconds < 0 || ($entry["duration"] ?? null) === "infinite";
			$effects->add(new EffectInstance(
				$effect,
				$infinite ? null : max(1, (int) ($seconds * 20)),
				max(0, (int) BehaviorEntity::toFloat($entry["amplifier"] ?? null, 0.0)),
				($entry["visible"] ?? true) !== false,
				($entry["ambient"] ?? false) === true,
				null,
				$infinite
			));
		}
	}
}
