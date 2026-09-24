<?php

declare(strict_types=1);

namespace dimension\generator\structure;

use dimension\generator\ChunkHash;
use dimension\generator\math\GenerationMath;
use dimension\generator\object\BlockManager;
use dimension\generator\Populator;
use dimension\generator\random\RandomSource;
use dimension\generator\random\Xoroshiro128;
use dimension\generator\structure\placement\StructurePlacement;
use dimension\generator\structure\template\StateBlocks;
use dimension\generator\structure\template\StructureTemplates;
use pocketmine\block\Block;
use pocketmine\block\Water;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\world\ChunkManager;
use function in_array;

/**
 * Ruined portals, one candidate chunk per region of 40 by 40 chunks. The
 * biome at the chunk centre decides how the portal is set in the terrain:
 * buried in deserts, on the surface in jungles and swamps, on the sea floor
 * in oceans, in or on mountains, underground or on the surface elsewhere,
 * and at a random height in the nether, where the stone bricks become
 * blackstone. One portal in twenty-one is a giant portal. Obsidian turns
 * crying one time in five.
 */
final class RuinedPortalPopulator implements Populator{

	private const SEA_LEVEL = 63;

	private const ON_LAND_SURFACE = 0;
	private const PARTLY_BURIED = 1;
	private const ON_OCEAN_FLOOR = 2;
	private const IN_MOUNTAIN = 3;
	private const UNDERGROUND = 4;
	private const IN_NETHER = 5;

	private const PORTALS = [
		"ruined_portal/portal_1",
		"ruined_portal/portal_2",
		"ruined_portal/portal_3",
		"ruined_portal/portal_4",
		"ruined_portal/portal_5",
		"ruined_portal/portal_6",
		"ruined_portal/portal_7",
		"ruined_portal/portal_8",
		"ruined_portal/portal_9",
		"ruined_portal/portal_10"
	];
	private const GIANT_PORTALS = [
		"ruined_portal/giant_portal_1",
		"ruined_portal/giant_portal_2",
		"ruined_portal/giant_portal_3"
	];

	private const NETHER_BRICK_REPLACEMENTS = [
		"minecraft:chiseled_stone_bricks" => "minecraft:chiseled_polished_blackstone",
		"minecraft:cracked_stone_bricks" => "minecraft:cracked_polished_blackstone_bricks",
		"minecraft:stone_bricks" => "minecraft:polished_blackstone_bricks",
		"minecraft:stone_brick_slab" => "minecraft:polished_blackstone_brick_slab",
		"minecraft:stone_brick_stairs" => "minecraft:polished_blackstone_brick_stairs",
		"minecraft:stone_brick_wall" => "minecraft:polished_blackstone_wall"
	];

	private const DESERT = [BiomeIds::DESERT, BiomeIds::DESERT_HILLS, BiomeIds::DESERT_MUTATED];
	private const JUNGLE_OR_SWAMP = [
		BiomeIds::JUNGLE, BiomeIds::JUNGLE_HILLS, BiomeIds::JUNGLE_EDGE, BiomeIds::JUNGLE_MUTATED, BiomeIds::JUNGLE_EDGE_MUTATED,
		BiomeIds::BAMBOO_JUNGLE, BiomeIds::BAMBOO_JUNGLE_HILLS,
		BiomeIds::SWAMPLAND, BiomeIds::SWAMPLAND_MUTATED, BiomeIds::MANGROVE_SWAMP
	];
	private const MOUNTAINS = [
		BiomeIds::EXTREME_HILLS, BiomeIds::EXTREME_HILLS_EDGE, BiomeIds::EXTREME_HILLS_MUTATED,
		BiomeIds::EXTREME_HILLS_PLUS_TREES, BiomeIds::EXTREME_HILLS_PLUS_TREES_MUTATED,
		BiomeIds::ICE_MOUNTAINS, BiomeIds::JAGGED_PEAKS, BiomeIds::FROZEN_PEAKS, BiomeIds::STONY_PEAKS,
		BiomeIds::SNOWY_SLOPES, BiomeIds::GROVE, BiomeIds::MEADOW, BiomeIds::CHERRY_GROVE
	];
	private const OCEAN = [
		BiomeIds::OCEAN, BiomeIds::DEEP_OCEAN, BiomeIds::LEGACY_FROZEN_OCEAN, BiomeIds::WARM_OCEAN, BiomeIds::DEEP_WARM_OCEAN,
		BiomeIds::LUKEWARM_OCEAN, BiomeIds::DEEP_LUKEWARM_OCEAN, BiomeIds::COLD_OCEAN, BiomeIds::DEEP_COLD_OCEAN,
		BiomeIds::FROZEN_OCEAN, BiomeIds::DEEP_FROZEN_OCEAN
	];
	private const NETHER = [
		BiomeIds::HELL, BiomeIds::SOULSAND_VALLEY, BiomeIds::CRIMSON_FOREST, BiomeIds::WARPED_FOREST, BiomeIds::BASALT_DELTAS
	];

