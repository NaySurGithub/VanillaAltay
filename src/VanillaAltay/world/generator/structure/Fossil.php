<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure;

use pocketmine\block\BlockTypeIds;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use VanillaAltay\world\generator\structure\template\StructureTemplate;
use VanillaAltay\world\generator\structure\template\TemplateArchive;
use function in_array;

final class Fossil implements UndergroundStructure{

	private const RARITY = 64;
	private const MAX_LOOSE_CORNERS = 4;

	private const BIOMES = [
		BiomeIds::DESERT,
		BiomeIds::DESERT_HILLS,
		BiomeIds::DESERT_MUTATED,
		BiomeIds::SWAMPLAND,
		BiomeIds::SWAMPLAND_MUTATED,
		BiomeIds::MANGROVE_SWAMP
	];

	private const GROUND = [
		BlockTypeIds::SAND,
		BlockTypeIds::SANDSTONE,
		BlockTypeIds::SMOOTH_SANDSTONE,
		BlockTypeIds::GRAVEL,
		BlockTypeIds::DIRT,
		BlockTypeIds::STONE,
		BlockTypeIds::DEEPSLATE,
		BlockTypeIds::TUFF,
		BlockTypeIds::GRANITE,
		BlockTypeIds::DIORITE,
		BlockTypeIds::ANDESITE
	];

	public function getName() : string{
		return "fossil";
	}

	public function getPlacement() : StructurePlacement{
		return new StructurePlacement(0, 0, 1, fn(int $biomeId) => in_array($biomeId, self::BIOMES, true));
	}

	public function getMinY() : int{
		return 10;
	}

	public function getMaxY() : int{
		return 45;
	}

	public function getAttempts() : int{
		return 1;
	}

	public function place(ChunkManager $world, Random $random, int $x, int $y, int $z) : void{
		if($random->nextBoundedInt(self::RARITY) !== 0){
			return;
		}

		$name = "fossil/" . ($random->nextBoolean() ? "skull" : "spine") . "_" . ($random->nextBoundedInt(4) + 1);
		if($random->nextBoundedInt(4) === 0){
			$name .= "_coal";
		}

		$template = TemplateArchive::getInstance()->get($name);
		if($template === null){
			return;
		}

		$rotation = $random->nextBoundedInt(4);
		if(!self::isBuried($world, $template, $x, $y, $z, $rotation)){
			return;
		}

		$template->place($world, $x, $y, $z, $rotation);
	}

	/**
	 * A fossil only forms where its corners sit in sand or stone, which keeps it out of caves and out of the open air.
	 */
	private static function isBuried(ChunkManager $world, StructureTemplate $template, int $x, int $y, int $z, int $rotation) : bool{
		[$spanX, $spanZ] = $rotation === StructureTemplate::ROTATION_90 || $rotation === StructureTemplate::ROTATION_270
			? [$template->getSizeZ(), $template->getSizeX()]
			: [$template->getSizeX(), $template->getSizeZ()];

		$loose = 0;

		foreach([0, $spanX - 1] as $offsetX){
			foreach([0, $template->getSizeY() - 1] as $offsetY){
				foreach([0, $spanZ - 1] as $offsetZ){
					if(!in_array($world->getBlockAt($x + $offsetX, $y + $offsetY, $z + $offsetZ)->getTypeId(), self::GROUND, true)){
						++$loose;
					}
				}
			}
		}

		return $loose <= self::MAX_LOOSE_CORNERS;
	}
}
