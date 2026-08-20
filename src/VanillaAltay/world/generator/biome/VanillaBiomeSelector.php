<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\biome;

use pocketmine\data\bedrock\BiomeIds;
use VanillaAltay\world\generator\noise\NoiseRouter;

/**
 * Picks a biome from the climate fields, the way vanilla does since 1.18: the landscape is decided first and the
 * biome is only a label put on top of it, which is why oceans and beaches come from continentalness rather than
 * from climate.
 *
 * Temperature and humidity are quantized into five levels each, using vanilla's own band edges, and the pair is
 * then looked up in a table. Those edges assume a noise centered on zero, not a zero to one range.
 */
final class VanillaBiomeSelector{

	/**
	 * Every biome this selector can return, and nothing else: asking for any other one is a search that would
	 * never end well.
	 */
	public const GENERATED_BIOMES = [
		"ocean" => BiomeIds::OCEAN,
		"deep_ocean" => BiomeIds::DEEP_OCEAN,
		"frozen_ocean" => BiomeIds::FROZEN_OCEAN,
		"beach" => BiomeIds::BEACH,
		"cold_beach" => BiomeIds::COLD_BEACH,
		"plains" => BiomeIds::PLAINS,
		"forest" => BiomeIds::FOREST,
		"birch_forest" => BiomeIds::BIRCH_FOREST,
		"roofed_forest" => BiomeIds::ROOFED_FOREST,
		"taiga" => BiomeIds::TAIGA,
		"mega_taiga" => BiomeIds::MEGA_TAIGA,
		"jungle" => BiomeIds::JUNGLE,
		"savanna" => BiomeIds::SAVANNA,
		"swampland" => BiomeIds::SWAMPLAND,
		"desert" => BiomeIds::DESERT,
		"mesa" => BiomeIds::MESA,
		"ice_plains" => BiomeIds::ICE_PLAINS,
		"ice_mountains" => BiomeIds::ICE_MOUNTAINS,
		"extreme_hills" => BiomeIds::EXTREME_HILLS
	];

	private const TEMPERATURE_BANDS = [-0.45, -0.15, 0.3, 0.55];
	private const HUMIDITY_BANDS = [-0.35, -0.1, 0.1, 0.3];

	private const DEEP_OCEAN_BAND = -0.455;
	private const OCEAN_BAND = -0.19;
	private const COAST_BAND = -0.11;

	/**
	 * Biome for each temperature level (coldest first) crossed with each humidity level (driest first).
	 */
	private const MIDDLE_BIOMES = [
		[BiomeIds::ICE_PLAINS, BiomeIds::ICE_PLAINS, BiomeIds::ICE_PLAINS, BiomeIds::TAIGA, BiomeIds::TAIGA],
		[BiomeIds::PLAINS, BiomeIds::PLAINS, BiomeIds::FOREST, BiomeIds::TAIGA, BiomeIds::MEGA_TAIGA],
		[BiomeIds::PLAINS, BiomeIds::PLAINS, BiomeIds::FOREST, BiomeIds::BIRCH_FOREST, BiomeIds::ROOFED_FOREST],
		[BiomeIds::SAVANNA, BiomeIds::SAVANNA, BiomeIds::FOREST, BiomeIds::JUNGLE, BiomeIds::JUNGLE],
		[BiomeIds::DESERT, BiomeIds::DESERT, BiomeIds::DESERT, BiomeIds::DESERT, BiomeIds::DESERT]
	];

	public function __construct(private NoiseRouter $router){}

	public function pickBiome(int $x, int $z) : int{
		return $this->pickBiomeFrom(
			$this->router->getContinentalness($x, $z),
			$this->router->getTemperature($x, $z),
			$this->router->getHumidity($x, $z),
			$this->router->getErosion($x, $z),
			$this->router->getPeaksValleys($x, $z)
		);
	}

	public function pickBiomeFrom(float $continentalness, float $rawTemperature, float $rawHumidity, float $erosion, float $peaksValleys) : int{
		$temperature = self::quantize($rawTemperature, self::TEMPERATURE_BANDS);

		//oceans and shores are read from continentalness alone. Deciding them from the terrain height instead
		//makes the biome flicker with every ripple of the ground near sea level, and shreds the map into slivers.
		if($continentalness < self::OCEAN_BAND){
			return match(true){
				$temperature === 0 => BiomeIds::FROZEN_OCEAN,
				$continentalness < self::DEEP_OCEAN_BAND => BiomeIds::DEEP_OCEAN,
				default => BiomeIds::OCEAN
			};
		}

		if($continentalness < self::COAST_BAND){
			return $temperature === 0 ? BiomeIds::COLD_BEACH : BiomeIds::BEACH;
		}

		//mountains come from the fields that raise them rather than from the height they happen to reach, so the
		//biome covers a whole massif instead of only its summit
		if($peaksValleys > 0.5 && $erosion < -0.2){
			return $temperature <= 1 ? BiomeIds::ICE_MOUNTAINS : BiomeIds::EXTREME_HILLS;
		}

		$humidity = self::quantize($rawHumidity, self::HUMIDITY_BANDS);

		//swamps sit in warm, wet, heavily eroded lowlands
		if($temperature >= 2 && $humidity === 4 && $erosion > 0.25){
			return BiomeIds::SWAMPLAND;
		}

		//vanilla puts badlands on hot plateaus, which is the closest thing this generator has to high, flat, dry land
		if($temperature === 4 && $humidity <= 1 && $erosion < -0.2){
			return BiomeIds::MESA;
		}

		return self::MIDDLE_BIOMES[$temperature][$humidity];
	}

	/**
	 * @param float[] $bands
	 */
	private static function quantize(float $value, array $bands) : int{
		foreach($bands as $level => $edge){
			if($value < $edge){
				return $level;
			}
		}

		return count($bands);
	}
}
