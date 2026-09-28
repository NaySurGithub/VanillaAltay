<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\inventory;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\loot\LootContext;
use behaviorpack\loot\LootFunction;
use pocketmine\item\Item;
use function array_is_list;
use function array_key_exists;
use function array_slice;
use function count;
use function file_get_contents;
use function is_array;
use function is_file;
use function is_int;
use function is_string;
use function json_decode;
use function ltrim;
use function max;
use function min;
use function mt_rand;
use function preg_replace;
use function rtrim;
use function shuffle;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function substr;

/**
 * The trading files of the behavior packs ("trading/*.json"): tiers of
 * trade groups, each trade asking up to two items for one.
 */
final class TradeTable{

	/** @var list<string> */
	private static array $packRoots = [];

	/** @var array<string, array<mixed>|null> */
	private static array $cache = [];

	private function __construct(){
	}

	public static function registerPackRoot(string $root) : void{
		$root = rtrim(str_replace("\\", "/", $root), "/");
		foreach(self::$packRoots as $known){
			if($known === $root){
				return;
			}
		}
		self::$packRoots[] = $root;
		self::$cache = [];
	}

	public static function clear() : void{
		self::$packRoots = [];
		self::$cache = [];
	}

	/**
	 * @return list<array<mixed>>
	 */
	public static function tiers(string $path) : array{
		$json = self::load($path);
		if($json === null){
			return [];
		}
		$tiers = $json["tiers"] ?? [];
		if(!is_array($tiers)){
			return [];
		}
		$result = [];
		foreach($tiers as $tier){
			if(is_array($tier)){
				$result[] = $tier;
			}
		}
		return $result;
	}

	/**
	 * Returns the experience needed to unlock each tier. Tiers without
	 * total_exp_required use the vanilla villager thresholds.
	 *
	 * @return list<int>
	 */
	public static function tierRequirements(string $path) : array{
		$defaults = [0, 10, 70, 150, 250];
		$requirements = [];
		foreach(self::tiers($path) as $index => $tier){
			$value = $tier["total_exp_required"] ?? null;
			$requirements[] = is_int($value) ? max(0, $value) : ($defaults[$index] ?? ($defaults[count($defaults) - 1] + 100 * ($index - count($defaults) + 1)));
		}
		return $requirements;
	}

	/**
	 * @return array<mixed>|null
	 */
	private static function load(string $path) : ?array{
		$path = ltrim(str_replace("\\", "/", $path), "/");
		if(array_key_exists($path, self::$cache)){
			return self::$cache[$path];
		}
		$decoded = null;
		foreach(self::$packRoots as $root){
			$file = $root . "/" . $path;
			if(!is_file($file)){
				continue;
			}
			$contents = file_get_contents($file);
			if($contents === false){
				continue;
			}
			$contents = preg_replace('#^\s*//.*$#m', "", $contents) ?? $contents;
			$json = json_decode($contents, true);
			if(is_array($json)){
				$decoded = $json;
				break;
			}
		}
		self::$cache[$path] = $decoded;
		return $decoded;
	}

	/**
	 * Rolls the trades of a tier as data store entries.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function rollTier(string $path, int $tierIndex, BehaviorEntity $entity) : array{
		$tier = self::tiers($path)[$tierIndex] ?? null;
		if($tier === null){
			return [];
		}
		$groups = $tier["groups"] ?? null;
		if(!is_array($groups)){
			$groups = [["num_to_select" => 0, "trades" => $tier["trades"] ?? []]];
		}
		$result = [];
		foreach($groups as $group){
			if(!is_array($group) || !is_array($group["trades"] ?? null)){
				continue;
			}
			$trades = [];
			foreach($group["trades"] as $trade){
				if(is_array($trade)){
					$trades[] = $trade;
				}
			}
			$select = is_int($group["num_to_select"] ?? null) ? $group["num_to_select"] : 0;
			if($select > 0 && $select < count($trades)){
				shuffle($trades);
				$trades = array_slice($trades, 0, $select);
			}
			foreach($trades as $trade){
				$entry = self::rollTrade($trade, $tierIndex, $entity);
				if($entry !== null){
					$result[] = $entry;
				}
			}
		}
		return $result;
	}

	/**
	 * @param array<mixed> $trade
	 *
	 * @return array<string, mixed>|null
	 */
	private static function rollTrade(array $trade, int $tierIndex, BehaviorEntity $entity) : ?array{
		$wants = self::list($trade["wants"] ?? []);
		$gives = self::list($trade["gives"] ?? []);
		$first = isset($wants[0]) ? self::rollItem($wants[0], $entity) : null;
		$sell = isset($gives[0]) ? self::rollItem($gives[0], $entity) : null;
		if($first === null || $sell === null){
			return null;
		}
		$second = isset($wants[1]) ? self::rollItem($wants[1], $entity) : null;
		$maxUses = is_int($trade["max_uses"] ?? null) ? max(1, $trade["max_uses"]) : 7;
		$traderExp = is_int($trade["trader_exp"] ?? null) ? max(0, $trade["trader_exp"]) : 1;
		return [
			"tier" => $tierIndex,
			"a" => ItemCodec::encode($first[0]),
			"pma" => $first[1],
			"b" => $second === null ? null : ItemCodec::encode($second[0]),
			"pmb" => $second === null ? 0.0 : $second[1],
			"sell" => ItemCodec::encode($sell[0]),
			"max_uses" => $maxUses,
			"uses" => 0,
			"trader_exp" => $traderExp,
			"reward_exp" => ($trade["reward_exp"] ?? true) !== false,
			"demand" => 0
		];
	}

	/**
	 * @return list<array<mixed>>
	 */
	private static function list(mixed $value) : array{
		if(!is_array($value)){
			return [];
		}
		if(!array_is_list($value)){
			$value = [$value];
		}
		$result = [];
		foreach($value as $entry){
			if(is_array($entry)){
				$result[] = $entry;
			}elseif(is_string($entry)){
				$result[] = ["item" => $entry];
			}
		}
		return $result;
	}

	/**
	 * @param array<mixed> $descriptor
	 *
	 * @return array{Item, float}|null
	 */
	private static function rollItem(array $descriptor, BehaviorEntity $entity) : ?array{
		if(is_array($descriptor["choice"] ?? null)){
			$choices = self::list($descriptor["choice"]);
			if($choices === []){
				return null;
			}
			$descriptor = $choices[mt_rand(0, count($choices) - 1)];
		}
		$identifier = $descriptor["item"] ?? null;
		if(!is_string($identifier)){
			return null;
		}
		$count = max(1, (int) BehaviorEntity::rangeValue($descriptor["quantity"] ?? null, 1.0));
		$data = is_int($descriptor["data"] ?? null) ? $descriptor["data"] : -1;
		$item = ItemCodec::parse($identifier, 1, $data);
		if($item === null){
			return null;
		}
		$item->setCount(1);
		$functions = $descriptor["functions"] ?? [];
		if(is_array($functions)){
			$context = new LootContext($entity);
			foreach($functions as $function){
				if(!is_array($function) || !is_string($function["function"] ?? null)){
					continue;
				}
				$type = strtolower($function["function"]);
				if(str_starts_with($type, "minecraft:")){
					$type = substr($type, 10);
				}
				if(LootFunction::isKnown($type)){
					$item = (new LootFunction($type, $function, []))->apply($item, $context);
				}
			}
		}
		$item->setCount(min($count, max(1, $item->getMaxStackSize())));
		return [$item, BehaviorEntity::toFloat($descriptor["price_multiplier"] ?? null, 0.0)];
	}
}
