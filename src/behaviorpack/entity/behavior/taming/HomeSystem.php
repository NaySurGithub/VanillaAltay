<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\taming;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\math\Vector3;
use function count;
use function floor;
use function is_array;
use function is_string;

/**
 * minecraft:home: the position the entity was first given this component
 * at becomes its home, kept as "home" [x, y, z]. The restriction radius is
 * kept as "home_radius" and the home blocks as "home_blocks".
 */
final class HomeSystem extends EntitySystem{

	public function onAdd() : void{
		if(!is_array($this->entity->getData("home"))){
			$position = $this->entity->getPosition();
			$this->entity->setData("home", [(int) floor($position->x), (int) floor($position->y), (int) floor($position->z)]);
		}
		$this->entity->setData("home_radius", BehaviorEntity::toFloat($this->config["restriction_radius"] ?? null, 0.0));
		$blocks = [];
		foreach(is_array($this->config["home_block_list"] ?? null) ? $this->config["home_block_list"] : [] as $block){
			if(is_string($block)){
				$blocks[] = TamingHelper::normalizeItemName($block);
			}
		}
		$this->entity->setData("home_blocks", count($blocks) > 0 ? $blocks : null);
	}

	public function onRemove() : void{
		$this->entity->setData("home_radius", null);
		$this->entity->setData("home_blocks", null);
	}

	public static function getHome(BehaviorEntity $entity) : ?Vector3{
		$home = $entity->getData("home");
		if(!is_array($home) || count($home) !== 3){
			return null;
		}
		return new Vector3((float) $home[0], (float) $home[1], (float) $home[2]);
	}

	/**
	 * Whether a position lies within the restriction radius of the home; an
	 * entity without a radius is not restricted.
	 */
	public static function isWithinHome(BehaviorEntity $entity, Vector3 $position) : bool{
		$home = self::getHome($entity);
		$radius = BehaviorEntity::toFloat($entity->getData("home_radius"), 0.0);
		if($home === null || $radius <= 0){
			return true;
		}
		return $home->add(0.5, 0, 0.5)->distanceSquared($position) <= $radius * $radius;
	}
}
