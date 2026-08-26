<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure;

use function array_keys;

final class StructureRegistry
{
	/** @var Structure[]|null */
	private static ?array $structures = null;

	private function __construct()
	{
		//NOOP
	}

	/**
	 * @return Structure[]
	 * @phpstan-return array<string, Structure>
	 */
	public static function all() : array
	{
		if (self::$structures === null) {
			self::$structures = [];
			$structures = [
				new DesertWell(), new SwampHut(), new MonsterRoom(), new JungleTemple(), new DesertPyramid(),
				new Mineshaft(), new Stronghold(), new PillagerOutpost(), new Fossil(), new OceanRuin(),
				new Igloo(), new Shipwreck(), new RuinedPortal(), new Village(), new AncientCity(), new TrailRuins(),
			];

			foreach ($structures as $structure) {
				self::$structures[$structure->getName()] = $structure;
			}
		}

		return self::$structures;
	}

	public static function get(string $name) : ?Structure
	{
		return self::all()[$name] ?? null;
	}

	/**
	 * @return string[]
	 */
	public static function getNames() : array
	{
		return array_keys(self::all());
	}
}
