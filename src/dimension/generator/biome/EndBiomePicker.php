<?php

declare(strict_types=1);

namespace dimension\generator\biome;

use pocketmine\data\bedrock\BiomeIds;

/**
 * The end is a single biome.
 */
final class EndBiomePicker implements BiomePicker{

	private BiomeResult $result;

	public function __construct(){
		$this->result = new BiomeResult(BiomeIds::THE_END);
	}

	public function pick(int $x, int $y, int $z) : BiomeResult{
		return $this->result;
	}
}
