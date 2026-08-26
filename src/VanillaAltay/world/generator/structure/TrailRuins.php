<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure;

use pocketmine\data\bedrock\BiomeIds;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use VanillaAltay\world\generator\structure\jigsaw\JigsawAssembler;
use VanillaAltay\world\generator\structure\mineshaft\BoundingBox;

use function in_array;
use function max;

/**
 * A buried village assembled from templates that name each other: a tower, the roads leaving it and the rooms
 * hanging off both.
 */
final class TrailRuins implements Structure
{
	private const SALT = 83469867;

	private const MAX_DEPTH = 16;

	private const MAX_PIECES = 64;

	/**
	 * The ruins sit fifteen blocks under the surface, which is what leaves only their roofs poking out.
	 */
	private const DEPTH_BELOW_SURFACE = 15;

	/**
	 * A piece is only written where a chunk is loaded, so the assembly is kept inside the chunk that starts it
	 * and its neighbours rather than being cut short at their border.
	 */
	private const BLOCK_REACH = 16;

	private const ENTRY_POOL = "trail_ruins/tower";

	private const BIOMES = [
		BiomeIds::TAIGA,
		BiomeIds::TAIGA_HILLS,
		BiomeIds::TAIGA_MUTATED,
		BiomeIds::COLD_TAIGA,
		BiomeIds::COLD_TAIGA_HILLS,
		BiomeIds::COLD_TAIGA_MUTATED,
		BiomeIds::MEGA_TAIGA,
		BiomeIds::MEGA_TAIGA_HILLS,
		BiomeIds::REDWOOD_TAIGA_MUTATED,
		BiomeIds::REDWOOD_TAIGA_HILLS_MUTATED,
		BiomeIds::JUNGLE,
	];

