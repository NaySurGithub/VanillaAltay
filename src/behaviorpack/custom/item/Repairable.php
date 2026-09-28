<?php

declare(strict_types=1);

namespace behaviorpack\custom\item;

use behaviorpack\BehaviorPackException;
use behaviorpack\Molang;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use function count;
use function is_array;
use function is_float;
use function is_int;
use function is_string;
use function max;
use function preg_replace;
use function str_starts_with;
use function substr;

/**
 * minecraft:repairable: the items that repair an item in an anvil and the
 * durability each of them restores.
 */
final class Repairable{

	private const MOLANG_VERSION = 12;

	/** @var list<array{items: list<string>, amount: int|float|string}> */
	private array $entries = [];

	/** @var array<string, int> */
	private array $typeIds = [];

	/**
	 * @throws BehaviorPackException
	 */
	public function __construct(mixed $value){
		$repairItems = is_array($value) ? ($value["repair_items"] ?? null) : null;
		if(!is_array($repairItems)){
			throw new BehaviorPackException("minecraft:repairable needs repair_items");
		}
		foreach($repairItems as $entry){
			if(!is_array($entry)){
				continue;
			}
			$items = [];
			foreach(is_array($entry["items"] ?? null) ? $entry["items"] : [] as $item){
				$name = is_array($item) ? ($item["name"] ?? $item["item"] ?? null) : $item;
				if(is_string($name) && $name !== ""){
					$items[] = $name;
				}
			}
			$amount = $entry["repair_amount"] ?? 0;
			if(is_array($amount)){
				$amount = $amount["expression"] ?? 0;
			}
			if(!is_int($amount) && !is_float($amount) && !is_string($amount)){
				throw new BehaviorPackException("minecraft:repairable: invalid repair_amount");
			}
			if(count($items) > 0){
				$this->entries[] = ["items" => $items, "amount" => $amount];
			}
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	public function networkValue() : array{
		$repairItems = [];
		foreach($this->entries as $entry){
			$items = [];
			foreach($entry["items"] as $name){
				$items[] = ["name" => $name];
			}
			$repairItems[] = [
				"items" => $items,
				"repair_amount" => [
					"expression" => (string) $entry["amount"],
					"version" => self::MOLANG_VERSION
				]
			];
		}
		return ["repair_items" => $repairItems];
	}

	/**
	 * Returns whether the material is listed in one of the repair entries.
	 */
	public function accepts(Item $material) : bool{
		return $this->findAmount($material) !== null;
	}

	/**
	 * Evaluates the durability the material restores to the subject, or
	 * null when the material is not listed.
	 */
	public function evaluate(Durable $subject, Item $material) : ?int{
		$amount = $this->findAmount($material);
		if($amount === null){
			return null;
		}
		if(is_string($amount)){
			$amount = (string) preg_replace('/\b(?:c|context)\.other\s*->\s*(?:q|query)\./i', "q.other_", $amount);
		}
		$result = Molang::evaluate($amount, [], function(string $name, array $args) use ($subject, $material) : float|int|null{
			$target = $subject;
			if(str_starts_with($name, "other_")){
				$name = substr($name, 6);
				if(!$material instanceof Durable){
					return 0;
				}
				$target = $material;
			}
			return match($name){
				"max_durability" => $target->getMaxDurability(),
				"remaining_durability" => $target->getMaxDurability() - $target->getDamage(),
				"damage" => $target->getDamage(),
				default => null
			};
		});
		return max(0, (int) $result);
	}

	private function findAmount(Item $material) : int|float|string|null{
		$typeId = $material->getTypeId();
		foreach($this->entries as $entry){
			foreach($entry["items"] as $name){
				if($this->resolveTypeId($name) === $typeId){
					return $entry["amount"];
				}
			}
		}
		return null;
	}

	private function resolveTypeId(string $name) : ?int{
		if(!isset($this->typeIds[$name])){
			$item = StringToItemParser::getInstance()->parse($name);
			if($item === null){
				return null;
			}
			$this->typeIds[$name] = $item->getTypeId();
		}
		return $this->typeIds[$name];
	}
}
