<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure;

use pocketmine\block\BlockTypeIds;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use VanillaAltay\world\generator\structure\template\TemplateArchive;
use function count;
use function in_array;

final class Shipwreck implements Structure{

	private const SALT = 165745295;

	private const BEACH_BIOMES = [BiomeIds::BEACH, BiomeIds::COLD_BEACH, BiomeIds::STONE_BEACH, BiomeIds::MUSHROOM_ISLAND_SHORE];
	private const OCEAN_BIOMES = [BiomeIds::OCEAN, BiomeIds::DEEP_OCEAN, BiomeIds::FROZEN_OCEAN];

	/**
	 * A wreck thrown on a beach never lands on its roof, so the upside down variants are left to the sea.
	 */
	private const BEACHED = [
		"shipwreck/with_mast",
		"shipwreck/sideways_full",
		"shipwreck/sideways_fronthalf",
		"shipwreck/sideways_backhalf",
		"shipwreck/rightsideup_full",
		"shipwreck/rightsideup_fronthalf",
		"shipwreck/rightsideup_backhalf",
		"shipwreck/with_mast_degraded",
		"shipwreck/rightsideup_full_degraded",
		"shipwreck/rightsideup_fronthalf_degraded",
		"shipwreck/rightsideup_backhalf_degraded"
	];

	private const SUNKEN = [
		"shipwreck/with_mast",
		"shipwreck/upsidedown_full",
		"shipwreck/upsidedown_fronthalf",
		"shipwreck/upsidedown_backhalf",
		"shipwreck/sideways_full",
		"shipwreck/sideways_fronthalf",
		"shipwreck/sideways_backhalf",
		"shipwreck/rightsideup_full",
		"shipwreck/rightsideup_fronthalf",
		"shipwreck/rightsideup_backhalf",
		"shipwreck/with_mast_degraded",
		"shipwreck/upsidedown_full_degraded",
		"shipwreck/upsidedown_fronthalf_degraded",
		"shipwreck/upsidedown_backhalf_degraded",
		"shipwreck/sideways_full_degraded",
		"shipwreck/sideways_fronthalf_degraded",
		"shipwreck/sideways_backhalf_degraded",
		"shipwreck/rightsideup_full_degraded",
		"shipwreck/rightsideup_fronthalf_degraded",
		"shipwreck/rightsideup_backhalf_degraded"
	];

	public function getName() : string{
		return "shipwreck";
	}

	public function getPlacement() : StructurePlacement{
		return new StructurePlacement(self::SALT, 4, 24, fn(int $biomeId) => in_array($biomeId, self::BEACH_BIOMES, true) || in_array($biomeId, self::OCEAN_BIOMES, true));
	}

	public function place(ChunkManager $world, Random $random, int $x, int $y, int $z) : void{
		$beached = in_array($this->getBiomeId($world, $x, $z), self::BEACH_BIOMES, true);
		$variants = $beached ? self::BEACHED : self::SUNKEN;

		$template = TemplateArchive::getInstance()->get($variants[$random->nextBoundedInt(count($variants))]);
		if($template === null){
			return;
		}

		$rotation = $random->nextBoundedInt(4);

		$template->place($world, $x, $this->getFloorY($world, $x, $y, $z), $z, $rotation);
	}

	private function getBiomeId(ChunkManager $world, int $x, int $z) : int{
		$chunk = $world->getChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE);

		return $chunk?->getBiomeId($x & Chunk::COORD_MASK, 0, $z & Chunk::COORD_MASK) ?? BiomeIds::OCEAN;
	}

	/**
	 * The surface handed over is the top of the water in an ocean, so the hull is dropped down to the sea bed.
	 */
	private function getFloorY(ChunkManager $world, int $x, int $y, int $z) : int{
		for(; $y > $world->getMinY(); --$y){
			$typeId = $world->getBlockAt($x, $y, $z)->getTypeId();
			if($typeId !== BlockTypeIds::WATER && $typeId !== BlockTypeIds::AIR){
				break;
			}
		}

		return $y;
	}
}
