<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\state;

use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;

/**
 * minecraft:variant, minecraft:mark_variant and minecraft:skin_id: an integer
 * the client uses to pick the texture and model of the entity.
 */
final class ValueStateSystem extends EntitySystem{

	private const KEYS = [
		"minecraft:variant" => ["variant", EntityMetadataProperties::VARIANT],
		"minecraft:mark_variant" => ["mark_variant", EntityMetadataProperties::MARK_VARIANT],
		"minecraft:skin_id" => ["skin_id", EntityMetadataProperties::SKIN_ID]
	];

	public function getValue() : int{
		return (int) ($this->config["value"] ?? 0);
	}

	public function onAdd() : void{
		$this->apply($this->getValue());
	}

	public function onRemove() : void{
		$this->apply(0);
	}

	private function apply(int $value) : void{
		$entry = self::KEYS[$this->component] ?? null;
		if($entry === null){
			return;
		}
		$this->entity->setData($entry[0], $value === 0 ? null : $value);
		$this->entity->setMetadata($entry[1], "int", $value);
	}
}
