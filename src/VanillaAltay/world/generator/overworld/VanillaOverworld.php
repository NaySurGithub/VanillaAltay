<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\overworld;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\world\biome\BiomeRegistry;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;
use pocketmine\world\generator\Generator;
use pocketmine\world\generator\object\OreType;
use pocketmine\world\generator\populator\GroundCover;
use pocketmine\world\generator\populator\Ore;
use pocketmine\world\generator\populator\Populator;
use VanillaAltay\VanillaAltayConfig;
use VanillaAltay\world\biome\VanillaBiomes;
use VanillaAltay\world\generator\biome\VanillaBiomeSelector;
use VanillaAltay\world\generator\carver\CaveCarver;
use VanillaAltay\world\generator\noise\NoiseRouter;
use VanillaAltay\world\generator\structure\StructurePopulator;
use VanillaAltay\world\generator\structure\StructureRegistry;

use function max;
use function min;

class VanillaOverworld extends Generator
{
	public const SEA_LEVEL = 63;

	private const DEEPSLATE_FADE_TOP = 8;

	private const BEDROCK_LAYERS = 6;

	/**
	 * How deep below the terrain height the 3d density field is still allowed to carve.
	 */
	private const DENSITY_DEPTH = 12;

	private NoiseRouter $router;

	private VanillaBiomeSelector $selector;

	private CaveCarver $carver;

	/** @var Populator[] */
	private array $generationPopulators = [];

	/** @var Populator[] */
	private array $populators = [];

	public function __construct(int $seed, string $preset)
	{
		parent::__construct($seed, $preset);

		if (VanillaAltayConfig::customBiomesEnabled()) {
			VanillaBiomes::register();
		}

		$this->router = new NoiseRouter($this->random);
		$this->selector = new VanillaBiomeSelector($this->router);
		$this->carver = new CaveCarver($this->random, self::SEA_LEVEL);

		$this->random->setSeed($this->seed);

		if (VanillaAltayConfig::featureEnabled("ground-cover")) {
			$this->generationPopulators[] = new GroundCover();
		}
		if (VanillaAltayConfig::featureEnabled("ores")) {
			$this->populators[] = $this->createOrePopulator();
		}
		if (VanillaAltayConfig::featureEnabled("structures")) {
			$this->populators[] = new StructurePopulator($seed, VanillaAltayConfig::filterStructures(StructureRegistry::all()));
		}
	}

	private function createOrePopulator() : Ore
	{
		$ores = new Ore();
		$stone = VanillaBlocks::STONE();
		$deepslate = VanillaBlocks::DEEPSLATE();

		$ores->setOreTypes([
			new OreType(VanillaBlocks::COAL_ORE(), $stone, 30, 17, 136, 256),
			new OreType(VanillaBlocks::COAL_ORE(), $stone, 20, 17, 0, 192),
			new OreType(VanillaBlocks::IRON_ORE(), $stone, 10, 10, 80, 256),
			new OreType(VanillaBlocks::IRON_ORE(), $stone, 10, 10, -24, 56),
			new OreType(VanillaBlocks::IRON_ORE(), $stone, 10, 4, -64, 72),
			new OreType(VanillaBlocks::COPPER_ORE(), $stone, 16, 10, -16, 112),
			new OreType(VanillaBlocks::GOLD_ORE(), $stone, 4, 9, -64, 32),
			new OreType(VanillaBlocks::REDSTONE_ORE(), $deepslate, 4, 8, -64, 15),
			new OreType(VanillaBlocks::REDSTONE_ORE(), $deepslate, 8, 8, -64, -32),
			new OreType(VanillaBlocks::DIAMOND_ORE(), $deepslate, 4, 7, -64, 16),
			new OreType(VanillaBlocks::LAPIS_LAZULI_ORE(), $stone, 2, 7, -32, 32),
			new OreType(VanillaBlocks::LAPIS_LAZULI_ORE(), $stone, 4, 7, -64, 64),
			new OreType(VanillaBlocks::EMERALD_ORE(), $stone, 100, 3, -16, 256),
			new OreType(VanillaBlocks::DIRT(), $stone, 7, 33, 0, 160),
			new OreType(VanillaBlocks::GRAVEL(), $stone, 14, 33, -64, 256),
			//vanilla makes these blobs 64 blocks, but the server spreads a vein along a line of size/8 and a
			//vein that long reaches past the chunks the populator is allowed to touch
			new OreType(VanillaBlocks::GRANITE(), $stone, 4, 33, 0, 60),
			new OreType(VanillaBlocks::DIORITE(), $stone, 4, 33, 0, 60),
			new OreType(VanillaBlocks::ANDESITE(), $stone, 4, 33, 0, 60),
			new OreType(VanillaBlocks::TUFF(), $deepslate, 4, 33, -64, 0),
		]);

		return $ores;
	}