	private StructurePlacement $placement;
	private Xoroshiro128 $random;

	public function __construct(
		private int $seed,
		private string $resourceFolder
	){
		$this->random = new Xoroshiro128(0);
		$this->placement = new StructurePlacement(34222645, 15, 40);
	}

	public function populate(BlockManager $root, ChunkManager $world, int $chunkX, int $chunkZ, int $seed) : void{
		$chunk = $world->getChunk($chunkX, $chunkZ);
		if($chunk === null){
			return;
		}
		$biome = $chunk->getBiomeId(7, self::SEA_LEVEL, 7);
		if(!$this->placement->canGenerate($seed, $this->random, $chunkX, $chunkZ, $biome)){
			return;
		}
		$random = $this->random;
		$random->setSeed($seed ^ ChunkHash::hash($chunkX, $chunkZ));
		$x = ($chunkX << 4) + 7;
		$z = ($chunkZ << 4) + 7;

		$isNether = in_array($biome, self::NETHER, true);
		if(in_array($biome, self::DESERT, true)){
			$height = self::PARTLY_BURIED;
		}elseif(in_array($biome, self::JUNGLE_OR_SWAMP, true)){
			$height = self::ON_LAND_SURFACE;
		}elseif(in_array($biome, self::MOUNTAINS, true)){
			$height = $random->nextBoolean() ? self::ON_LAND_SURFACE : self::IN_MOUNTAIN;
		}elseif(in_array($biome, self::OCEAN, true)){
			$height = self::ON_OCEAN_FLOOR;
		}elseif($isNether){
			$height = self::IN_NETHER;
		}else{
			$height = $random->nextBoolean() ? self::ON_LAND_SURFACE : self::UNDERGROUND;
		}

		$big = $random->nextBoundedInt(20) === 0;
		$name = $big ? self::GIANT_PORTALS[$random->nextInt(3)] : self::PORTALS[$random->nextInt(10)];
		$airPocket = $height === self::IN_NETHER && $random->nextFloat() < 0.5;
		$structure = StructureTemplates::get($this->resourceFolder, $name);
		if($structure === null){
			return;
		}
		$y = $this->findSuitableY($random, $world, $x, $z, $height, $airPocket, $structure->getSizeX(), $structure->getSizeY(), $structure->getSizeZ());

		$manager = new BlockManager($world);
		$water = StateBlocks::state("minecraft:water");
		$netherrack = StateBlocks::state("minecraft:netherrack");
		$obsidian = StateBlocks::state("minecraft:obsidian");
		$cryingObsidian = StateBlocks::state("minecraft:crying_obsidian");

		$volume = $structure->getVolume();
		for($index = 0; $index < $volume; ++$index){
			$state = $structure->stateAt($index);
			if($state === null){
				continue;
			}
			$blockX = $x + $structure->xOf($index);
			$blockY = $y + $structure->yOf($index);
			$blockZ = $z + $structure->zOf($index);
			$id = $state->getName();
			if($id === "minecraft:air" && $water !== null && $this->shouldFillAirWithWater($world, $blockX, $blockY, $blockZ)){
				$this->set($manager, $blockX, $blockY, $blockZ, $water);
				continue;
			}
			$result = $state;
			if($id === "minecraft:jigsaw" && $netherrack !== null){
				$result = $netherrack;
			}
			if(($id === "minecraft:lava" || $id === "minecraft:flowing_lava") && $obsidian !== null && $this->isWater($world->getBlockAt($blockX, $blockY + 1, $blockZ))){
				$result = $obsidian;
			}
			if($id === "minecraft:obsidian" && $random->nextInt(5) === 0 && $cryingObsidian !== null){
				$result = $cryingObsidian;
			}
			if($isNether){
				if($id === "minecraft:mossy_stone_bricks"){
					continue;
				}
				$replacement = self::NETHER_BRICK_REPLACEMENTS[$id] ?? null;
				if($replacement !== null){
					$result = $this->replaceKeepingStates($state, $replacement) ?? $result;
				}
			}
			$this->set($manager, $blockX, $blockY, $blockZ, $result);
		}
		$root->merge($manager);
	}

	private function set(BlockManager $manager, int $x, int $y, int $z, BlockStateData $state) : void{
		$block = StateBlocks::toBlock($state);
		if($block !== null){
			$manager->setBlockStateAt($x, $y, $z, $block);
		}
	}

