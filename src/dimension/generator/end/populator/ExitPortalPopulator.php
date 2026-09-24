<?php

declare(strict_types=1);

namespace dimension\generator\end\populator;

use dimension\generator\end\object\ExitPortal;
use dimension\generator\nether\object\WorldQuery;
use dimension\generator\object\BlockManager;
use dimension\generator\Populator;
use pocketmine\world\ChunkManager;

/**
 * Builds the exit portal frame on top of the main island at 0, 0. It runs
 * for chunk 0, 0 so that every block of the frame lies in a chunk the
 * population pass can write.
 */
final class ExitPortalPopulator implements Populator{

	public function populate(BlockManager $root, ChunkManager $world, int $chunkX, int $chunkZ, int $seed) : void{
		if($chunkX === 0 && $chunkZ === 0){
			$object = new BlockManager($world);
			(new ExitPortal())->generate($object, 0, WorldQuery::heightMap($world, 0, 0), 0);
			$root->merge($object);
		}
	}
}
