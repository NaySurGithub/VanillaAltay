<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure;

use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;

/**
 * A structure larger than the chunk it starts from. Every chunk within its reach rebuilds the same layout and
 * writes its own share of it.
 */
interface SpreadStructure extends Structure{

	/**
	 * How many chunks away from its origin the structure can still put blocks.
	 */
	public function getChunkReach() : int;

	/**
	 * Writes the part of the structure that belongs to the target chunk and its neighbours.
	 */
	public function placeAround(ChunkManager $world, Random $random, int $originChunkX, int $originChunkZ, int $targetChunkX, int $targetChunkZ) : void;
}
