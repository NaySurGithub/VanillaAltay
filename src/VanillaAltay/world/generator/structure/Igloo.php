<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use VanillaAltay\world\generator\structure\template\TemplateArchive;

final class Igloo implements Structure
{
	private const SALT = 14357618;

	private const BASEMENT_MIN_SEGMENTS = 3;
	private const BASEMENT_EXTRA_SEGMENTS = 8;

	public function getName() : string
	{
		return "igloo";
	}

	public function getPlacement() : StructurePlacement
	{
		return new StructurePlacement(self::SALT, 8, 32, fn(int $biomeId) => $biomeId === BiomeIds::ICE_PLAINS || $biomeId === BiomeIds::COLD_TAIGA || $biomeId === BiomeIds::SNOWY_SLOPES);
	}

	public function place(ChunkManager $world, Random $random, int $x, int $y, int $z) : void
	{
		$archive = TemplateArchive::getInstance();

		$top = $archive->get("igloo/top");
		if ($top === null) {
			return;
		}

		$top->place($world, $x, $y, $z);

		$shaftX = $x + 3;
		$shaftZ = $z + 5;

		if (!$random->nextBoolean()) {
			$this->setBlockAt($world, $shaftX, $y, $shaftZ, VanillaBlocks::SNOW_LAYER());

			return;
		}

		$middle = $archive->get("igloo/middle");
		$bottom = $archive->get("igloo/bottom");
		if ($middle === null || $bottom === null) {
			return;
		}

		$depth = 0;
		$segments = $random->nextBoundedInt(self::BASEMENT_EXTRA_SEGMENTS) + self::BASEMENT_MIN_SEGMENTS;

		for ($i = 0; $i < $segments; ++$i) {
			$depth += $middle->getSizeY();
			$middle->place($world, $x + 2, $y - $depth, $z + 4);
		}

		$bottom->place($world, $x, $y - $depth - $bottom->getSizeY(), $z - 2);

		$this->setBlockAt($world, $shaftX, $y, $shaftZ, VanillaBlocks::AIR());
	}

	/**
	 * The ladder shaft is a hole in the top template: it is closed with snow without a basement and opened
	 * with one.
	 */
	private function setBlockAt(ChunkManager $world, int $x, int $y, int $z, Block $block) : void
	{
		if (!$world->isInWorld($x, $y, $z)) {
			return;
		}

		$chunk = $world->getChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE);
		$chunk?->setBlockStateId($x & Chunk::COORD_MASK, $y, $z & Chunk::COORD_MASK, $block->getStateId());
	}
}
