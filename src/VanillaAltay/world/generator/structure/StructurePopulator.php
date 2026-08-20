<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure;

use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\populator\Populator;
use pocketmine\world\World;
use VanillaAltay\world\generator\populator\SurfacePopulator;
use function abs;

final class StructurePopulator implements Populator{

	/**
	 * @param Structure[] $structures
	 */
	public function __construct(private int $worldSeed, private array $structures){}

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void{
		$chunk = $world->getChunk($chunkX, $chunkZ);
		if($chunk === null){
			return;
		}

		$biomeId = $chunk->getBiomeId(7, 0, 7);

		foreach($this->structures as $structure){
			if($structure instanceof SpreadStructure){
				$this->populateSpread($world, $structure, $chunkX, $chunkZ, $random);
				continue;
			}

			if(!$structure->getPlacement()->canGenerate($this->worldSeed, $random, $chunkX, $chunkZ, $biomeId)){
				continue;
			}

			$random->setSeed($this->worldSeed ^ World::chunkHash($chunkX, $chunkZ));

			if($structure instanceof UndergroundStructure){
				for($attempt = 0; $attempt < $structure->getAttempts(); ++$attempt){
					$x = ($chunkX * Chunk::EDGE_LENGTH) + $random->nextBoundedInt(Chunk::EDGE_LENGTH);
					$z = ($chunkZ * Chunk::EDGE_LENGTH) + $random->nextBoundedInt(Chunk::EDGE_LENGTH);
					$y = $random->nextRange($structure->getMinY(), $structure->getMaxY());

					$structure->place($world, $random, $x, $y, $z);
				}

				continue;
			}

			$x = ($chunkX * Chunk::EDGE_LENGTH) + $random->nextBoundedInt(Chunk::EDGE_LENGTH - 1);
			$z = ($chunkZ * Chunk::EDGE_LENGTH) + $random->nextBoundedInt(Chunk::EDGE_LENGTH - 1);
			$y = SurfacePopulator::getHighestWorkableBlock($world, $x, $z);
			if($y === -1){
				continue;
			}

			$structure->place($world, $random, $x, $y - 1, $z);
		}
	}

	/**
	 * Looks for every origin whose structure could reach this chunk and rebuilds each of them. The layout is
	 * seeded from its own origin, so all the chunks it crosses draw exactly the same shaft.
	 */
	private function populateSpread(ChunkManager $world, SpreadStructure $structure, int $chunkX, int $chunkZ, Random $random) : void{
		$placement = $structure->getPlacement();
		$reach = $structure->getChunkReach();
		$spacing = $placement->getMaxDistance();

		$minRegionX = StructurePlacement::regionCoord($chunkX - $reach, $spacing);
		$maxRegionX = StructurePlacement::regionCoord($chunkX + $reach, $spacing);
		$minRegionZ = StructurePlacement::regionCoord($chunkZ - $reach, $spacing);
		$maxRegionZ = StructurePlacement::regionCoord($chunkZ + $reach, $spacing);

		for($regionX = $minRegionX; $regionX <= $maxRegionX; ++$regionX){
			for($regionZ = $minRegionZ; $regionZ <= $maxRegionZ; ++$regionZ){
				[$originX, $originZ] = $placement->getCandidateChunk($this->worldSeed, $random, $regionX, $regionZ);

				if(abs($originX - $chunkX) > $reach || abs($originZ - $chunkZ) > $reach){
					continue;
				}

				$origin = $world->getChunk($originX, $originZ);
				if($origin !== null && !$placement->isBiomeValid($origin->getBiomeId(7, 0, 7))){
					continue;
				}

				$random->setSeed($this->worldSeed ^ World::chunkHash($originX, $originZ));
				$structure->placeAround($world, $random, $originX, $originZ, $chunkX, $chunkZ);
			}
		}
	}
}
