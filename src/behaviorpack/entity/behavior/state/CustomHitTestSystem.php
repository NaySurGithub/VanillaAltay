<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\state;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\math\AxisAlignedBB;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use function abs;
use function array_is_list;
use function count;
use function is_array;
use function max;

/**
 * minecraft:custom_hit_test: hitboxes used for hits instead of the collision
 * box. The client hit area is widened to contain every hitbox.
 */
final class CustomHitTestSystem extends EntitySystem{

	/**
	 * @return list<array{width: float, height: float, pivot: array{float, float, float}}>
	 */
	public function getHitboxes() : array{
		$hitboxes = $this->config["hitboxes"] ?? [];
		if(!is_array($hitboxes)){
			return [];
		}
		if(!array_is_list($hitboxes)){
			$hitboxes = [$hitboxes];
		}
		$result = [];
		foreach($hitboxes as $hitbox){
			if(!is_array($hitbox)){
				continue;
			}
			$pivot = $hitbox["pivot"] ?? [];
			$pivot = is_array($pivot) && count($pivot) === 3 ? $pivot : [0, 0, 0];
			$result[] = [
				"width" => max(0.0, BehaviorEntity::toFloat($hitbox["width"] ?? null, 1.0)),
				"height" => max(0.0, BehaviorEntity::toFloat($hitbox["height"] ?? null, 1.0)),
				"pivot" => [
					BehaviorEntity::toFloat($pivot[0] ?? null, 0.0),
					BehaviorEntity::toFloat($pivot[1] ?? null, 0.0),
					BehaviorEntity::toFloat($pivot[2] ?? null, 0.0)
				]
			];
		}
		return $result;
	}

	/**
	 * Returns the hitboxes in world space, centered on their pivot relative to
	 * the entity position.
	 *
	 * @return list<AxisAlignedBB>
	 */
	public function getWorldHitboxes() : array{
		$position = $this->entity->getPosition();
		$scale = $this->entity->getScale();
		$boxes = [];
		foreach($this->getHitboxes() as $hitbox){
			$halfWidth = $hitbox["width"] * $scale / 2;
			$halfHeight = $hitbox["height"] * $scale / 2;
			$x = $position->x + $hitbox["pivot"][0] * $scale;
			$y = $position->y + $hitbox["pivot"][1] * $scale;
			$z = $position->z + $hitbox["pivot"][2] * $scale;
			$boxes[] = new AxisAlignedBB($x - $halfWidth, $y - $halfHeight, $z - $halfWidth, $x + $halfWidth, $y + $halfHeight, $z + $halfWidth);
		}
		return $boxes;
	}

	public function onAdd() : void{
		$hitboxes = $this->getHitboxes();
		$this->entity->setData("custom_hitboxes", count($hitboxes) > 0 ? $hitboxes : null);
		if(count($hitboxes) === 0){
			$this->resetSize();
			return;
		}
		$width = 0.0;
		$height = 0.0;
		foreach($hitboxes as $hitbox){
			$offset = max(abs($hitbox["pivot"][0]), abs($hitbox["pivot"][2]));
			$width = max($width, $hitbox["width"] + $offset * 2);
			$height = max($height, $hitbox["pivot"][1] + $hitbox["height"] / 2);
		}
		$scale = $this->entity->getScale();
		$size = $this->entity->getSize();
		$this->entity->setMetadata(EntityMetadataProperties::BOUNDING_BOX_WIDTH, "float", max($width * $scale, $size->getWidth()));
		$this->entity->setMetadata(EntityMetadataProperties::BOUNDING_BOX_HEIGHT, "float", max($height * $scale, $size->getHeight()));
	}

	public function onRemove() : void{
		$this->entity->setData("custom_hitboxes", null);
		$this->resetSize();
	}

	private function resetSize() : void{
		$size = $this->entity->getSize();
		$this->entity->setMetadata(EntityMetadataProperties::BOUNDING_BOX_WIDTH, "float", $size->getWidth());
		$this->entity->setMetadata(EntityMetadataProperties::BOUNDING_BOX_HEIGHT, "float", $size->getHeight());
	}
}