	public function generateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void
	{
		$this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->seed);

		$chunk = $world->getChunk($chunkX, $chunkZ) ?? throw new \InvalidArgumentException("Chunk $chunkX $chunkZ does not yet exist");

		$stone = VanillaBlocks::STONE()->getStateId();
		$deepslate = VanillaBlocks::DEEPSLATE()->getStateId();
		$water = VanillaBlocks::WATER()->getStateId();
		$bedrock = VanillaBlocks::BEDROCK()->getStateId();
		$air = Block::EMPTY_STATE_ID;

		$minY = $world->getMinY();
		$maxY = $world->getMaxY();

		$baseX = $chunkX * Chunk::EDGE_LENGTH;
		$baseZ = $chunkZ * Chunk::EDGE_LENGTH;

		$noise = $this->router->sampleChunk($chunkX, $chunkZ);

		$heights = [];
		$biomes = [];
		$biomeArray = new PalettedBlockArray(BiomeIds::OCEAN);
		$lowest = $maxY;
		$highest = $minY;
		for ($x = 0; $x < Chunk::EDGE_LENGTH; ++$x) {
			for ($z = 0; $z < Chunk::EDGE_LENGTH; ++$z) {
				$height = $noise->getTerrainHeight($x, $z);
				$heights[$x][$z] = $height;
				//vanilla stores biomes per 4x4 cell, which is also what keeps their edges from fraying
				$cellX = $x & ~3;
				$cellZ = $z & ~3;
				$biomes[$x][$z] = $biomes[$cellX][$cellZ] ?? $this->selector->pickBiomeFrom(
					$noise->getContinentalness($cellX, $cellZ),
					$noise->getTemperature($cellX, $cellZ),
					$noise->getHumidity($cellX, $cellZ),
					$noise->getErosion($cellX, $cellZ),
					$noise->getPeaksValleys($cellX, $cellZ),
				);
				for ($y = 0; $y < SubChunk::EDGE_LENGTH; ++$y) {
					$biomeArray->set($x, $y, $z, $biomes[$x][$z]);
				}

				$lowest = min($lowest, $height);
				$highest = max($highest, $height);
			}
		}

		//the 3d field only matters near the surface, and the caves stop short of it
		$density = $this->router->sampleDensity($chunkX, $chunkZ, $lowest - self::DENSITY_DEPTH, $highest);
		$cavesEnabled = VanillaAltayConfig::featureEnabled("caves");
		$caves = $cavesEnabled ? $this->carver->sample($chunkX, $chunkZ, $minY + 1, $highest - CaveCarver::SURFACE_MARGIN) : null;

		$bedrockTop = $minY + self::BEDROCK_LAYERS;
		$densityFrom = $lowest - self::DENSITY_DEPTH;

		//a subchunk that is entirely underground and entirely on one side of the deepslate band is uniform, so it
		//is filled in one go and only its caves are dug out afterwards
		$filled = [];
		foreach ($chunk->getSubChunks() as $y => $subChunk) {
			$bottom = $y << SubChunk::COORD_BIT_SIZE;
			$top = $bottom + SubChunk::EDGE_LENGTH - 1;

			$blocks = [];
			if ($bottom >= $bedrockTop && $top < $densityFrom) {
				if ($top < 0) {
					$blocks = [new PalettedBlockArray($deepslate)];
				} elseif ($bottom > self::DEEPSLATE_FADE_TOP) {
					$blocks = [new PalettedBlockArray($stone)];
				}
			}

			$filled[$y] = $blocks !== [];
			$chunk->setSubChunk($y, new SubChunk(Block::EMPTY_STATE_ID, $blocks, clone $biomeArray));
		}

