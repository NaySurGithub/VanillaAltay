<?php

declare(strict_types=1);

namespace dimension\generator;

use dimension\generator\object\BlockManager;
use pocketmine\world\ChunkManager;

/**
 * A decoration pass run on a chunk once its neighbours exist. A populator
 * seeds its own random from the world seed and the chunk, reads the world as
 * it was before this population pass, and queues its changes into the root
 * BlockManager, which the generator applies once every populator of the chunk
 * has run.
 *
 * Populators run on the generation worker threads: they must not touch the
 * server, a World, or any shared mutable state.
 */
interface Populator{

	public function populate(BlockManager $root, ChunkManager $world, int $chunkX, int $chunkZ, int $seed) : void;
}
