<?php

declare(strict_types=1);

namespace dimension\generator\end;

use dimension\generator\biome\EndBiomePicker;
use dimension\generator\BiomeFiller;
use dimension\generator\GeneratorOptions;
use dimension\generator\holder\EndObjectHolder;
use dimension\generator\math\Float32;
use dimension\generator\math\GenerationMath;
use dimension\generator\noise\SimplexNoise;
use dimension\generator\object\BlockManager;
use dimension\generator\Populator;
use dimension\generator\random\Xoroshiro128;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\Generator;
use function abs;
use function fmod;
use function max;
use function sqrt;

/**
 * End terrain: the main island around the origin and the outer islands
 * beyond 1024 blocks, made of end stone between Y 0 and 127. The whole
 * chunk is the end biome.
 */
final class EndGenerator extends Generator{

	private const COORDINATE_SCALE = 684.412;
	private const DETAIL_NOISE_SCALE_X = 80.0;
	private const DETAIL_NOISE_SCALE_Z = 80.0;

	private EndBiomePicker $biomePicker;
	private EndObjectHolder $objectHolder;
	/** @var list<Populator>|null */
	private ?array $populators = null;
	private int $endStone;

	public function __construct(int $seed, string $preset){
		parent::__construct($seed, $preset);
		$this->biomePicker = new EndBiomePicker();
		$this->objectHolder = new EndObjectHolder(new Xoroshiro128($seed));
		$this->endStone = VanillaBlocks::END_STONE()->getStateId();
	}

	public function getObjectHolder() : EndObjectHolder{
		return $this->objectHolder;
	}

