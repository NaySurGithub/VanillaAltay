<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure;

use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use VanillaAltay\world\generator\structure\jigsaw\JigsawAssembler;
use VanillaAltay\world\generator\structure\jigsaw\JigsawPiece;
use VanillaAltay\world\generator\structure\mineshaft\BoundingBox;
use function array_key_first;
use function count;

/**
 * The city is assembled from templates that name each other, so its shape is only known once it is built. It is
 * far wider than a chunk, which is why every chunk it reaches rebuilds the same layout and writes its own part.
 */
final class AncientCity implements SpreadStructure{

	private const SALT = 0x616E6369656E744C;

	private const GENERATION_Y = -51;

	private const MAX_DEPTH = 7;

	private const MAX_PIECES = 128;

	/**
	 * Measured over sixty layouts: a city reaches at most a hundred and sixty blocks from its origin.
	 */
	private const CHUNK_REACH = 11;

	private const LAYOUT_CACHE_SIZE = 4;

	private const ENTRY_POOL = "ancient_city/city_center";

	/**
	 * @var JigsawPiece[][]
	 * @phpstan-var array<int, list<JigsawPiece>>
	 */
	private array $layouts = [];

	/**
	 * @phpstan-return array<string, list<array{string, int}>>
	 */
	private static function getPools() : array{
		return [
			self::ENTRY_POOL => [
				["ancient_city/city_center/city_center_1", 1],
				["ancient_city/city_center/city_center_2", 1],
				["ancient_city/city_center/city_center_3", 1]
			],
			"ancient_city/city/entrance" => [
				["ancient_city/city/entrance/entrance_connector", 1],
				["ancient_city/city/entrance/entrance_path_1", 1],
				["ancient_city/city/entrance/entrance_path_2", 1],
				["ancient_city/city/entrance/entrance_path_3", 1],
				["ancient_city/city/entrance/entrance_path_4", 1],
				["ancient_city/city/entrance/entrance_path_5", 1]
			],
			"ancient_city/structures" => [
				["empty", 7],
				["ancient_city/structures/barracks", 4],
				["ancient_city/structures/chamber_1", 4],
				["ancient_city/structures/chamber_2", 4],
				["ancient_city/structures/chamber_3", 4],
				["ancient_city/structures/sauna_1", 4],
				["ancient_city/structures/small_statue", 4],
				["ancient_city/structures/large_ruin_1", 1],
				["ancient_city/structures/tall_ruin_1", 1],
				["ancient_city/structures/tall_ruin_2", 1],
				["ancient_city/structures/tall_ruin_3", 2],
				["ancient_city/structures/tall_ruin_4", 2],
				["ancient_city/structures/camp_1", 1],
				["ancient_city/structures/camp_2", 1],
				["ancient_city/structures/camp_3", 1],
				["ancient_city/structures/medium_ruin_1", 1],
				["ancient_city/structures/medium_ruin_2", 1],
				["ancient_city/structures/small_ruin_1", 1],
				["ancient_city/structures/small_ruin_2", 1],
				["ancient_city/structures/large_pillar_1", 1],
				["ancient_city/structures/medium_pillar_1", 1],
				["ancient_city/structures/ice_box_1", 1]
			],
			"ancient_city/sculk" => [
				["empty", 7]
			],
			"ancient_city/walls" => [
				["ancient_city/walls/intact_corner_wall_1", 1],
				["ancient_city/walls/intact_intersection_wall_1", 1],
				["ancient_city/walls/intact_lshape_wall_1", 1],
				["ancient_city/walls/intact_horizontal_wall_1", 1],
				["ancient_city/walls/intact_horizontal_wall_2", 1],
				["ancient_city/walls/intact_horizontal_wall_stairs_1", 1],
				["ancient_city/walls/intact_horizontal_wall_stairs_2", 1],
				["ancient_city/walls/intact_horizontal_wall_stairs_3", 1],
				["ancient_city/walls/intact_horizontal_wall_stairs_4", 4],
				["ancient_city/walls/intact_horizontal_wall_passage_1", 3],
				["ancient_city/walls/ruined_corner_wall_1", 1],
				["ancient_city/walls/ruined_corner_wall_2", 1],
				["ancient_city/walls/ruined_horizontal_wall_stairs_1", 2],
				["ancient_city/walls/ruined_horizontal_wall_stairs_2", 2],
				["ancient_city/walls/ruined_horizontal_wall_stairs_3", 3],
				["ancient_city/walls/ruined_horizontal_wall_stairs_4", 3]
			],
			"ancient_city/walls/no_corners" => [
				["ancient_city/walls/intact_horizontal_wall_1", 1],
				["ancient_city/walls/intact_horizontal_wall_2", 1],
				["ancient_city/walls/intact_horizontal_wall_stairs_1", 1],
				["ancient_city/walls/intact_horizontal_wall_stairs_2", 1],
				["ancient_city/walls/intact_horizontal_wall_stairs_3", 1],
				["ancient_city/walls/intact_horizontal_wall_stairs_4", 1],
				["ancient_city/walls/intact_horizontal_wall_stairs_5", 1],
				["ancient_city/walls/intact_horizontal_wall_bridge", 1]
			],
			"ancient_city/city_center/walls" => [
				["ancient_city/city_center/walls/bottom_1", 1],
				["ancient_city/city_center/walls/bottom_2", 1],
				["ancient_city/city_center/walls/bottom_left_corner", 1],
				["ancient_city/city_center/walls/bottom_right_corner_1", 1],
				["ancient_city/city_center/walls/bottom_right_corner_2", 1],
				["ancient_city/city_center/walls/left", 1],
				["ancient_city/city_center/walls/right", 1],
				["ancient_city/city_center/walls/top", 1],
				["ancient_city/city_center/walls/top_left_corner", 1],
				["ancient_city/city_center/walls/top_right_corner", 1]
			]
		];
	}

