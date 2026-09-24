<?php

declare(strict_types=1);

namespace dimension\generator\structure;

use dimension\generator\ChunkHash;
use dimension\generator\object\BlockManager;
use dimension\generator\Populator;
use dimension\generator\random\Xoroshiro128;
use dimension\generator\structure\placement\StructurePlacement;
use dimension\generator\structure\template\StateBlocks;
use dimension\generator\structure\template\StructureTemplate;
use dimension\generator\structure\template\StructureTemplates;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\world\ChunkManager;
use const PHP_INT_MAX;

/**
 * Bone fossils of soul sand valleys: one candidate chunk per region of 32
 * by 32 chunks. The fossil sits on the lowest soul sand or soul soil
 * surface found under its footprint, and one fossil in three gets a dried
 * ghast beside it. Air cells of the fossil templates are not placed.
 */
final class NetherFossilPopulator implements Populator{

	private const LAVA_LEVEL = 31;

	private StructurePlacement $placement;
	private Xoroshiro128 $random;

	public function __construct(
		private int $seed,
		private string $resourceFolder
	){
		$this->random = new Xoroshiro128(0);
		$this->placement = new StructurePlacement(
			StructurePlacement::DEFAULT_SALT,
			2,
			32,
			static fn(int $biome) : bool => $biome === BiomeIds::SOULSAND_VALLEY
		);
	}

	public function populate(BlockManager $root, ChunkManager $world, int $chunkX, int $chunkZ, int $seed) : void{
		$chunk = $world->getChunk($chunkX, $chunkZ);
		if($chunk === null){
			return;
		}
		$biome = $chunk->getBiomeId(3, self::LAVA_LEVEL, 3);
		if(!$this->placement->canGenerate($seed, $this->random, $chunkX, $chunkZ, $biome)){
			return;
		}
		$this->random->setSeed($seed ^ ChunkHash::hash($chunkX, $chunkZ));
		$x = ($chunkX << 4) + 3;
		$z = ($chunkZ << 4) + 3;
		$manager = new BlockManager($world);
		$structure = StructureTemplates::get($this->resourceFolder, "nether_fossils/fossil_" . ($this->random->nextInt(14) + 1));
		if($structure === null){
			return;
		}

		$height = PHP_INT_MAX;
		for($bx = 0; $bx < $structure->getSizeX(); ++$bx){
			for($bz = 0; $bz < $structure->getSizeZ(); ++$bz){
				foreach($this->getHighestWorkableBlocks($manager, $x + $bx, $z + $bz) as $y){
					if($y < $height){
						$height = $y;
					}
				}
			}
		}
		if($height === PHP_INT_MAX){
			return;
		}

		$volume = $structure->getVolume();
		for($index = 0; $index < $volume; ++$index){
			$state = $structure->stateAt($index);
			if($state === null || $state->getName() === StructureTemplate::AIR){
				continue;
			}
			$block = StateBlocks::toBlock($state);
			if($block !== null){
				$manager->setBlockStateAt($x + $structure->xOf($index), $height + $structure->yOf($index), $z + $structure->zOf($index), $block);
			}
		}
		if($this->random->nextInt(3) === 0){
			$driedGhast = StateBlocks::state("minecraft:dried_ghast");
			$block = $driedGhast === null ? null : StateBlocks::toBlock($driedGhast);
			if($block !== null){
				$manager->setBlockStateAt($x - 1, $height, $z - 1, $block);
			}
		}
		$root->merge($manager);
	}

	/**
	 * Y just above every soul sand or soul soil block of the column that has
	 * a replaceable block on top, scanning down from Y 128.
	 *
	 * @return list<int>
	 */
	private function getHighestWorkableBlocks(BlockManager $level, int $x, int $z) : array{
		$blockYs = [];
		for($y = 128; $y > 0; --$y){
			$id = $level->getBlockIdAt($x, $y, $z);
			if(($id === "minecraft:soul_sand" || $id === "minecraft:soul_soil") && $level->getBlockAt($x, $y + 1, $z)->canBeReplaced()){
				$blockYs[] = $y + 1;
			}
		}
		return $blockYs;
	}
}