	public function generateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$chunk = $world->getChunk($chunkX, $chunkZ) ?? throw new \InvalidArgumentException("Chunk $chunkX $chunkZ does not yet exist");
		BiomeFiller::fill($chunk, $this->biomePicker->pick($chunkX << 4, 63, $chunkZ << 4)->getBiomeId());
		$this->generateTerrain($chunk, $chunkX, $chunkZ);
	}

	public function populateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$this->populators ??= EndPopulators::create($this->seed, GeneratorOptions::resourceFolder($this->preset));
		$root = new BlockManager($world);
		foreach($this->populators as $populator){
			try{
				$populator->populate($root, $world, $chunkX, $chunkZ, $this->seed);
			}catch(\Throwable){
			}
		}
		$root->apply();
	}

	private function generateTerrain(Chunk $chunk, int $chunkX, int $chunkZ) : void{
		$noises = $this->objectHolder->getTerrainHolder();
		$densityX = $chunkX << 1;
		$densityZ = $chunkZ << 1;
		$density = [];
		$detailNoise = $noises->getDetailNoiseOctaves()->generateNoiseOctaves(
			$densityX, 0, $densityZ, 3, 33, 3,
			(self::COORDINATE_SCALE * 2) / self::DETAIL_NOISE_SCALE_X,
			4.277575000000001,
			(self::COORDINATE_SCALE * 2) / self::DETAIL_NOISE_SCALE_Z
		);
		$roughnessNoise = $noises->getRoughnessNoiseOctaves()->generateNoiseOctaves(
			$densityX, 0, $densityZ, 3, 33, 3,
			self::COORDINATE_SCALE * 2, self::COORDINATE_SCALE, self::COORDINATE_SCALE * 2
		);
		$roughnessNoise2 = $noises->getRoughnessNoiseOctaves2()->generateNoiseOctaves(
			$densityX, 0, $densityZ, 3, 33, 3,
			self::COORDINATE_SCALE * 2, self::COORDINATE_SCALE, self::COORDINATE_SCALE * 2
		);

		$index = 0;
		for($i = 0; $i < 3; $i++){
			for($j = 0; $j < 3; $j++){
				$noiseHeight = self::getIslandHeight($chunkX, $chunkZ, $i, $j, $noises->getIslandNoise());
				for($k = 0; $k < 33; $k++){
					$noiseR = $roughnessNoise[$index] / 512.0;
					$noiseR2 = $roughnessNoise2[$index] / 512.0;
					$noiseD = ($detailNoise[$index] / 10.0 + 1.0) / 2.0;
					$dens = $noiseD < 0 ? $noiseR : ($noiseD > 1 ? $noiseR2 : $noiseR + ($noiseR2 - $noiseR) * $noiseD);
					$dens = ($dens - 8.0) + $noiseHeight;
					$index++;
					if($k < 8){
						$lowering = Float32::of((8 - $k) / 7.0);
						$dens = $dens * (1.0 - $lowering) + $lowering * -30.0;
					}elseif($k > 14){
						$lowering = GenerationMath::clamp(($k - 14) / 64.0, 0.0, 1.0);
						$dens = $dens * (1.0 - $lowering) + $lowering * -3000.0;
					}
					$density[$i][$j][$k] = $dens;
				}
			}
		}

		for($i = 0; $i < 2; $i++){
			for($j = 0; $j < 2; $j++){
				for($k = 0; $k < 32; $k++){
					$d1 = $density[$i][$j][$k];
					$d2 = $density[$i + 1][$j][$k];
					$d3 = $density[$i][$j + 1][$k];
					$d4 = $density[$i + 1][$j + 1][$k];
					$d5 = ($density[$i][$j][$k + 1] - $d1) / 4;
					$d6 = ($density[$i + 1][$j][$k + 1] - $d2) / 4;
					$d7 = ($density[$i][$j + 1][$k + 1] - $d3) / 4;
					$d8 = ($density[$i + 1][$j + 1][$k + 1] - $d4) / 4;
					for($l = 0; $l < 4; $l++){
						$d9 = $d1;
						$d10 = $d3;
						for($m = 0; $m < 8; $m++){
							$dens = $d9;
							for($n = 0; $n < 8; $n++){
								if($dens > 0){
									$chunk->setBlockStateId($m + ($i << 3), $l + ($k << 2), $n + ($j << 3), $this->endStone);
								}
								$dens += ($d10 - $d9) / 8;
							}
							$d9 += ($d2 - $d1) / 8;
							$d10 += ($d4 - $d3) / 8;
						}
						$d1 += $d5;
						$d3 += $d7;
						$d2 += $d6;
						$d4 += $d8;
					}
				}
			}
		}
	}

	/**
	 * Single precision height offset of the density column ($x, $z) (0-2,
	 * 8 blocks apart) of a chunk: a cone around the origin, raised by any
	 * outer island centred within 12 chunks.
	 */
	public static function getIslandHeight(int $chunkX, int $chunkZ, int $x, int $z, SimplexNoise $islandNoise) : float{
		$threshold = Float32::of(-0.9);
		$x1 = Float32::of($chunkX * 2 + $x);
		$z1 = Float32::of($chunkZ * 2 + $z);
		$islandHeight1 = GenerationMath::clamp(
			Float32::of(100.0 - Float32::of(self::distance($x1, $z1) * 8.0)),
			-100.0,
			80.0
		);

		for($i = -12; $i <= 12; $i++){
			for($j = -12; $j <= 12; $j++){
				$x2 = $chunkX + $i;
				$z2 = $chunkZ + $j;
				if(($x2 * $x2) + ($z2 * $z2) > 4096 && $islandNoise->getValue($x2, $z2) < $threshold){
					$x1 = (float) ($x - $i * 2);
					$z1 = (float) ($z - $j * 2);
					$weight = Float32::of(Float32::of(abs(Float32::of($x2)) * 3439.0) + Float32::of(abs(Float32::of($z2)) * 147.0));
					$weight = Float32::of(fmod($weight, 13.0) + 9.0);
					$islandHeight2 = Float32::of(100.0 - Float32::of(self::distance($x1, $z1) * $weight));
					$islandHeight2 = GenerationMath::clamp($islandHeight2, -100.0, 80.0);
					$islandHeight1 = max($islandHeight1, $islandHeight2);
				}
			}
		}
		return $islandHeight1;
	}

	/**
	 * Single precision sqrt(x^2 + z^2).
	 */
	private static function distance(float $x, float $z) : float{
		return Float32::of(sqrt(Float32::of(Float32::of($x * $x) + Float32::of($z * $z))));
	}
}