	/**
	 * @phpstan-return array<string, list<array{string, int}>>
	 */
	private static function getPools() : array
	{
		return [
			self::ENTRY_POOL => [
				["trail_ruins/tower/tower_1", 1],
				["trail_ruins/tower/tower_2", 1],
				["trail_ruins/tower/tower_3", 1],
				["trail_ruins/tower/tower_4", 1],
				["trail_ruins/tower/tower_5", 1],
			],
			"trail_ruins/tower/tower_top" => [
				["trail_ruins/tower/tower_top_1", 1],
				["trail_ruins/tower/tower_top_2", 1],
				["trail_ruins/tower/tower_top_3", 1],
				["trail_ruins/tower/tower_top_4", 1],
				["trail_ruins/tower/tower_top_5", 1],
			],
			"trail_ruins/tower/additions" => [
				["trail_ruins/tower/hall_1", 1],
				["trail_ruins/tower/hall_2", 1],
				["trail_ruins/tower/hall_3", 1],
				["trail_ruins/tower/hall_4", 1],
				["trail_ruins/tower/hall_5", 1],
				["trail_ruins/tower/large_hall_1", 1],
				["trail_ruins/tower/large_hall_2", 1],
				["trail_ruins/tower/large_hall_3", 1],
				["trail_ruins/tower/large_hall_4", 1],
				["trail_ruins/tower/large_hall_5", 1],
				["trail_ruins/tower/one_room_1", 1],
				["trail_ruins/tower/one_room_2", 1],
				["trail_ruins/tower/one_room_3", 1],
				["trail_ruins/tower/one_room_4", 1],
				["trail_ruins/tower/one_room_5", 1],
				["trail_ruins/tower/platform_1", 1],
				["trail_ruins/tower/platform_2", 1],
				["trail_ruins/tower/platform_3", 1],
				["trail_ruins/tower/platform_4", 1],
				["trail_ruins/tower/platform_5", 1],
				["trail_ruins/tower/stable_1", 1],
				["trail_ruins/tower/stable_2", 1],
				["trail_ruins/tower/stable_3", 1],
				["trail_ruins/tower/stable_4", 1],
				["trail_ruins/tower/stable_5", 1],
			],
			"trail_ruins/roads" => [
				["trail_ruins/roads/long_road_end", 1],
				["trail_ruins/roads/road_end_1", 1],
				["trail_ruins/roads/road_section_1", 1],
				["trail_ruins/roads/road_section_2", 1],
				["trail_ruins/roads/road_section_3", 1],
				["trail_ruins/roads/road_section_4", 1],
				["trail_ruins/roads/road_spacer_1", 1],
			],
			"trail_ruins/buildings" => [
				["trail_ruins/buildings/group_hall_1", 1],
				["trail_ruins/buildings/group_hall_2", 1],
				["trail_ruins/buildings/group_hall_3", 1],
				["trail_ruins/buildings/group_hall_4", 1],
				["trail_ruins/buildings/group_hall_5", 1],
				["trail_ruins/buildings/large_room_1", 1],
				["trail_ruins/buildings/large_room_2", 1],
				["trail_ruins/buildings/large_room_3", 1],
				["trail_ruins/buildings/large_room_4", 1],
				["trail_ruins/buildings/large_room_5", 1],
				["trail_ruins/buildings/one_room_1", 1],
				["trail_ruins/buildings/one_room_2", 1],
				["trail_ruins/buildings/one_room_3", 1],
				["trail_ruins/buildings/one_room_4", 1],
				["trail_ruins/buildings/one_room_5", 1],
			],
			"trail_ruins/buildings/grouped" => [
				["trail_ruins/buildings/group_full_1", 1],
				["trail_ruins/buildings/group_full_2", 1],
				["trail_ruins/buildings/group_full_3", 1],
				["trail_ruins/buildings/group_full_4", 1],
				["trail_ruins/buildings/group_full_5", 1],
				["trail_ruins/buildings/group_lower_1", 1],
				["trail_ruins/buildings/group_lower_2", 1],
				["trail_ruins/buildings/group_lower_3", 1],
				["trail_ruins/buildings/group_lower_4", 1],
				["trail_ruins/buildings/group_lower_5", 1],
				["trail_ruins/buildings/group_upper_1", 1],
				["trail_ruins/buildings/group_upper_2", 1],
				["trail_ruins/buildings/group_upper_3", 1],
				["trail_ruins/buildings/group_upper_4", 1],
				["trail_ruins/buildings/group_upper_5", 1],
				["trail_ruins/buildings/group_room_1", 1],
				["trail_ruins/buildings/group_room_2", 1],
				["trail_ruins/buildings/group_room_3", 1],
				["trail_ruins/buildings/group_room_4", 1],
				["trail_ruins/buildings/group_room_5", 1],
			],
			"trail_ruins/decor" => [
				["trail_ruins/decor/decor_1", 1],
				["trail_ruins/decor/decor_2", 1],
				["trail_ruins/decor/decor_3", 1],
				["trail_ruins/decor/decor_4", 1],
				["trail_ruins/decor/decor_5", 1],
				["trail_ruins/decor/decor_6", 1],
				["trail_ruins/decor/decor_7", 1],
			],
		];
	}

	public function getName() : string
	{
		return "trail_ruins";
	}

	public function getPlacement() : StructurePlacement
	{
		return new StructurePlacement(self::SALT, 8, 34, fn(int $biomeId) => in_array($biomeId, self::BIOMES, true));
	}

	public function place(ChunkManager $world, Random $random, int $x, int $y, int $z) : void
	{
		$originY = max($world->getMinY() + 1, $y - self::DEPTH_BELOW_SURFACE);

		$clip = new BoundingBox(
			-self::BLOCK_REACH,
			$world->getMinY() - $originY,
			-self::BLOCK_REACH,
			self::BLOCK_REACH,
			$world->getMaxY() - 1 - $originY,
			self::BLOCK_REACH,
		);

		foreach (JigsawAssembler::assemble(self::getPools(), self::ENTRY_POOL, self::MAX_DEPTH, self::MAX_PIECES, $random, $clip) as $piece) {
			$piece->template->place($world, $x + $piece->x, $originY + $piece->y, $z + $piece->z, $piece->rotation);
		}
	}
}
