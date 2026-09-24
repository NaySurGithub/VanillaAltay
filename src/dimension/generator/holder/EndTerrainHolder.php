<?php

declare(strict_types=1);

namespace dimension\generator\holder;

use dimension\generator\noise\OctaveNoise;
use dimension\generator\noise\SimplexNoise;
use dimension\generator\random\RandomSource;

/**
 * Noises of the end terrain, drawn one after the other from a single copy
 * of the world seeded source: two 16 octave roughness noises, the 8 octave
 * detail noise that blends them, then the island placement noise.
 */
final class EndTerrainHolder extends RandomizedObjectHolder{

	private OctaveNoise $roughnessNoiseOctaves;
	private OctaveNoise $roughnessNoiseOctaves2;
	private OctaveNoise $detailNoiseOctaves;
	private SimplexNoise $islandNoise;

	public function __construct(RandomSource $randomSourceProvider){
		parent::__construct($randomSourceProvider);
		$random = $randomSourceProvider->identical();
		$this->roughnessNoiseOctaves = new OctaveNoise($random, 16);
		$this->roughnessNoiseOctaves2 = new OctaveNoise($random, 16);
		$this->detailNoiseOctaves = new OctaveNoise($random, 8);
		$this->islandNoise = new SimplexNoise($random);
	}

	public function getRoughnessNoiseOctaves() : OctaveNoise{
		return $this->roughnessNoiseOctaves;
	}

	public function getRoughnessNoiseOctaves2() : OctaveNoise{
		return $this->roughnessNoiseOctaves2;
	}

	public function getDetailNoiseOctaves() : OctaveNoise{
		return $this->detailNoiseOctaves;
	}

	public function getIslandNoise() : SimplexNoise{
		return $this->islandNoise;
	}
}
