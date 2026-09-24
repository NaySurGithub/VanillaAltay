<?php

declare(strict_types=1);

namespace dimension\generator\nether;

use dimension\generator\biome\NetherBiomePicker;
use dimension\generator\BiomeFiller;
use dimension\generator\ChunkHash;
use dimension\generator\GeneratorOptions;
use dimension\generator\holder\NetherObjectHolder;
use dimension\generator\holder\NetherTerrainHolder;
use dimension\generator\math\Float32;
use dimension\generator\object\BlockManager;
use dimension\generator\Populator;
use dimension\generator\random\LegacyRandom;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\Generator;
use function intdiv;

/**
 * Nether terrain between Y 0 and 127: bedrock floor and roof, netherrack
 * shaped by a 3D density, a lava sea up to Y 31 and biome dependent
 * surfaces. Nothing is generated outside that range.
 */
final class NetherGenerator extends Generator{

	public const LAVA_LEVEL = 31;
	private const SEA_LEVEL = 63;
	private const MIN_DENSITY_Y = 1;
	private const MAX_DENSITY_Y = 126;

	private NetherBiomePicker $biomePicker;
	private NetherObjectHolder $objectHolder;
	private LegacyRandom $chunkRandom;
	/** @var list<Populator>|null */
	private ?array $populators = null;

	private int $air;
	private int $bedrock;
	private int $netherrack;
	private int $basalt;
	private int $blackstone;
	private int $gravel;
	private int $soulSand;
	private int $soulSoil;
	private int $warpedWart;
	private int $warpedNylium;
	private int $netherWartBlock;
	private int $crimsonNylium;
	private int $lava;

	private float $patchThreshold;
	private float $crimsonStateThreshold;
	private float $wartThreshold;

	public function __construct(int $seed, string $preset){
		parent::__construct($seed, $preset);
		$this->biomePicker = new NetherBiomePicker(new LegacyRandom($seed));
		$this->objectHolder = new NetherObjectHolder(new LegacyRandom($seed));
		$this->chunkRandom = new LegacyRandom(0);

		$this->air = VanillaBlocks::AIR()->getStateId();
		$this->bedrock = VanillaBlocks::BEDROCK()->getStateId();
		$this->netherrack = VanillaBlocks::NETHERRACK()->getStateId();
		$this->basalt = VanillaBlocks::BASALT()->getStateId();
		$this->blackstone = VanillaBlocks::BLACKSTONE()->getStateId();
		$this->gravel = VanillaBlocks::GRAVEL()->getStateId();
		$this->soulSand = VanillaBlocks::SOUL_SAND()->getStateId();
		$this->soulSoil = VanillaBlocks::SOUL_SOIL()->getStateId();
		$this->warpedWart = VanillaBlocks::WARPED_WART_BLOCK()->getStateId();
		$this->warpedNylium = VanillaBlocks::WARPED_NYLIUM()->getStateId();
		$this->netherWartBlock = VanillaBlocks::NETHER_WART_BLOCK()->getStateId();
		$this->crimsonNylium = VanillaBlocks::CRIMSON_NYLIUM()->getStateId();
		$this->lava = VanillaBlocks::LAVA()->getStillForm()->getStateId();

		$this->patchThreshold = Float32::of(-0.012);
		$this->crimsonStateThreshold = Float32::of(0.54);
		$this->wartThreshold = Float32::of(1.17);
	}

	public function getObjectHolder() : NetherObjectHolder{
		return $this->objectHolder;
	}