		for ($x = 0; $x < Chunk::EDGE_LENGTH; ++$x) {
			for ($z = 0; $z < Chunk::EDGE_LENGTH; ++$z) {
				$terrainHeight = $heights[$x][$z];

				for ($y = $minY; $y < $minY + self::BEDROCK_LAYERS; ++$y) {
					$chunk->setBlockStateId(
						$x,
						$y,
						$z,
						$y === $minY || $this->random->nextBoundedInt(self::BEDROCK_LAYERS) >= ($y - $minY) ? $bedrock : $deepslate,
					);
				}

				$caveCeiling = $terrainHeight - CaveCarver::SURFACE_MARGIN;

				[$cheese, $tunnels] = $caves?->getColumn($x, $z) ?? [[], []];
				$caveFloor = $caves?->getFloor() ?? $minY;

				$deepTop = min($densityFrom, $maxY);
				for ($cellStart = $bedrockTop; $cellStart < $deepTop; $cellStart += CaveCarver::SAMPLING_RATE_Y) {
					$cellEnd = min($cellStart + CaveCarver::SAMPLING_RATE_Y, $deepTop);
					$index = $cellStart - $caveFloor;

					$mayCarve = $cavesEnabled && $cellStart <= $caveCeiling && isset($cheese[$index]) && CaveCarver::cellMayContainCave(
						$cheese[$index],
						$cheese[$index + CaveCarver::SAMPLING_RATE_Y] ?? $cheese[$index],
						$tunnels[$index],
						$tunnels[$index + CaveCarver::SAMPLING_RATE_Y] ?? $tunnels[$index],
					);

					if (!$mayCarve && ($filled[$cellStart >> SubChunk::COORD_BIT_SIZE] ?? false) && ($filled[($cellEnd - 1) >> SubChunk::COORD_BIT_SIZE] ?? false)) {
						continue;
					}

					for ($y = $cellStart; $y < $cellEnd; ++$y) {
						$blockIndex = $y - $caveFloor;
						$cave = $mayCarve && $y <= $caveCeiling && isset($cheese[$blockIndex]) &&
							CaveCarver::isCave($cheese[$blockIndex], $tunnels[$blockIndex], $y, self::SEA_LEVEL);

						if ($filled[$y >> SubChunk::COORD_BIT_SIZE] ?? false) {
							if ($cave) {
								$chunk->setBlockStateId($x, $y, $z, $air);
							}
						} elseif (!$cave) {
							$chunk->setBlockStateId($x, $y, $z, $this->isDeepslateAt($y) ? $deepslate : $stone);
						}
					}
				}

				$top = max($terrainHeight, self::SEA_LEVEL);
				for ($y = max($minY + self::BEDROCK_LAYERS, $densityFrom); $y <= $top && $y < $maxY; ++$y) {
					if ($y <= $terrainHeight && $density->isSolid($x, $y, $z, $terrainHeight)) {
						if ($cavesEnabled && $y <= $caveCeiling && $caves->isCave($x, $y, $z)) {
							continue;
						}
						$chunk->setBlockStateId($x, $y, $z, $this->isDeepslateAt($y) ? $deepslate : $stone);
					} elseif ($y <= self::SEA_LEVEL) {
						$chunk->setBlockStateId($x, $y, $z, $water);
					}
				}
			}
		}

		//the ground cover has to be laid before anything is populated: run later, it would find the trees that
		//neighbouring chunks grew into this one and bury their trunks under grass
		foreach ($this->generationPopulators as $populator) {
			$populator->populate($world, $chunkX, $chunkZ, $this->random);
		}
	}

	/**
	 * Vanilla fades stone into deepslate between y=0 and y=8 instead of cutting sharply.
	 */
	private function isDeepslateAt(int $y) : bool
	{
		return match (true) {
			$y < 0 => true,
			$y > self::DEEPSLATE_FADE_TOP => false,
			default => $this->random->nextBoundedInt(self::DEEPSLATE_FADE_TOP + 1) >= $y,
		};
	}

	public function populateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void
	{
		$this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->seed);

		foreach ($this->populators as $populator) {
			$populator->populate($world, $chunkX, $chunkZ, $this->random);
		}

		$chunk = $world->getChunk($chunkX, $chunkZ) ?? throw new \InvalidArgumentException("Chunk $chunkX $chunkZ does not yet exist");
		if (VanillaAltayConfig::featureEnabled("biome-decoration")) {
			BiomeRegistry::getInstance()->getBiome($chunk->getBiomeId(7, self::SEA_LEVEL, 7))->populateChunk($world, $chunkX, $chunkZ, $this->random);
		}
	}

	public function getSeaLevel() : int
	{
		return self::SEA_LEVEL;
	}

	public function getHighestBlockAt(int $x, int $z) : int
	{
		return max($this->router->getTerrainHeight($x, $z), self::SEA_LEVEL);
	}
}
