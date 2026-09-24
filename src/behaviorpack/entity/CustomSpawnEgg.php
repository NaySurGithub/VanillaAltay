<?php

declare(strict_types=1);

namespace behaviorpack\entity;

use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\SpawnEgg;
use pocketmine\math\Vector3;
use pocketmine\world\World;

/**
 * Spawn egg of a custom behavior pack entity.
 */
final class CustomSpawnEgg extends SpawnEgg{

	/**
	 * @phpstan-param class-string<BehaviorEntity> $entityClass
	 */
	public function __construct(
		ItemIdentifier $identifier,
		string $name,
		private string $entityClass
	){
		parent::__construct($identifier, $name);
	}

	protected function createEntity(World $world, Vector3 $pos, float $yaw, float $pitch) : Entity{
		$class = $this->entityClass;
		return new $class(Location::fromObject($pos, $world, $yaw, $pitch));
	}
}
