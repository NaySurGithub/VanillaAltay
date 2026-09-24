<?php

declare(strict_types=1);

namespace dimension\generator\structure\jigsaw;

use dimension\generator\structure\BoundingBox;

/**
 * Template pools of the bastion remnant: four layouts (units, hoglin
 * stable, treasure room, bridge) drawn from the start pool, assembled six
 * pieces deep. Pieces may overlap one another.
 */
final class BastionLayout extends JigsawAssembler{

	private const START = "bastion/starts";

	/** @var array<string, StructurePool>|null */
	private static ?array $pools = null;

	protected function getEntryPool() : string{
		return self::START;
	}

	protected function getMaxDepth() : int{
		return 6;
	}

	protected function strictlyIntersects(BoundingBox $first, BoundingBox $second) : bool{
		return false;
	}

	protected function getPools() : array{
		return self::$pools ??= self::buildPools();
	}

	/**
	 * @param array<string, int> $entries template name => weight
	 */
	private static function pool(string $name, array $entries) : StructurePool{
		$list = [];
		foreach($entries as $structureName => $weight){
			$list[] = new PoolEntry($structureName, $weight);
		}
		return new StructurePool($name, $list);
	}

	/**
	 * @param list<array{string, int}> $entries
	 */
	private static function poolList(string $name, array $entries) : StructurePool{
		$list = [];
		foreach($entries as [$structureName, $weight]){
			$list[] = new PoolEntry($structureName, $weight);
		}
		return new StructurePool($name, $list);
	}

