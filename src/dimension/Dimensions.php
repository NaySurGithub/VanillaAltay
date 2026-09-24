<?php

declare(strict_types=1);

namespace dimension;

use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\Server;
use pocketmine\world\World;

/**
 * Maps worlds to Bedrock dimensions. The overworld is the default world; the
 * nether and the end are the worlds named in config.yml. Any other world is
 * treated as an overworld.
 */
final class Dimensions{

	/**
	 * @param array<int, string> $worldNames dimension id => world folder name
	 */
	public function __construct(
		private Server $server,
		private array $worldNames
	){}

	public function getDimension(World $world) : int{
		$name = $world->getFolderName();
		foreach($this->worldNames as $dimension => $worldName){
			if($worldName === $name){
				return $dimension;
			}
		}
		return DimensionIds::OVERWORLD;
	}

	public function isEnabled(int $dimension) : bool{
		return $dimension === DimensionIds::OVERWORLD || isset($this->worldNames[$dimension]);
	}

	public function getWorldName(int $dimension) : ?string{
		return $this->worldNames[$dimension] ?? null;
	}

	/**
	 * Returns the loaded world of a dimension, the default world for the
	 * overworld, or null when the dimension is disabled or its world is not
	 * loaded.
	 */
	public function getWorld(int $dimension) : ?World{
		$manager = $this->server->getWorldManager();
		if($dimension === DimensionIds::OVERWORLD){
			return $manager->getDefaultWorld();
		}
		$name = $this->worldNames[$dimension] ?? null;
		return $name === null ? null : $manager->getWorldByName($name);
	}

	/**
	 * Lowest block Y the client of this dimension can see.
	 */
	public static function getMinY(int $dimension) : int{
		return $dimension === DimensionIds::OVERWORLD ? World::Y_MIN : 0;
	}

	/**
	 * Exclusive upper block Y the client of this dimension can see.
	 */
	public static function getMaxY(int $dimension) : int{
		return match($dimension){
			DimensionIds::NETHER => 128,
			DimensionIds::THE_END => 256,
			default => World::Y_MAX
		};
	}
}
