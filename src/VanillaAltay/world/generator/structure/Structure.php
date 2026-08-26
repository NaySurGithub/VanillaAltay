<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure;

use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;

interface Structure
{
	public function getName() : string;

	public function getPlacement() : StructurePlacement;

	/**
	 * Builds the structure around the given surface position. The caller has already checked the placement rules.
	 */
	public function place(ChunkManager $world, Random $random, int $x, int $y, int $z) : void;
}
