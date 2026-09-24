<?php

declare(strict_types=1);

namespace dimension\generator\nether\object;

use dimension\generator\Blocks;
use pocketmine\world\ChunkManager;

/**
 * World read helpers used by the populators.
 */
final class WorldQuery{

	public const AIR = "minecraft:air";

	private function __construct(){
	}

	public static function blockId(ChunkManager $world, int $x, int $y, int $z) : string{
		if(!$world->isInWorld($x, $y, $z)){
			return self::AIR;
		}
		return Blocks::id($world->getBlockAt($x, $y, $z));
	}

	public static function biomeId(ChunkManager $world, int $x, int $y, int $z) : int{
		$chunk = $world->getChunk($x >> 4, $z >> 4);
		if($chunk === null){
			return -1;
		}
		return $chunk->getBiomeId($x & 0x0f, $y, $z & 0x0f);
	}

	/**
	 * Highest non-air block of a column;
	 * minY - 1 when the column is empty.
	 */
	public static function heightMap(ChunkManager $world, int $x, int $z) : int{
		$minY = $world->getMinY();
		for($y = $world->getMaxY() - 1; $y >= $minY; --$y){
			if(self::blockId($world, $x, $y, $z) !== self::AIR){
				return $y;
			}
		}
		return $minY - 1;
	}
}
