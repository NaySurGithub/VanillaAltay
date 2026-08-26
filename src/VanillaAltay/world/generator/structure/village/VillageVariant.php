<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\village;

use pocketmine\data\bedrock\BiomeIds;

/**
 * One biome's share of the archive: its town centres, its street pieces, its houses and the caps that close a
 * street. The zombie variants, the villager markers and the animal markers are left out.
 */
final class VillageVariant
{
	/**
	 * @param string[] $townCenters
	 * @param string[] $streets
	 * @param string[] $houses
	 * @param string[] $terminators
	 */
	private function __construct(
		public readonly string $name,
		public readonly array $townCenters,
		public readonly array $streets,
		public readonly array $houses,
		public readonly array $terminators,
	) {}

	/**
	 * @var self[]
	 * @phpstan-var array<string, self>
	 */
	private static array $variants = [];

	public static function forBiome(int $biomeId) : ?self
	{
		$name = match ($biomeId) {
			BiomeIds::PLAINS, BiomeIds::SUNFLOWER_PLAINS, BiomeIds::MEADOW => "plains",
			BiomeIds::DESERT => "desert",
			BiomeIds::SAVANNA => "savanna",
			BiomeIds::TAIGA => "taiga",
			BiomeIds::ICE_PLAINS, BiomeIds::COLD_TAIGA => "snowy",
			default => null,
		};

		if ($name === null) {
			return null;
		}

		return self::$variants[$name] ??= self::build($name);
	}

