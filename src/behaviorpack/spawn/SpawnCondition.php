<?php

declare(strict_types=1);

namespace behaviorpack\spawn;

use function array_is_list;
use function array_search;
use function count;
use function is_array;
use function is_numeric;
use function is_string;
use function max;
use function min;
use function strtolower;

/**
 * One entry of the "conditions" list of a spawn rule.
 */
final class SpawnCondition{

	private const DIFFICULTIES = ["peaceful", "easy", "normal", "hard"];

	public bool $surface = false;
	public bool $underground = false;
	public bool $underwater = false;
	public bool $lava = false;

	/** @var list<string>|null */
	public ?array $onBlocks = null;
	/** @var list<string>|null */
	public ?array $preventedBlocks = null;
	/** @var list<string>|null */
	public ?array $aboveBlocks = null;
	public int $aboveDistance = 0;

	public ?int $minLight = null;
	public ?int $maxLight = null;
	public int $minDifficulty = 0;
	public int $maxDifficulty = 3;
	public int $weight = 1;

	public int $herdMin = 1;
	public int $herdMax = 1;
	public ?string $herdEvent = null;
	public int $herdEventSkip = 0;
	public ?string $initialEvent = null;
	public int $initialEventCount = 0;

	/** @var list<array{weight: int, type: string}> */
	public array $permutations = [];

	public ?int $densitySurface = null;
	public ?int $densityUnderground = null;

	/** @var array<mixed>|null */
	public ?array $biomeFilter = null;

	public ?int $minY = null;
	public ?int $maxY = null;
	public ?float $minDistance = null;
	public ?float $maxDistance = null;

	public ?int $delayMin = null;
	public ?int $delayMax = null;
	public ?string $delayIdentifier = null;
	public int $delayChance = 100;

	public ?int $worldAgeMin = null;
	public ?string $mobEvent = null;
	public bool $requiresVillage = false;
	public bool $experimental = false;
	public bool $noBubble = false;

