<?php

declare(strict_types=1);

namespace dimension\generator\nether\object;

use dimension\generator\ChunkHash;
use dimension\generator\math\Float32;
use dimension\generator\math\GenerationMath;
use dimension\generator\math\Int64;
use dimension\generator\object\BlockManager;
use dimension\generator\Populator;
use dimension\generator\random\Xoroshiro128;
use pocketmine\world\ChunkManager;
use function in_array;
use function max;
use function min;
use function ord;
use function strlen;
use const M_PI;

/**
 * Ore feature: clusters of a block replacing stone, deepslate or netherrack.
 * The chunk random is salted with the 32-bit string hash of name().
 */
abstract class OreGeneratorFeature implements Populator{

	public const UNIFORM = 0;
	public const TRIANGLE = 1;

	private const REPLACEABLE = ["minecraft:stone", "minecraft:deepslate", "minecraft:netherrack"];

	protected Xoroshiro128 $random;

	public function __construct(){
		$this->random = new Xoroshiro128(0);
	}

	abstract public function name() : string;

	abstract public function getState() : string;

	abstract public function getClusterCount() : int;

	abstract public function getClusterSize() : int;

	abstract public function getMinHeight() : int;

	abstract public function getMaxHeight() : int;

	public function getSkipAir() : float{
		return 0.0;
	}

	public function getConcentration() : int{
		return self::UNIFORM;
	}

	public function isRare() : bool{
		return false;
	}

	public function canBeReplaced(string $id) : bool{
		return in_array($id, self::REPLACEABLE, true);
	}

	private static function stringHash(string $value) : int{
		$hash = 0;
		$length = strlen($value);
		for($i = 0; $i < $length; ++$i){
			$hash = Int64::toInt32($hash * 31 + ord($value[$i]));
		}
		return $hash;
	}

	final public function populate(BlockManager $root, ChunkManager $world, int $chunkX, int $chunkZ, int $seed) : void{
		$random = $this->random;
		$random->setSeed(ChunkHash::hash($chunkX, $chunkZ) ^ Int64::add($seed, self::stringHash($this->name())));
		$sx = $chunkX << 4;
		$sz = $chunkZ << 4;
		$manager = new BlockManager($world);
		for($i = 0; $i < ($this->isRare() ? ($random->nextInt($this->getClusterCount()) === 0 ? 1 : 0) : $this->getClusterCount()); $i++){
			$object = new BlockManager($world);
			$maxY = min($this->getMaxHeight(), $world->getMaxY());
			$minY = max($this->getMinHeight(), $world->getMinY());
			$x = $sx + $random->nextInt(15);
			$z = $sz + $random->nextInt(15);
			if($this->getConcentration() === self::TRIANGLE){
				$y = GenerationMath::randomRangeTriangle($random, $minY, $maxY);
			}else{
				$y = $minY + $random->nextBoundedInt(($maxY - $minY) + 1);
			}

			if(!$this->canBeReplaced(WorldQuery::blockId($world, $x, $y, $z))){
				continue;
			}
			if($this->getClusterSize() === 1){
				$object->setBlockStateAt($x, $y, $z, $this->getState());
			}else{
				$random->setSeed($seed ^ ChunkHash::hash($chunkX, $chunkZ) ^ ($x + $y + $z));
				$this->spawn($object, $world, $random, $x, $y, $z);
				$random->setSeed($seed ^ ChunkHash::hash($chunkX, $chunkZ));
			}
			$skip = false;
			if($this->getSkipAir() != 0){
				$air = false;
				foreach($object->getBlocks() as $placed){
					if(WorldQuery::blockId($world, $placed->x, $placed->y, $placed->z) === WorldQuery::AIR){
						$air = true;
						break;
					}
				}
				if($air){
					$skip = $random->nextFloat() < $this->getSkipAir();
				}
			}
			if(!$skip){
				$manager->merge($object);
			}
		}

		$root->merge($manager);
	}

	protected function spawn(BlockManager $level, ChunkManager $world, Xoroshiro128 $rand, int $x, int $y, int $z) : void{
		$size = $this->getClusterSize();
		$pi = Float32::of(M_PI);
		$piScaled = Float32::of($rand->nextFloat() * $pi);
		$sinOffset = Float32::of(Float32::of(GenerationMath::sinFloat($piScaled) * $size) / 8.0);
		$cosOffset = Float32::of(Float32::of(GenerationMath::cosFloat($piScaled) * $size) / 8.0);
		$scaleMaxX = Float32::of(($x + 8) + $sinOffset);
		$scaleMinX = Float32::of(($x + 8) - $sinOffset);
		$scaleMaxZ = Float32::of(($z + 8) + $cosOffset);
		$scaleMinZ = Float32::of(($z + 8) - $cosOffset);
		$scaleMaxY = (float) ($y + $rand->nextBoundedInt(3) - 2);
		$scaleMinY = (float) ($y + $rand->nextBoundedInt(3) - 2);

		for($i = 0; $i < $size; ++$i){
			$sizeIncr = Float32::of($i / $size);
			$scaleX = $scaleMaxX + ($scaleMinX - $scaleMaxX) * $sizeIncr;
			$scaleY = $scaleMaxY + ($scaleMinY - $scaleMaxY) * $sizeIncr;
			$scaleZ = $scaleMaxZ + ($scaleMinZ - $scaleMaxZ) * $sizeIncr;
			$randSizeOffset = $rand->nextDouble() * $size / 16.0;
			$sinFactor = Float32::of(GenerationMath::sinFloat(Float32::of($pi * $sizeIncr)) + 1.0);
			$randVec1 = $sinFactor * $randSizeOffset + 1.0;
			$randVec2 = $sinFactor * $randSizeOffset + 1.0;
			$minX = GenerationMath::floor($scaleX - $randVec1 / 2.0);
			$minY = GenerationMath::floor($scaleY - $randVec2 / 2.0);
			$minZ = GenerationMath::floor($scaleZ - $randVec1 / 2.0);
			$maxX = GenerationMath::floor($scaleX + $randVec1 / 2.0);
			$maxY = GenerationMath::floor($scaleY + $randVec2 / 2.0);
			$maxZ = GenerationMath::floor($scaleZ + $randVec1 / 2.0);

			for($xSeg = $minX; $xSeg <= $maxX; ++$xSeg){
				$xVal = ($xSeg + 0.5 - $scaleX) / ($randVec1 / 2.0);

				if($xVal * $xVal < 1.0){
					for($ySeg = $minY; $ySeg <= $maxY; ++$ySeg){
						if($ySeg < -64){
							continue;
						}
						$yVal = ($ySeg + 0.5 - $scaleY) / ($randVec2 / 2.0);

						if($xVal * $xVal + $yVal * $yVal < 1.0){
							for($zSeg = $minZ; $zSeg <= $maxZ; ++$zSeg){
								$zVal = ($zSeg + 0.5 - $scaleZ) / ($randVec1 / 2.0);

								if($xVal * $xVal + $yVal * $yVal + $zVal * $zVal < 1.0){
									if($this->canBeReplaced($level->getBlockIdAt($xSeg, $ySeg, $zSeg))){
										$level->setBlockStateAt($xSeg, $ySeg, $zSeg, $this->getState());
									}
								}
							}
						}
					}
				}
			}
		}
	}
}