	public function getName() : string{
		return "ancient_city";
	}

	/**
	 * Altay knows no deep dark biome, so the only rule left is the spacing one.
	 */
	public function getPlacement() : StructurePlacement{
		return new StructurePlacement(self::SALT, 8, 24, fn(int $biomeId) => true);
	}

	public function getChunkReach() : int{
		return self::CHUNK_REACH;
	}

	public function place(ChunkManager $world, Random $random, int $x, int $y, int $z) : void{
		$this->placeAround($world, $random, $x >> 4, $z >> 4, $x >> 4, $z >> 4);
	}

	public function placeAround(ChunkManager $world, Random $random, int $originChunkX, int $originChunkZ, int $targetChunkX, int $targetChunkZ) : void{
		$pieces = $this->getLayout($random, $random->getSeed(), $originChunkX, $originChunkZ);

		$clip = new BoundingBox(
			($targetChunkX - 1) << 4, $world->getMinY(), ($targetChunkZ - 1) << 4,
			(($targetChunkX + 2) << 4) - 1, $world->getMaxY() - 1, (($targetChunkZ + 2) << 4) - 1
		);

		foreach($pieces as $piece){
			if(!$piece->boundingBox->intersects($clip)){
				continue;
			}

			$piece->template->place($world, $piece->x, $piece->y, $piece->z, $piece->rotation);
		}
	}

	/**
	 * Assembling costs far more than writing, and every chunk the city crosses asks for the same layout, so the
	 * last few are kept.
	 *
	 * @return JigsawPiece[]
	 */
	private function getLayout(Random $random, int $layoutSeed, int $chunkX, int $chunkZ) : array{
		if(isset($this->layouts[$layoutSeed])){
			return $this->layouts[$layoutSeed];
		}

		$pieces = JigsawAssembler::assemble(self::getPools(), self::ENTRY_POOL, self::MAX_DEPTH, self::MAX_PIECES, $random);

		$originX = $chunkX << 4;
		$originZ = $chunkZ << 4;

		foreach($pieces as $piece){
			$piece->x += $originX;
			$piece->y += self::GENERATION_Y;
			$piece->z += $originZ;
			$piece->boundingBox->move($originX, self::GENERATION_Y, $originZ);
		}

		//the keys are seeds, so the oldest one is dropped by name rather than shifted, which would renumber them
		if(count($this->layouts) >= self::LAYOUT_CACHE_SIZE){
			unset($this->layouts[array_key_first($this->layouts)]);
		}

		return $this->layouts[$layoutSeed] = $pieces;
	}
}