	/**
	 * @param array<string, mixed> $json
	 */
	public static function parse(array $json) : self{
		$c = new self();
		$c->surface = isset($json["minecraft:spawns_on_surface"]);
		$c->underground = isset($json["minecraft:spawns_underground"]);
		$c->underwater = isset($json["minecraft:spawns_underwater"]);
		$c->lava = isset($json["minecraft:spawns_lava"]);
		$c->onBlocks = self::blockList($json["minecraft:spawns_on_block_filter"] ?? null);
		$c->preventedBlocks = self::blockList($json["minecraft:spawns_on_block_prevented_filter"] ?? null);

		$above = $json["minecraft:spawns_above_block_filter"] ?? null;
		if(is_array($above)){
			$c->aboveBlocks = self::blockList($above["blocks"] ?? null) ?? [];
			$c->aboveDistance = max(0, (int) self::number($above["distance"] ?? 0));
		}

		$brightness = $json["minecraft:brightness_filter"] ?? null;
		if(is_array($brightness)){
			$c->minLight = (int) self::number($brightness["min"] ?? 0);
			$c->maxLight = (int) self::number($brightness["max"] ?? 15);
		}

		$difficulty = $json["minecraft:difficulty_filter"] ?? null;
		if(is_array($difficulty)){
			$c->minDifficulty = self::difficulty($difficulty["min"] ?? null, 0);
			$c->maxDifficulty = self::difficulty($difficulty["max"] ?? null, 3);
		}

		$weight = $json["minecraft:weight"] ?? null;
		if(is_array($weight)){
			$c->weight = max(0, (int) self::number($weight["default"] ?? 1));
		}

		$herd = $json["minecraft:herd"] ?? null;
		if(is_array($herd) && array_is_list($herd)){
			$herd = $herd[0] ?? null;
		}
		if(is_array($herd)){
			$c->herdMin = max(1, (int) self::number($herd["min_size"] ?? 1));
			$c->herdMax = max($c->herdMin, (int) self::number($herd["max_size"] ?? $c->herdMin));
			$c->herdEvent = is_string($herd["event"] ?? null) ? $herd["event"] : null;
			$c->herdEventSkip = max(0, (int) self::number($herd["event_skip_count"] ?? 0));
			$c->initialEvent = is_string($herd["initial_event"] ?? null) ? $herd["initial_event"] : null;
			$c->initialEventCount = max(0, (int) self::number($herd["initial_event_count"] ?? ($c->initialEvent !== null ? 1 : 0)));
		}

		$permute = $json["minecraft:permute_type"] ?? null;
		if(is_array($permute)){
			foreach($permute as $entry){
				if(is_array($entry) && is_string($entry["entity_type"] ?? null)){
					$c->permutations[] = ["weight" => max(0, (int) self::number($entry["weight"] ?? 1)), "type" => $entry["entity_type"]];
				}
			}
		}

		$density = $json["minecraft:density_limit"] ?? null;
		if(is_array($density)){
			$c->densitySurface = isset($density["surface"]) ? (int) self::number($density["surface"]) : null;
			$c->densityUnderground = isset($density["underground"]) ? (int) self::number($density["underground"]) : null;
		}

		$biome = $json["minecraft:biome_filter"] ?? null;
		$c->biomeFilter = is_array($biome) ? $biome : null;

		$height = $json["minecraft:height_filter"] ?? null;
		if(is_array($height)){
			$c->minY = isset($height["min"]) ? (int) self::number($height["min"]) : null;
			$c->maxY = isset($height["max"]) ? (int) self::number($height["max"]) : null;
		}

		$distance = $json["minecraft:distance_filter"] ?? null;
		if(is_array($distance)){
			$c->minDistance = isset($distance["min"]) ? self::number($distance["min"]) : null;
			$c->maxDistance = isset($distance["max"]) ? self::number($distance["max"]) : null;
		}

		$delay = $json["minecraft:delay_filter"] ?? null;
		if(is_array($delay)){
			$c->delayMin = max(0, (int) self::number($delay["min"] ?? 0));
			$c->delayMax = max($c->delayMin, (int) self::number($delay["max"] ?? $c->delayMin));
			$c->delayIdentifier = is_string($delay["identifier"] ?? null) ? $delay["identifier"] : null;
			$c->delayChance = min(100, max(0, (int) self::number($delay["spawn_chance"] ?? 100)));
		}

		$age = $json["minecraft:world_age_filter"] ?? null;
		if(is_array($age)){
			$c->worldAgeMin = (int) self::number($age["min"] ?? 0);
		}

		$mobEvent = $json["minecraft:mob_event_filter"] ?? null;
		if(is_array($mobEvent) && is_string($mobEvent["event"] ?? null)){
			$c->mobEvent = strtolower($mobEvent["event"]);
		}

		$c->requiresVillage = isset($json["minecraft:player_in_village_filter"]);
		$c->experimental = isset($json["minecraft:is_experimental"]);
		$c->noBubble = isset($json["minecraft:disallow_spawns_in_bubble"]);
		return $c;
	}

	/**
	 * @return list<string>|null
	 */
	private static function blockList(mixed $value) : ?array{
		if($value === null){
			return null;
		}
		if(is_string($value) || (is_array($value) && !array_is_list($value))){
			$value = [$value];
		}
		if(!is_array($value)){
			return null;
		}
		$names = [];
		foreach($value as $entry){
			if(is_array($entry)){
				$entry = $entry["name"] ?? null;
			}
			if(is_string($entry) && $entry !== ""){
				$names[] = BlockNames::normalize($entry);
			}
		}
		return count($names) > 0 ? $names : null;
	}

	private static function difficulty(mixed $value, int $default) : int{
		if(is_string($value)){
			$index = array_search(strtolower($value), self::DIFFICULTIES, true);
			return $index === false ? $default : $index;
		}
		return is_numeric($value) ? (int) $value : $default;
	}

	private static function number(mixed $value) : float{
		return is_numeric($value) ? (float) $value : 0.0;
	}
}
