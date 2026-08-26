<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure;

use pocketmine\data\bedrock\BiomeIds;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use VanillaAltay\world\generator\populator\SurfacePopulator;
use VanillaAltay\world\generator\structure\template\StructureTemplate;
use VanillaAltay\world\generator\structure\template\TemplateArchive;

use function count;
use function in_array;

final class PillagerOutpost implements Structure
{
	private const SALT = 165745296;

	private const BIOMES = [
		BiomeIds::PLAINS,
		BiomeIds::DESERT,
		BiomeIds::SAVANNA,
		BiomeIds::TAIGA,
		BiomeIds::ICE_PLAINS,
		BiomeIds::COLD_TAIGA,
		BiomeIds::SUNFLOWER_PLAINS,
		BiomeIds::MEADOW,
		BiomeIds::GROVE,
		BiomeIds::SNOWY_SLOPES,
		BiomeIds::JAGGED_PEAKS,
		BiomeIds::FROZEN_PEAKS,
		BiomeIds::STONY_PEAKS,
		BiomeIds::CHERRY_GROVE,
	];

	private const FEATURES = [
		"pillager_outpost/feature_cage1",
		"pillager_outpost/feature_cage2",
		"pillager_outpost/feature_cage_with_allays",
		"pillager_outpost/feature_logs",
		"pillager_outpost/feature_tent1",
		"pillager_outpost/feature_tent2",
		"pillager_outpost/feature_targets",
	];

	public function getName() : string
	{
		return "pillager_outpost";
	}

	public function getPlacement() : StructurePlacement
	{
		return new StructurePlacement(self::SALT, 8, 32, fn(int $biomeId) => in_array($biomeId, self::BIOMES, true));
	}

	public function place(ChunkManager $world, Random $random, int $x, int $y, int $z) : void
	{
		$archive = TemplateArchive::getInstance();

		$tower = $archive->get($random->nextBoundedInt(4) === 0 ? "pillager_outpost/watchtower_overgrown" : "pillager_outpost/watchtower");
		if ($tower === null) {
			return;
		}

		$tower->place($world, $x, $y + 1, $z, $random->nextBoundedInt(4));

		foreach ([[-1, -1], [-1, 1], [1, -1], [1, 1]] as [$signX, $signZ]) {
			if (!$random->nextBoolean()) {
				continue;
			}

			$feature = $archive->get(self::FEATURES[$random->nextBoundedInt(count(self::FEATURES))]);
			if ($feature === null) {
				continue;
			}

			$rotation = $random->nextBoundedInt(4);
			[$spanX, $spanZ] = self::rotatedSpan($feature, $rotation);

			$featureX = $x + ($signX * (16 - $random->nextBoundedInt(16 - $spanX)));
			$featureZ = $z + ($signZ * (16 - $random->nextBoundedInt(16 - $spanZ)));

			$featureY = SurfacePopulator::getHighestWorkableBlock($world, $featureX, $featureZ);
			if ($featureY === -1) {
				continue;
			}

			$feature->place($world, $featureX, $featureY, $featureZ, $rotation);
		}
	}

	/**
	 * @return int[]
	 * @phpstan-return array{int, int}
	 */
	private static function rotatedSpan(StructureTemplate $template, int $rotation) : array
	{
		return $rotation === StructureTemplate::ROTATION_90 || $rotation === StructureTemplate::ROTATION_270
			? [$template->getSizeZ(), $template->getSizeX()]
			: [$template->getSizeX(), $template->getSizeZ()];
	}
}
