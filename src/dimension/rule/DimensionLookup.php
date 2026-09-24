<?php

declare(strict_types=1);

namespace dimension\rule;

use dimension\Dimensions;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\world\Position;
use pocketmine\world\World;

/**
 * Gives block classes, which are created without any plugin context, access
 * to the dimension of the world they stand in.
 */
final class DimensionLookup{

	private static ?Dimensions $dimensions = null;

	private function __construct(){
	}

	public static function setDimensions(Dimensions $dimensions) : void{
		self::$dimensions = $dimensions;
	}

	public static function getDimension(World $world) : int{
		if(self::$dimensions === null){
			return DimensionIds::OVERWORLD;
		}
		return self::$dimensions->getDimension($world);
	}

	public static function isNether(World $world) : bool{
		return self::getDimension($world) === DimensionIds::NETHER;
	}

	/**
	 * Whether the position is bound to a loaded world of the nether.
	 */
	public static function isInNether(Position $position) : bool{
		return $position->isValid() && self::isNether($position->getWorld());
	}
}
