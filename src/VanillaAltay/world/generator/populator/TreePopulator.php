<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\populator;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\BlockTypeTags;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\object\Tree;
use pocketmine\world\generator\object\TreeFactory;
use pocketmine\world\generator\object\TreeType;
use function count;

/**
 * Plants trees from a callback rather than from a single TreeType, so a biome can mix species and use tree
 * objects the server does not know about.
 */
final class TreePopulator extends SurfacePopulator{

	/**
	 * @phpstan-param \Closure(Random) : ?Tree $factory
	 */
	public function __construct(private \Closure $factory){}

	/**
	 * Picks between several types, the way a vanilla forest mixes oak and birch.
	 *
	 * @phpstan-param array<int, TreeType> $weighted a type for each slot of the roll
	 */
	public static function ofTypes(array $weighted) : self{
		return new self(function(Random $random) use ($weighted) : ?Tree{
			return TreeFactory::get($random, $weighted[$random->nextBoundedInt(count($weighted))]);
		});
	}

	protected function place(ChunkManager $world, int $x, int $y, int $z, Random $random) : void{
		($this->factory)($random)?->getBlockTransaction($world, $x, $y, $z, $random)?->apply();
	}

	/**
	 * A tree only grows on soil. Anything else under the column, including the logs of a tree that is already
	 * there, means no tree, otherwise trunks end up buried or stacked on each other.
	 */
	protected function getPlacementY(ChunkManager $world, int $x, int $z) : int{
		$highest = $world->getChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE)?->getHighestBlockAt($x & Chunk::COORD_MASK, $z & Chunk::COORD_MASK);
		if($highest === null){
			return -1;
		}

		for($y = $highest; $y >= $world->getMinY(); --$y){
			$block = $world->getBlockAt($x, $y, $z);
			if($block->hasTypeTag(BlockTypeTags::DIRT) || $block->hasTypeTag(BlockTypeTags::MUD)){
				return $y + 1;
			}

			if($block->getTypeId() !== BlockTypeIds::AIR && $block->getTypeId() !== BlockTypeIds::SNOW_LAYER){
				return -1;
			}
		}

		return -1;
	}
}