	private static function build(string $name) : self
	{
		return match ($name) {
			"plains" => new self(
				"plains",
				self::prefix("village/plains/town_centers/", ["plains_fountain_01", "plains_meeting_point_1", "plains_meeting_point_2", "plains_meeting_point_3"]),
				self::prefix("village/plains/streets/", ["straight_01", "straight_02", "straight_03", "straight_04", "straight_05", "straight_06"]),
				self::prefix("village/plains/houses/", [
					"plains_small_house_1", "plains_small_house_2", "plains_small_house_3", "plains_small_house_4",
					"plains_small_house_5", "plains_small_house_6", "plains_small_house_7", "plains_small_house_8",
					"plains_medium_house_1", "plains_medium_house_2", "plains_big_house_1",
					"plains_butcher_shop_1", "plains_butcher_shop_2", "plains_tool_smith_1", "plains_fletcher_house_1",
					"plains_shepherds_house_1", "plains_armorer_house_1", "plains_fisher_cottage_1", "plains_tannery_1",
					"plains_cartographer_1", "plains_library_1", "plains_library_2", "plains_masons_house_1",
					"plains_weaponsmith_1", "plains_temple_3", "plains_temple_4", "plains_stable_1", "plains_stable_2",
					"plains_large_farm_1", "plains_small_farm_1", "plains_animal_pen_1", "plains_animal_pen_2", "plains_animal_pen_3",
				]),
				self::prefix("village/plains/terminators/", ["terminator_01", "terminator_02", "terminator_03", "terminator_04"]),
			),
			"desert" => new self(
				"desert",
				self::prefix("village/desert/town_centers/", ["desert_meeting_point_1", "desert_meeting_point_2", "desert_meeting_point_3"]),
				self::prefix("village/desert/streets/", ["straight_01", "straight_02", "straight_03"]),
				self::prefix("village/desert/houses/", [
					"desert_small_house_1", "desert_small_house_2", "desert_small_house_3", "desert_small_house_4",
					"desert_small_house_5", "desert_small_house_6", "desert_small_house_7", "desert_small_house_8",
					"desert_medium_house_1", "desert_medium_house_2", "desert_animal_pen_1", "desert_animal_pen_2",
					"desert_armorer_1", "desert_butcher_shop_1", "desert_cartographer_house_1", "desert_farm_1",
					"desert_farm_2", "desert_fisher_1", "desert_fletcher_house_1", "desert_large_farm_1",
					"desert_library_1", "desert_mason_1", "desert_shepherd_house_1", "desert_tannery_1",
					"desert_temple_1", "desert_temple_2", "desert_tool_smith_1", "desert_weaponsmith_1",
				]),
				self::prefix("village/desert/terminators/", ["terminator_01", "terminator_02"]),
			),
			"savanna" => new self(
				"savanna",
				self::prefix("village/savanna/town_centers/", ["savanna_meeting_point_1", "savanna_meeting_point_2", "savanna_meeting_point_3", "savanna_meeting_point_4"]),
				self::prefix("village/savanna/streets/", ["straight_02", "straight_04", "straight_05", "straight_06", "straight_08", "straight_09", "straight_10", "straight_11"]),
				self::prefix("village/savanna/houses/", [
					"savanna_small_house_1", "savanna_small_house_2", "savanna_small_house_3", "savanna_small_house_4",
					"savanna_small_house_5", "savanna_small_house_6", "savanna_small_house_7", "savanna_small_house_8",
					"savanna_medium_house_1", "savanna_medium_house_2", "savanna_animal_pen_1", "savanna_animal_pen_2",
					"savanna_animal_pen_3", "savanna_armorer_1", "savanna_butchers_shop_1", "savanna_butchers_shop_2",
					"savanna_cartographer_1", "savanna_fisher_cottage_1", "savanna_fletcher_house_1",
					"savanna_large_farm_1", "savanna_large_farm_2", "savanna_library_1", "savanna_mason_1",
					"savanna_shepherd_1", "savanna_small_farm", "savanna_tannery_1", "savanna_temple_1",
					"savanna_temple_2", "savanna_tool_smith_1", "savanna_weaponsmith_1", "savanna_weaponsmith_2",
				]),
				self::prefix("village/savanna/terminators/", ["terminator_05"]),
			),
			"taiga" => new self(
				"taiga",
				self::prefix("village/taiga/town_centers/", ["taiga_meeting_point_1", "taiga_meeting_point_2"]),
				self::prefix("village/taiga/streets/", ["straight_01", "straight_02", "straight_03", "straight_04", "straight_05", "straight_06"]),
				self::prefix("village/taiga/houses/", [
					"taiga_small_house_1", "taiga_small_house_2", "taiga_small_house_3", "taiga_small_house_4",
					"taiga_small_house_5", "taiga_medium_house_1", "taiga_medium_house_2", "taiga_medium_house_3",
					"taiga_medium_house_4", "taiga_animal_pen_1", "taiga_armorer_2", "taiga_armorer_house_1",
					"taiga_butcher_shop_1", "taiga_cartographer_house_1", "taiga_fisher_cottage_1",
					"taiga_fletcher_house_1", "taiga_large_farm_1", "taiga_large_farm_2", "taiga_library_1",
					"taiga_masons_house_1", "taiga_shepherds_house_1", "taiga_small_farm_1", "taiga_tannery_1",
					"taiga_temple_1", "taiga_tool_smith_1", "taiga_weaponsmith_1", "taiga_weaponsmith_2",
				]),
				[],
			),
			default => new self(
				"snowy",
				self::prefix("village/snowy/town_centers/", ["snowy_meeting_point_1", "snowy_meeting_point_2", "snowy_meeting_point_3"]),
				self::prefix("village/snowy/streets/", ["straight_01", "straight_02", "straight_03", "straight_04", "straight_06", "straight_08"]),
				self::prefix("village/snowy/houses/", [
					"snowy_small_house_1", "snowy_small_house_2", "snowy_small_house_3", "snowy_small_house_4",
					"snowy_small_house_5", "snowy_small_house_6", "snowy_small_house_7", "snowy_small_house_8",
					"snowy_medium_house_1", "snowy_medium_house_2", "snowy_medium_house_3", "snowy_animal_pen_1",
					"snowy_animal_pen_2", "snowy_armorer_house_1", "snowy_armorer_house_2", "snowy_butchers_shop_1",
					"snowy_butchers_shop_2", "snowy_cartographer_house_1", "snowy_farm_1", "snowy_farm_2",
					"snowy_fisher_cottage", "snowy_fletcher_house_1", "snowy_library_1", "snowy_masons_house_1",
					"snowy_masons_house_2", "snowy_shepherds_house_1", "snowy_tannery_1", "snowy_temple_1",
					"snowy_tool_smith_1", "snowy_weapon_smith_1",
				]),
				[],
			),
		};
	}

	/**
	 * @param string[] $names
	 * @return string[]
	 */
	private static function prefix(string $prefix, array $names) : array
	{
		$identifiers = [];
		foreach ($names as $name) {
			$identifiers[] = $prefix . $name;
		}

		return $identifiers;
	}
}
