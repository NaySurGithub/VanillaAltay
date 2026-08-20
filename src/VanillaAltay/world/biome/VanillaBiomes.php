<?php

declare(strict_types=1);

namespace VanillaAltay\world\biome;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\utils\Random;
use pocketmine\world\biome\Biome;
use pocketmine\world\biome\BiomeRegistry;
use pocketmine\world\generator\object\TreeType;
use pocketmine\world\generator\populator\TallGrass;
use VanillaAltay\world\generator\object\DarkOakTree;
use VanillaAltay\world\generator\populator\Cactus;
use VanillaAltay\world\generator\populator\Flower;
use VanillaAltay\world\generator\populator\GroundPlant;
use VanillaAltay\world\generator\populator\Lake;
use VanillaAltay\world\generator\populator\SugarCane;
use VanillaAltay\world\generator\populator\TreePopulator;

/**
 * Registers every biome the overworld generator uses, with its ground cover and its decoration.
 *
 * Altay only ships thirteen biomes and most of them decorate with little more than grass and trees, so the
 * plugin replaces them all rather than registering the missing ones and leaving the rest half empty.
 */
final class VanillaBiomes{

	private const GRASS_COVER = [BlockTypeIds::GRASS, BlockTypeIds::DIRT];
	private const SAND_COVER = [BlockTypeIds::SAND, BlockTypeIds::RED_SAND];

	private function __construct(){
		//NOOP
	}

	public static function register() : void{
		$registry = BiomeRegistry::getInstance();

		$registry->register(BiomeIds::OCEAN, self::ocean("Ocean", 46, 58));
		$registry->register(BiomeIds::DEEP_OCEAN, self::ocean("Deep Ocean", 30, 45));
		$registry->register(BiomeIds::FROZEN_OCEAN, self::ocean("Frozen Ocean", 46, 58, 0.0));
		$registry->register(BiomeIds::RIVER, self::ocean("River", 58, 62));

		$registry->register(BiomeIds::BEACH, self::beach("Beach", 0.8, 0.4));
		$registry->register(BiomeIds::COLD_BEACH, self::beach("Cold Beach", 0.05, 0.3));

		$registry->register(BiomeIds::PLAINS, self::plains());
		//a vanilla forest is mostly oak with birch mixed in, a birch forest is the other way around
		$registry->register(BiomeIds::FOREST, self::forest("Forest", [TreeType::OAK, TreeType::OAK, TreeType::OAK, TreeType::BIRCH], 10));
		$registry->register(BiomeIds::BIRCH_FOREST, self::forest("Birch Forest", [TreeType::BIRCH], 10));
		$registry->register(BiomeIds::ROOFED_FOREST, self::roofedForest());
		$registry->register(BiomeIds::TAIGA, self::taiga("Taiga", 6));
		$registry->register(BiomeIds::MEGA_TAIGA, self::taiga("Mega Taiga", 10));
		$registry->register(BiomeIds::SWAMPLAND, self::swamp());
		$registry->register(BiomeIds::JUNGLE, self::jungle());
		$registry->register(BiomeIds::SAVANNA, self::savanna());
		$registry->register(BiomeIds::DESERT, self::desert());
		$registry->register(BiomeIds::MESA, self::mesa());
		$registry->register(BiomeIds::ICE_PLAINS, self::icePlains());
		$registry->register(BiomeIds::ICE_MOUNTAINS, self::iceMountains());
		$registry->register(BiomeIds::EXTREME_HILLS, self::extremeHills());
	}

	private static function ocean(string $name, int $minElevation, int $maxElevation, float $temperature = 0.5) : Biome{
		$biome = self::make($name, [
			VanillaBlocks::GRAVEL(),
			VanillaBlocks::GRAVEL(),
			VanillaBlocks::GRAVEL(),
			VanillaBlocks::GRAVEL(),
			VanillaBlocks::GRAVEL()
		], $temperature, 0.5, $minElevation, $maxElevation);
		$biome->addPopulator(self::grass(2));

		return $biome;
	}

	private static function beach(string $name, float $temperature, float $rainfall) : Biome{
		$biome = self::make($name, [
			VanillaBlocks::SAND(),
			VanillaBlocks::SAND(),
			VanillaBlocks::SANDSTONE(),
			VanillaBlocks::SANDSTONE(),
			VanillaBlocks::SANDSTONE()
		], $temperature, $rainfall, 60, 66);
		$biome->addPopulator(self::sugarCane(2));

		return $biome;
	}