	/**
	 * State of $identifier carrying over the values of the states both
	 * blocks share.
	 */
	private function replaceKeepingStates(BlockStateData $state, string $identifier) : ?BlockStateData{
		$default = StateBlocks::state($identifier);
		if($default === null){
			return null;
		}
		$overrides = [];
		foreach($default->getStates() as $key => $tag){
			$value = $state->getState($key);
			if($value !== null && $value->getType() === $tag->getType()){
				$overrides[$key] = $value;
			}
		}
		return StateBlocks::state($identifier, $overrides);
	}

	private function isWater(Block $block) : bool{
		return $block instanceof Water;
	}

	private function shouldFillAirWithWater(ChunkManager $world, int $x, int $y, int $z) : bool{
		if($y >= self::SEA_LEVEL){
			return false;
		}
		if($this->isWater($world->getBlockAt($x, $y, $z))){
			return true;
		}
		foreach([[0, -1, 0], [0, 1, 0], [0, 0, -1], [0, 0, 1], [-1, 0, 0], [1, 0, 0]] as [$dx, $dy, $dz]){
			if($this->isWater($world->getBlockAt($x + $dx, $y + $dy, $z + $dz))){
				return true;
			}
		}
		return false;
	}

	private function findSuitableY(RandomSource $random, ChunkManager $world, int $worldX, int $worldZ, int $portalHeight, bool $airPocket, int $structureWidth, int $structureHeight, int $structureDepth) : int{
		$minY = $world->getMinY() + 15;
		$surfaceYAtCenter = $this->getSurfaceY($world, $worldX + ($structureWidth >> 1), $worldZ + ($structureDepth >> 1), $portalHeight);
		if($portalHeight === self::IN_NETHER){
			if($airPocket){
				$i = GenerationMath::randomRange($random, 32, 100);
			}elseif($random->nextBoolean()){
				$i = GenerationMath::randomRange($random, 27, 29);
			}else{
				$i = GenerationMath::randomRange($random, 29, 100);
			}
		}elseif($portalHeight === self::IN_MOUNTAIN){
			$i = self::getRandomWithinInterval($random, 70, $surfaceYAtCenter - $structureHeight);
		}elseif($portalHeight === self::UNDERGROUND){
			$i = self::getRandomWithinInterval($random, $minY, $surfaceYAtCenter - $structureHeight);
		}elseif($portalHeight === self::PARTLY_BURIED){
			$i = $surfaceYAtCenter - $structureHeight + GenerationMath::randomRange($random, 2, 8);
		}else{
			$i = $surfaceYAtCenter;
		}

		$maxCornerX = $worldX + $structureWidth - 1;
		$maxCornerZ = $worldZ + $structureDepth - 1;
		for($y = $i; $y > $minY; --$y){
			$cornersOnSolidGround = 0;
			if(self::isOpaqueGround($world->getBlockAt($worldX, $y, $worldZ))){
				++$cornersOnSolidGround;
			}
			if(self::isOpaqueGround($world->getBlockAt($maxCornerX, $y, $worldZ))){
				++$cornersOnSolidGround;
			}
			if(self::isOpaqueGround($world->getBlockAt($worldX, $y, $maxCornerZ))){
				++$cornersOnSolidGround;
			}
			if(self::isOpaqueGround($world->getBlockAt($maxCornerX, $y, $maxCornerZ))){
				++$cornersOnSolidGround;
			}
			if($cornersOnSolidGround >= 3){
				return $y;
			}
		}
		return $y;
	}

	/**
	 * First free Y above the highest block of the column, lowered through
	 * water for portals on the sea floor.
	 */
	private function getSurfaceY(ChunkManager $world, int $x, int $z, int $portalHeight) : int{
		$minY = $world->getMinY();
		$chunk = $world->getChunk($x >> 4, $z >> 4);
		$highest = $chunk?->getHighestBlockAt($x & 0x0f, $z & 0x0f);
		$y = $highest === null ? $minY : $highest + 1;
		if($portalHeight !== self::ON_OCEAN_FLOOR){
			return $y;
		}
		while($y > $minY && $this->isWater($world->getBlockAt($x, $y, $z))){
			--$y;
		}
		return $y;
	}

	private static function isOpaqueGround(Block $block) : bool{
		return $block->isSolid() && !$block->canBeReplaced() && !$block->isTransparent();
	}

	private static function getRandomWithinInterval(RandomSource $random, int $start, int $end) : int{
		return $start < $end ? GenerationMath::randomRange($random, $start, $end) : $end;
	}
}