	/**
	 * @return array<string, StructurePool>
	 */
	private static function buildPools() : array{
		$definitions = [
			self::START => [
				"bastion/units/air_base" => 1,
				"bastion/hoglin_stable/air_base" => 1,
				"bastion/treasure/big_air_full" => 1,
				"bastion/bridge/starting_pieces/entrance_base" => 1
			],
			"bastion/units/center_pieces" => [
				"bastion/units/center_pieces/center_0" => 1,
				"bastion/units/center_pieces/center_1" => 1,
				"bastion/units/center_pieces/center_2" => 1
			],
			"bastion/units/pathways" => [
				"bastion/units/pathways/pathway_0" => 1,
				"bastion/units/pathways/pathway_wall_0" => 1
			],
			"bastion/units/walls/wall_bases" => [
				"bastion/units/walls/wall_base" => 1,
				"bastion/units/walls/connected_wall" => 1
			],
			"bastion/units/stages/stage_0" => [
				"bastion/units/stages/stage_0_0" => 1,
				"bastion/units/stages/stage_0_1" => 1,
				"bastion/units/stages/stage_0_2" => 1,
				"bastion/units/stages/stage_0_3" => 1
			],
			"bastion/units/stages/stage_1" => [
				"bastion/units/stages/stage_1_0" => 1,
				"bastion/units/stages/stage_1_1" => 1,
				"bastion/units/stages/stage_1_2" => 1,
				"bastion/units/stages/stage_1_3" => 1
			],
			"bastion/units/stages/rot/stage_1" => [
				"bastion/units/stages/rot/stage_1_0" => 1
			],
			"bastion/units/stages/stage_2" => [
				"bastion/units/stages/stage_2_0" => 1,
				"bastion/units/stages/stage_2_1" => 1
			],
			"bastion/units/stages/stage_3" => [
				"bastion/units/stages/stage_3_0" => 1,
				"bastion/units/stages/stage_3_1" => 1,
				"bastion/units/stages/stage_3_2" => 1,
				"bastion/units/stages/stage_3_3" => 1
			],
			"bastion/units/fillers/stage_0" => [
				"bastion/units/fillers/stage_0" => 1
			],
			"bastion/units/edges" => [
				"bastion/units/edges/edge_0" => 1
			],
			"bastion/units/wall_units" => [
				"bastion/units/wall_units/unit_0" => 1
			],
			"bastion/units/edge_wall_units" => [
				"bastion/units/wall_units/edge_0_large" => 1
			],
			"bastion/units/ramparts" => [
				"bastion/units/ramparts/ramparts_0" => 1,
				"bastion/units/ramparts/ramparts_1" => 1,
				"bastion/units/ramparts/ramparts_2" => 1
			],
			"bastion/units/large_ramparts" => [
				"bastion/units/ramparts/ramparts_0" => 1
			],
			"bastion/units/rampart_plates" => [
				"bastion/units/rampart_plates/plate_0" => 1
			],
			"bastion/hoglin_stable/starting_pieces" => [
				"bastion/hoglin_stable/starting_pieces/starting_stairs_0" => 1,
				"bastion/hoglin_stable/starting_pieces/starting_stairs_1" => 1,
				"bastion/hoglin_stable/starting_pieces/starting_stairs_2" => 1,
				"bastion/hoglin_stable/starting_pieces/starting_stairs_3" => 1,
				"bastion/hoglin_stable/starting_pieces/starting_stairs_4" => 1
			],
			"bastion/hoglin_stable/mirrored_starting_pieces" => [
				"bastion/hoglin_stable/starting_pieces/stairs_0_mirrored" => 1,
				"bastion/hoglin_stable/starting_pieces/stairs_1_mirrored" => 1,
				"bastion/hoglin_stable/starting_pieces/stairs_2_mirrored" => 1,
				"bastion/hoglin_stable/starting_pieces/stairs_3_mirrored" => 1,
				"bastion/hoglin_stable/starting_pieces/stairs_4_mirrored" => 1
			],
			"bastion/hoglin_stable/wall_bases" => [
				"bastion/hoglin_stable/walls/wall_base" => 1
			],
			"bastion/hoglin_stable/walls" => [
				"bastion/hoglin_stable/walls/side_wall_0" => 1,
				"bastion/hoglin_stable/walls/side_wall_1" => 1
			],
			"bastion/hoglin_stable/stairs" => [
				"bastion/hoglin_stable/stairs/stairs_1_0" => 1,
				"bastion/hoglin_stable/stairs/stairs_1_1" => 1,
				"bastion/hoglin_stable/stairs/stairs_1_2" => 1,
				"bastion/hoglin_stable/stairs/stairs_1_3" => 1,
				"bastion/hoglin_stable/stairs/stairs_1_4" => 1,
				"bastion/hoglin_stable/stairs/stairs_2_0" => 1,
				"bastion/hoglin_stable/stairs/stairs_2_1" => 1,
				"bastion/hoglin_stable/stairs/stairs_2_2" => 1,
				"bastion/hoglin_stable/stairs/stairs_2_3" => 1,
				"bastion/hoglin_stable/stairs/stairs_2_4" => 1,
				"bastion/hoglin_stable/stairs/stairs_3_0" => 1,
				"bastion/hoglin_stable/stairs/stairs_3_1" => 1,
				"bastion/hoglin_stable/stairs/stairs_3_2" => 1,
				"bastion/hoglin_stable/stairs/stairs_3_3" => 1,
				"bastion/hoglin_stable/stairs/stairs_3_4" => 1
			],
			"bastion/hoglin_stable/small_stables/inner" => [
				"bastion/hoglin_stable/small_stables/inner_0" => 1,
				"bastion/hoglin_stable/small_stables/inner_1" => 1,
				"bastion/hoglin_stable/small_stables/inner_2" => 1,
				"bastion/hoglin_stable/small_stables/inner_3" => 1
			],
			"bastion/hoglin_stable/small_stables/outer" => [
				"bastion/hoglin_stable/small_stables/outer_0" => 1,
				"bastion/hoglin_stable/small_stables/outer_1" => 1,
				"bastion/hoglin_stable/small_stables/outer_2" => 1,
				"bastion/hoglin_stable/small_stables/outer_3" => 1
			],
			"bastion/hoglin_stable/large_stables/inner" => [
				"bastion/hoglin_stable/large_stables/inner_0" => 1,
				"bastion/hoglin_stable/large_stables/inner_1" => 1,
				"bastion/hoglin_stable/large_stables/inner_2" => 1,
				"bastion/hoglin_stable/large_stables/inner_3" => 1,
				"bastion/hoglin_stable/large_stables/inner_4" => 1
			],
			"bastion/hoglin_stable/large_stables/outer" => [
				"bastion/hoglin_stable/large_stables/outer_0" => 1,
				"bastion/hoglin_stable/large_stables/outer_1" => 1,
				"bastion/hoglin_stable/large_stables/outer_2" => 1,
				"bastion/hoglin_stable/large_stables/outer_3" => 1,
				"bastion/hoglin_stable/large_stables/outer_4" => 1
			],
			"bastion/hoglin_stable/posts" => [
				"bastion/hoglin_stable/posts/stair_post" => 1,
				"bastion/hoglin_stable/posts/end_post" => 1
			],
			"bastion/hoglin_stable/ramparts" => [
				"bastion/hoglin_stable/ramparts/ramparts_1" => 1,
				"bastion/hoglin_stable/ramparts/ramparts_2" => 1,
				"bastion/hoglin_stable/ramparts/ramparts_3" => 1
			],
			"bastion/hoglin_stable/rampart_plates" => [
				"bastion/hoglin_stable/rampart_plates/rampart_plate_1" => 1
			],
			"bastion/hoglin_stable/connectors" => [
				"bastion/hoglin_stable/connectors/end_post_connector" => 1
			],
			"bastion/treasure/bases" => [
				"bastion/treasure/bases/lava_basin" => 1
			],
			"bastion/treasure/stairs" => [
				"bastion/treasure/stairs/lower_stairs" => 1
			],
			"bastion/treasure/bases/centers" => [
				"bastion/treasure/bases/centers/center_0" => 1,
				"bastion/treasure/bases/centers/center_1" => 1,
				"bastion/treasure/bases/centers/center_2" => 1,
				"bastion/treasure/bases/centers/center_3" => 1
			],
			"bastion/treasure/brains" => [
				"bastion/treasure/brains/center_brain" => 1
			],
			"bastion/treasure/walls" => [
				"bastion/treasure/walls/lava_wall" => 1,
				"bastion/treasure/walls/entrance_wall" => 1
			],
			"bastion/treasure/walls/outer" => [
				"bastion/treasure/walls/outer/top_corner" => 1,
				"bastion/treasure/walls/outer/mid_corner" => 1,
				"bastion/treasure/walls/outer/bottom_corner" => 1,
				"bastion/treasure/walls/outer/outer_wall" => 1,
				"bastion/treasure/walls/outer/medium_outer_wall" => 1,
				"bastion/treasure/walls/outer/tall_outer_wall" => 1
			],
			"bastion/treasure/walls/bottom" => [
				"bastion/treasure/walls/bottom/wall_0" => 1,
				"bastion/treasure/walls/bottom/wall_1" => 1,
				"bastion/treasure/walls/bottom/wall_2" => 1,
				"bastion/treasure/walls/bottom/wall_3" => 1
			],
			"bastion/treasure/walls/mid" => [
				"bastion/treasure/walls/mid/wall_0" => 1,
				"bastion/treasure/walls/mid/wall_1" => 1,
				"bastion/treasure/walls/mid/wall_2" => 1
			],
			"bastion/treasure/walls/top" => [
				"bastion/treasure/walls/top/main_entrance" => 1,
				"bastion/treasure/walls/top/wall_0" => 1,
				"bastion/treasure/walls/top/wall_1" => 1
			],
			"bastion/treasure/connectors" => [
				"bastion/treasure/connectors/center_to_wall_middle" => 1,
				"bastion/treasure/connectors/center_to_wall_top" => 1,
				"bastion/treasure/connectors/center_to_wall_top_entrance" => 1
			],
			"bastion/treasure/entrances" => [
				"bastion/treasure/entrances/entrance_0" => 1
			],
			"bastion/treasure/ramparts" => [
				"bastion/treasure/ramparts/mid_wall_main" => 1,
				"bastion/treasure/ramparts/mid_wall_side" => 1,
				"bastion/treasure/ramparts/bottom_wall_0" => 1,
				"bastion/treasure/ramparts/top_wall" => 1,
				"bastion/treasure/ramparts/lava_basin_side" => 1,
				"bastion/treasure/ramparts/lava_basin_main" => 1
			],
			"bastion/treasure/corners/bottom" => [
				"bastion/treasure/corners/bottom/corner_0" => 1,
				"bastion/treasure/corners/bottom/corner_1" => 1
			],
			"bastion/treasure/corners/edges" => [
				"bastion/treasure/corners/edges/bottom" => 1,
				"bastion/treasure/corners/edges/middle" => 1,
				"bastion/treasure/corners/edges/top" => 1
			],
			"bastion/treasure/corners/middle" => [
				"bastion/treasure/corners/middle/corner_0" => 1,
				"bastion/treasure/corners/middle/corner_1" => 1
			],
			"bastion/treasure/corners/top" => [
				"bastion/treasure/corners/top/corner_0" => 1,
				"bastion/treasure/corners/top/corner_1" => 1
			],
			"bastion/treasure/extensions/houses" => [
				"bastion/treasure/extensions/house_0" => 1,
				"bastion/treasure/extensions/house_1" => 1
			],
			"bastion/treasure/roofs" => [
				"bastion/treasure/roofs/wall_roof" => 1,
				"bastion/treasure/roofs/corner_roof" => 1,
				"bastion/treasure/roofs/center_roof" => 1
			],
			"bastion/bridge/starting_pieces" => [
				"bastion/bridge/starting_pieces/entrance" => 1,
				"bastion/bridge/starting_pieces/entrance_face" => 1
			],
			"bastion/bridge/bridge_pieces" => [
				"bastion/bridge/bridge_pieces/bridge" => 1
			],
			"bastion/bridge/legs" => [
				"bastion/bridge/legs/leg_0" => 1,
				"bastion/bridge/legs/leg_1" => 1
			],
			"bastion/bridge/walls" => [
				"bastion/bridge/walls/wall_base_0" => 1,
				"bastion/bridge/walls/wall_base_1" => 1
			],
			"bastion/bridge/ramparts" => [
				"bastion/bridge/ramparts/rampart_0" => 1,
				"bastion/bridge/ramparts/rampart_1" => 1
			],
			"bastion/bridge/rampart_plates" => [
				"bastion/bridge/rampart_plates/plate_0" => 1
			],
			"bastion/bridge/connectors" => [
				"bastion/bridge/connectors/back_bridge_top" => 1,
				"bastion/bridge/connectors/back_bridge_bottom" => 1
			],
			"bastion/mobs/piglin" => [
				"bastion/mobs/melee_piglin" => 1,
				"bastion/mobs/sword_piglin" => 4,
				"bastion/mobs/crossbow_piglin" => 4,
				"bastion/mobs/empty" => 1
			],
			"bastion/mobs/hoglin" => [
				"bastion/mobs/hoglin" => 2,
				"bastion/mobs/empty" => 1
			],
			"bastion/blocks/gold" => [
				"bastion/blocks/air" => 3,
				"bastion/blocks/gold" => 1
			],
			"bastion/mobs/piglin_melee" => [
				"bastion/mobs/melee_piglin_always" => 1,
				"bastion/mobs/melee_piglin" => 5,
				"bastion/mobs/sword_piglin" => 1
			]
		];
		$pools = [];
		foreach($definitions as $name => $entries){
			$pools[$name] = self::pool($name, $entries);
		}
		$pools["bastion/treasure/extensions/large_pool"] = self::poolList("bastion/treasure/extensions/large_pool", [
			["bastion/treasure/extensions/empty", 1],
			["bastion/treasure/extensions/empty", 1],
			["bastion/treasure/extensions/fire_room", 1],
			["bastion/treasure/extensions/large_bridge_0", 1],
			["bastion/treasure/extensions/large_bridge_1", 1],
			["bastion/treasure/extensions/large_bridge_2", 1],
			["bastion/treasure/extensions/large_bridge_3", 1],
			["bastion/treasure/extensions/roofed_bridge", 1],
			["bastion/treasure/extensions/empty", 1]
		]);
		$pools["bastion/treasure/extensions/small_pool"] = self::poolList("bastion/treasure/extensions/small_pool", [
			["bastion/treasure/extensions/empty", 1],
			["bastion/treasure/extensions/fire_room", 1],
			["bastion/treasure/extensions/empty", 1],
			["bastion/treasure/extensions/small_bridge_0", 1],
			["bastion/treasure/extensions/small_bridge_1", 1],
			["bastion/treasure/extensions/small_bridge_2", 1],
			["bastion/treasure/extensions/small_bridge_3", 1]
		]);
		return $pools;
	}
}