	private static function plains() : Biome{
		$biome = self::grassy("Plains", 0.8, 0.4);
		$biome->addPopulator(self::trees([TreeType::OAK], 0, 2));
		$biome->addPopulator(self::grass(12));
		$biome->addPopulator(new Flower([
			VanillaBlocks::DANDELION(),
			VanillaBlocks::POPPY(),
			VanillaBlocks::AZURE_BLUET(),
			VanillaBlocks::CORNFLOWER(),
			VanillaBlocks::OXEYE_DAISY()
		]));
		$biome->addPopulator(self::sugarCane(1));
		$biome->addPopulator(self::pumpkins());
		$biome->addPopulator(self::waterLake());

		return $biome;
	}

	/**
	 * @param TreeType[] $trees
	 */
	private static function forest(string $name, array $trees, int $amount, float $temperature = 0.7, float $rainfall = 0.8) : Biome{
		$biome = self::grassy($name, $temperature, $rainfall);
		$biome->addPopulator(self::trees($trees, $amount, 2));
		$biome->addPopulator(self::grass(4));
		$biome->addPopulator(new Flower([
			VanillaBlocks::DANDELION(),
			VanillaBlocks::POPPY(),
			VanillaBlocks::LILY_OF_THE_VALLEY()
		]));
		$biome->addPopulator(self::mushrooms());
		$biome->addPopulator(self::waterLake());

		return $biome;
	}

	private static function roofedForest() : Biome{
		$biome = self::grassy("Roofed Forest", 0.7, 0.8);
		$biome->addPopulator(self::darkOaks(10, 3));
		$biome->addPopulator(self::trees([TreeType::OAK, TreeType::BIRCH], 1, 2));
		$biome->addPopulator(self::grass(4));
		$biome->addPopulator(self::mushrooms());

		return $biome;
	}

	private static function taiga(string $name, int $trees) : Biome{
		$biome = self::grassy($name, 0.3, 0.8);
		$biome->addPopulator(self::trees([TreeType::SPRUCE], $trees, 2));
		$biome->addPopulator(self::grass(2));
		$biome->addPopulator(self::mushrooms());
		$biome->addPopulator(self::waterLake());

		return $biome;
	}

	private static function swamp() : Biome{
		$biome = self::grassy("Swampland", 0.8, 0.9);
		$biome->addPopulator(self::trees([TreeType::OAK], 2, 1));
		$biome->addPopulator(self::grass(6));
		$biome->addPopulator(self::mushrooms());
		$biome->addPopulator(new GroundPlant(VanillaBlocks::LILY_PAD(), [BlockTypeIds::WATER]));
		$biome->addPopulator(self::sugarCane(4));

		return $biome;
	}

	private static function jungle() : Biome{
		$biome = self::grassy("Jungle", 0.95, 0.9);
		$biome->addPopulator(self::trees([TreeType::JUNGLE, TreeType::JUNGLE, TreeType::JUNGLE, TreeType::OAK], 14, 4));
		$biome->addPopulator(self::grass(16));
		$biome->addPopulator(self::mushrooms());
		$biome->addPopulator(self::waterLake());

		return $biome;
	}

	private static function savanna() : Biome{
		$biome = self::grassy("Savanna", 1.2, 0.0);
		$biome->addPopulator(self::trees([TreeType::ACACIA], 1, 1));
		$biome->addPopulator(self::grass(16));
		$biome->addPopulator(self::sugarCane(1));

		return $biome;
	}

	private static function desert() : Biome{
		$biome = self::make("Desert", [
			VanillaBlocks::SAND(),
			VanillaBlocks::SAND(),
			VanillaBlocks::SANDSTONE(),
			VanillaBlocks::SANDSTONE(),
			VanillaBlocks::SANDSTONE()
		], 2.0, 0.0, 63, 74);
		$biome->addPopulator(self::cactus(3));
		$biome->addPopulator(self::deadBushes(2));
		$biome->addPopulator(self::sugarCane(2));

		return $biome;
	}

