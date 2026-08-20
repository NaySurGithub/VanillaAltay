<?php

declare(strict_types=1);

namespace VanillaAltay\world\biome;

use pocketmine\block\Block;
use pocketmine\world\biome\Biome;

/**
 * A biome defined entirely by its ground cover and climate, so that the missing vanilla biomes don't each need
 * a class of their own.
 */
final class SimpleBiome extends Biome{

	/**
	 * @param Block[] $groundCover
	 */
	public function __construct(private string $name, array $groundCover, float $temperature, float $rainfall){
		$this->setGroundCover($groundCover);
		$this->temperature = $temperature;
		$this->rainfall = $rainfall;
	}

	public function getName() : string{
		return $this->name;
	}
}
