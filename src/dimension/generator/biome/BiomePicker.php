<?php

declare(strict_types=1);

namespace dimension\generator\biome;

interface BiomePicker{

	public function pick(int $x, int $y, int $z) : BiomeResult;
}