	private static function mesa() : Biome{
		$biome = self::make("Mesa", [
			VanillaBlocks::RED_SAND(),
			VanillaBlocks::RED_SAND(),
			VanillaBlocks::STAINED_CLAY(),
			VanillaBlocks::STAINED_CLAY(),
			VanillaBlocks::STAINED_CLAY()
		], 2.0, 0.0, 63, 90);
		$biome->addPopulator(self::cactus(1));
		$biome->addPopulator(self::deadBushes(4));

		return $biome;
	}

	private static function icePlains() : Biome{
		$biome = self::grassy("Ice Plains", 0.0, 0.5);
		$biome->addPopulator(self::trees([TreeType::SPRUCE], 0, 1));
		$biome->addPopulator(self::grass(2));

		return $biome;
	}

	private static function iceMountains() : Biome{
		$biome = self::make("Ice Mountains", [
			VanillaBlocks::SNOW(),
			VanillaBlocks::DIRT(),
			VanillaBlocks::DIRT(),
			VanillaBlocks::DIRT(),
			VanillaBlocks::DIRT()
		], 0.0, 0.5, 63, 128);
		$biome->addPopulator(self::trees([TreeType::SPRUCE], 0, 1));

		return $biome;
	}

	private static function extremeHills() : Biome{
		$biome = self::grassy("Mountains", 0.2, 0.3);
		$biome->addPopulator(self::trees([TreeType::SPRUCE, TreeType::OAK], 1, 1));
		$biome->addPopulator(self::grass(4));
		$biome->addPopulator(self::waterLake());

		return $biome;
	}

	private static function grassy(string $name, float $temperature, float $rainfall) : SimpleBiome{
		return self::make($name, [
			VanillaBlocks::GRASS(),
			VanillaBlocks::DIRT(),
			VanillaBlocks::DIRT(),
			VanillaBlocks::DIRT(),
			VanillaBlocks::DIRT()
		], $temperature, $rainfall, 63, 81);
	}

	/**
	 * @param Block[] $groundCover
	 */
	private static function make(string $name, array $groundCover, float $temperature, float $rainfall, int $minElevation, int $maxElevation) : SimpleBiome{
		$biome = new SimpleBiome($name, $groundCover, $temperature, $rainfall);
		$biome->setElevation($minElevation, $maxElevation);

		return $biome;
	}

	/**
	 * @param TreeType[] $types one entry per slot of the roll, so repeating a type makes it more likely
	 */
	private static function trees(array $types, int $base, int $random = 1) : TreePopulator{
		$trees = TreePopulator::ofTypes($types);
		$trees->setBaseAmount($base);
		$trees->setRandomAmount($random);

		return $trees;
	}

	private static function darkOaks(int $base, int $random) : TreePopulator{
		$trees = new TreePopulator(fn(Random $treeRandom) => new DarkOakTree());
		$trees->setBaseAmount($base);
		$trees->setRandomAmount($random);

		return $trees;
	}

	private static function grass(int $amount) : TallGrass{
		$grass = new TallGrass();
		$grass->setBaseAmount($amount);

		return $grass;
	}

	private static function sugarCane(int $amount) : SugarCane{
		$cane = new SugarCane();
		$cane->setBaseAmount($amount);
		$cane->setRandomAmount(2);

		return $cane;
	}

	private static function cactus(int $amount) : Cactus{
		$cactus = new Cactus();
		$cactus->setBaseAmount($amount);
		$cactus->setRandomAmount(2);

		return $cactus;
	}

	private static function deadBushes(int $amount) : GroundPlant{
		$bushes = new GroundPlant(VanillaBlocks::DEAD_BUSH(), self::SAND_COVER);
		$bushes->setBaseAmount($amount);

		return $bushes;
	}

	private static function pumpkins() : GroundPlant{
		$pumpkins = new GroundPlant(VanillaBlocks::PUMPKIN(), self::GRASS_COVER);
		$pumpkins->setBaseAmount(1);
		$pumpkins->setRandomAmount(1);
		$pumpkins->setChance(32);

		return $pumpkins;
	}

	private static function mushrooms() : GroundPlant{
		$mushrooms = new GroundPlant(VanillaBlocks::BROWN_MUSHROOM(), self::GRASS_COVER);
		$mushrooms->setBaseAmount(1);
		$mushrooms->setRandomAmount(2);
		$mushrooms->setChance(4);

		return $mushrooms;
	}

	private static function waterLake() : Lake{
		return new Lake(VanillaBlocks::WATER(), 4);
	}
}
