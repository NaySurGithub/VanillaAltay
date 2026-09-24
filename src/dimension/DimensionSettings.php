<?php

declare(strict_types=1);

namespace dimension;

use pocketmine\network\mcpe\protocol\types\DimensionIds;
use function is_array;
use function is_bool;
use function is_string;

/**
 * The "dimensions" section of config.yml.
 */
final class DimensionSettings{

	public function __construct(
		public readonly bool $enabled,
		public readonly bool $netherEnabled,
		public readonly string $netherWorld,
		public readonly bool $endEnabled,
		public readonly string $endWorld
	){}

	/**
	 * @param array<mixed> $section
	 */
	public static function fromConfig(array $section) : self{
		$nether = is_array($section["nether"] ?? null) ? $section["nether"] : [];
		$end = is_array($section["end"] ?? null) ? $section["end"] : [];
		return new self(
			self::flag($section, "enabled"),
			self::flag($nether, "enabled"),
			self::name($nether, "world_nether"),
			self::flag($end, "enabled"),
			self::name($end, "world_the_end")
		);
	}

	/**
	 * @return array<int, string> dimension id => world folder name
	 */
	public function getWorldNames() : array{
		$names = [];
		if($this->netherEnabled){
			$names[DimensionIds::NETHER] = $this->netherWorld;
		}
		if($this->endEnabled){
			$names[DimensionIds::THE_END] = $this->endWorld;
		}
		return $names;
	}

	/**
	 * @param array<mixed> $section
	 */
	private static function flag(array $section, string $key) : bool{
		$value = $section[$key] ?? null;
		return is_bool($value) ? $value : true;
	}

	/**
	 * @param array<mixed> $section
	 */
	private static function name(array $section, string $default) : string{
		$value = $section["world"] ?? null;
		return is_string($value) && $value !== "" ? $value : $default;
	}
}
