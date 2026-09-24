<?php

declare(strict_types=1);

namespace dimension\generator\biome;

use dimension\generator\math\Float32;
use dimension\generator\noise\NormalNoise;
use dimension\generator\random\LegacyRandom;
use pocketmine\data\bedrock\BiomeIds;

/**
 * Picks the nether biome whose climate point (temperature, humidity,
 * offset) is nearest to the temperature and humidity noises.
 */
final class NetherBiomePicker implements BiomePicker{

	private NormalNoise $temperatureNoise;
	private NormalNoise $humidityNoise;
	/** @var array<int, float[]> biome id => climate point */
	private array $points;

	public function __construct(LegacyRandom $random){
		$this->temperatureNoise = new NormalNoise($random->fork(), -10, [1.5, 0, 1, 0, 0, 0]);
		$this->humidityNoise = new NormalNoise($random->fork(), -8, [1, 1, 0, 0, 0, 0]);
		$this->points = [
			BiomeIds::BASALT_DELTAS => [-0.5, 0.0, Float32::of(0.175)],
			BiomeIds::CRIMSON_FOREST => [Float32::of(0.4), 0.0, 0.0],
			BiomeIds::HELL => [0.0, 0.0, 0.0],
			BiomeIds::SOULSAND_VALLEY => [0.0, -0.5, 0.0],
			BiomeIds::WARPED_FOREST => [0.0, 0.5, Float32::of(0.375)]
		];
	}

	public function pick(int $x, int $y, int $z) : NetherBiomeResult{
		$temperature = $this->temperatureNoise->getValue($x, $y, $z);
		$humidity = $this->humidityNoise->getValue($x, $y, $z);
		$distance = 3.4028234663852886E38;
		$biomeId = BiomeIds::HELL;
		foreach($this->points as $id => [$pointX, $pointY, $pointZ]){
			$dx = $temperature - $pointX;
			$dy = $humidity - $pointY;
			$dz = 0.0 - $pointZ;
			$delta = $dx * $dx + $dy * $dy + $dz * $dz;
			if($delta < $distance){
				$distance = $delta;
				$biomeId = $id;
			}
		}
		return new NetherBiomeResult($biomeId, $temperature, $humidity);
	}
}