	public function generateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$chunk = $world->getChunk($chunkX, $chunkZ) ?? throw new \InvalidArgumentException("Chunk $chunkX $chunkZ does not yet exist");
		$biomes = $this->generateBiomes($chunk, $chunkX, $chunkZ);
		$this->generateTerrain($chunk, $chunkX, $chunkZ, $biomes);
	}

	public function populateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$this->populators ??= NetherPopulators::create($this->seed, GeneratorOptions::resourceFolder($this->preset));
		$root = new BlockManager($world);
		foreach($this->populators as $populator){
			try{
				$populator->populate($root, $world, $chunkX, $chunkZ, $this->seed);
			}catch(\Throwable){
			}
		}
		$root->apply();
	}

	/**
	 * @return int[] biome id per column, indexed x * 16 + z
	 */
	private function generateBiomes(Chunk $chunk, int $chunkX, int $chunkZ) : array{
		$biomes = [];
		for($x = 0; $x < 16; $x++){
			$worldX = $chunkX * 16 + $x;
			for($z = 0; $z < 16; $z++){
				$biomes[$x * 16 + $z] = $this->biomePicker->pick($worldX, self::SEA_LEVEL, $chunkZ * 16 + $z)->getBiomeId();
			}
		}
		BiomeFiller::fillColumns($chunk, $biomes);
		return $biomes;
	}

	/**
	 * @param int[] $biomes biome id per column, indexed x * 16 + z
	 */
	private function generateTerrain(Chunk $chunk, int $chunkX, int $chunkZ, array $biomes) : void{
		$baseX = $chunkX << 4;
		$baseZ = $chunkZ << 4;
		$random = $this->chunkRandom;
		$random->setSeed($this->seed ^ ChunkHash::hash($chunkX, $chunkZ));
		$noises = $this->objectHolder->getTerrainHolder();
		$densityFunction = $noises->getDensityFunction();
		$cellMinY = intdiv(self::MIN_DENSITY_Y, 8) * 8;
		$cellMaxY = intdiv(self::MAX_DENSITY_Y, 8) * 8;

		$columns = [];
		for($i = 0; $i < 256; $i++){
			$columns[$i] = [];
		}
		for($cellX = 0; $cellX < 16; $cellX += 4){
			for($cellZ = 0; $cellZ < 16; $cellZ += 4){
				for($cellY = $cellMaxY; $cellY >= $cellMinY; $cellY -= 8){
					for($localX = 0; $localX < 4; $localX++){
						$x = $cellX + $localX;
						for($localZ = 0; $localZ < 4; $localZ++){
							$z = $cellZ + $localZ;
							$column = &$columns[$x * 16 + $z];
							for($localY = 7; $localY >= 0; $localY--){
								$y = $cellY + $localY;
								if($y < self::MIN_DENSITY_Y || $y > self::MAX_DENSITY_Y){
									continue;
								}
								if($densityFunction->compute($baseX + $x, $y, $baseZ + $z) > 0){
									$column[$y] = $this->netherrack;
								}elseif($y <= self::LAVA_LEVEL){
									$column[$y] = $this->lava;
								}else{
									$column[$y] = $this->air;
								}
							}
							unset($column);
						}
					}
				}
			}
		}

		for($x = 0; $x < 16; ++$x){
			for($z = 0; $z < 16; ++$z){
				$column = $columns[$x * 16 + $z];
				$column[0] = $this->bedrock;
				$column[126] = $this->netherrack;
				$column[127] = $this->bedrock;
				$this->decorateSurface($column, $biomes[$x * 16 + $z], $baseX + $x, $baseZ + $z, $noises);

				for($i = 0; $i < $random->nextBoundedInt(6); $i++){
					if($column[126 - $i] === $this->netherrack && $column[125 - $i] !== $this->air){
						$column[126 - $i] = $this->bedrock;
					}
				}

				for($y = 0; $y < 128; $y++){
					if($column[$y] !== $this->air){
						$chunk->setBlockStateId($x, $y, $z, $column[$y]);
					}
				}
			}
		}
	}

	/**
	 * @param int[] $column block state ids indexed by Y (0-127)
	 */
	private function decorateSurface(array &$column, int $biomeId, int $nx, int $nz, NetherTerrainHolder $noises) : void{
		for($y = 1; $y < 127; ++$y){
			switch($biomeId){
				case BiomeIds::BASALT_DELTAS:
					if($column[$y] === $this->netherrack){
						if($this->isCeil($column, $y)){
							$column[$y] = $this->basalt;
							break;
						}elseif($column[$y + 1] === $this->air
							&& ($noises->getNetherStateNoise()->getValue($nx, $y, $nz) >= 0
								|| ($y <= 35 && $y >= 30 && $noises->getPatchNoise()->getValue($nx, $y, $nz) >= $this->patchThreshold))){
							$column[$y] = $this->gravel;
							break;
						}
						if($this->isTop($column, $y) || $this->isCeil($column, $y)){
							$column[$y] = $this->blackstone;
						}
					}
					break;
				case BiomeIds::SOULSAND_VALLEY:
					if($column[$y] === $this->netherrack){
						if($this->isCeil($column, $y)){
							$column[$y] = $noises->getNetherStateNoise()->getValue($nx, $y, $nz) >= 0 ? $this->soulSand : $this->soulSoil;
						}elseif($this->isTop($column, $y)){
							if($noises->getNetherStateNoise()->getValue($nx, $y, $nz) >= 0 || ($y <= 35 && $y >= 30 && $noises->getPatchNoise()->getValue($nx, $y, $nz) >= $this->patchThreshold)){
								$column[$y] = $this->soulSand;
							}else{
								$column[$y] = $this->soulSoil;
							}
						}
					}
					break;
				case BiomeIds::WARPED_FOREST:
					if($column[$y] === $this->netherrack
						&& $column[$y + 1] === $this->air
						&& $y > 31
						&& $noises->getNetherStateNoise()->getValue($nx, $y, $nz) <= 0){
						$column[$y] = $noises->getNetherwartNoise()->getValue($nx, $y, $nz) >= $this->wartThreshold ? $this->warpedWart : $this->warpedNylium;
					}
					break;
				case BiomeIds::CRIMSON_FOREST:
					if($column[$y] === $this->netherrack
						&& $column[$y + 1] === $this->air
						&& $y > 31
						&& $noises->getNetherStateNoise()->getValue($nx, $y, $nz) <= $this->crimsonStateThreshold){
						$column[$y] = $noises->getNetherwartNoise()->getValue($nx, $y, $nz) >= $this->wartThreshold ? $this->netherWartBlock : $this->crimsonNylium;
					}
					break;
				case BiomeIds::HELL:
					if($column[$y] === $this->netherrack && $this->isTop($column, $y)){
						if($y > 31 && $y < 35 && $noises->getSoulsandNoise()->getValue($nx, $y, $nz) >= $this->patchThreshold){
							$column[$y] = $this->gravel;
							break;
						}
						if($y <= 35 && $y >= 30 && $noises->getSoulsandNoise()->getValue($nx, $y, $nz) >= $this->patchThreshold){
							$column[$y] = $this->soulSand;
						}
					}
					break;
			}
		}
	}

	/**
	 * Whether air lies within the 4 blocks above $y (or at $y).
	 *
	 * @param int[] $column
	 */
	private function isTop(array $column, int $y) : bool{
		for($i = 0; $i < 5; $i++){
			$yy = $y + $i;
			if($yy < 1 || $yy > 127){
				continue;
			}
			if($column[$yy] === $this->air){
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether air lies within the 4 blocks below $y (or at $y).
	 *
	 * @param int[] $column
	 */
	private function isCeil(array $column, int $y) : bool{
		for($i = 0; $i < 5; $i++){
			$yy = $y - $i;
			if($yy < 1 || $yy > 127){
				continue;
			}
			if($column[$yy] === $this->air){
				return true;
			}
		}
		return false;
	}
}
