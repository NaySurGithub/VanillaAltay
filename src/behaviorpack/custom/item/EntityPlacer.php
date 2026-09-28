<?php

declare(strict_types=1);

namespace behaviorpack\custom\item;

use behaviorpack\BehaviorPackException;
use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Location;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\world\World;
use function class_exists;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function md5;

/**
 * minecraft:entity_placer: the entity an item spawns and the blocks it can
 * be used or dispensed on.
 */
final class EntityPlacer{

	private const GENERATED_NAMESPACE = "behaviorpack\\entity\\generated";

	private string $entity;

	/** @var list<string> */
	private array $useOn;

	/** @var list<string> */
	private array $dispenseOn;

	/**
	 * @throws BehaviorPackException
	 */
	public function __construct(mixed $value){
		$entity = is_array($value) ? ($value["entity"] ?? null) : $value;
		if(!is_string($entity) || $entity === ""){
			throw new BehaviorPackException("minecraft:entity_placer needs an entity");
		}
		$this->entity = $entity;
		$this->useOn = self::blockNames(is_array($value) ? ($value["use_on"] ?? []) : []);
		$this->dispenseOn = self::blockNames(is_array($value) ? ($value["dispense_on"] ?? []) : []);
	}

	/**
	 * @return list<string>
	 */
	private static function blockNames(mixed $list) : array{
		$names = [];
		foreach(is_array($list) ? $list : [] as $entry){
			$name = is_array($entry) ? ($entry["name"] ?? null) : $entry;
			if(is_string($name) && $name !== ""){
				$names[] = $name;
			}
		}
		return $names;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function networkValue() : array{
		return [
			"entity" => $this->entity,
			"use_on" => self::networkBlocks($this->useOn),
			"dispense_on" => self::networkBlocks($this->dispenseOn)
		];
	}

	/**
	 * @param list<string> $names
	 * @return list<array{name: string}>
	 */
	private static function networkBlocks(array $names) : array{
		$result = [];
		foreach($names as $name){
			$result[] = ["name" => $name];
		}
		return $result;
	}

	public function canUseOn(Block $block) : bool{
		return self::matches($this->useOn, $block);
	}

	public function canDispenseOn(Block $block) : bool{
		return self::matches($this->dispenseOn, $block);
	}

	/**
	 * @param list<string> $names
	 */
	private static function matches(array $names, Block $block) : bool{
		if(count($names) === 0){
			return true;
		}
		$typeIds = [];
		foreach($names as $name){
			$item = StringToItemParser::getInstance()->parse($name);
			if($item !== null){
				$typeIds[] = $item->getBlock()->getTypeId();
			}
		}
		return in_array($block->getTypeId(), $typeIds, true);
	}

	/**
	 * Creates the entity at the given position without spawning it, or null
	 * when the identifier is not a registered entity.
	 */
	public function create(World $world, Vector3 $pos, float $yaw) : ?Entity{
		$class = self::GENERATED_NAMESPACE . "\\Entity" . md5($this->entity);
		if(class_exists($class, false)){
			return new $class(Location::fromObject($pos, $world, $yaw, 0.0));
		}
		$nbt = CompoundTag::create()
			->setString(EntityFactory::TAG_IDENTIFIER, $this->entity)
			->setTag("Pos", new ListTag([new DoubleTag($pos->x), new DoubleTag($pos->y), new DoubleTag($pos->z)]))
			->setTag("Motion", new ListTag([new DoubleTag(0.0), new DoubleTag(0.0), new DoubleTag(0.0)]))
			->setTag("Rotation", new ListTag([new FloatTag($yaw), new FloatTag(0.0)]));
		return EntityFactory::getInstance()->createFromData($world, $nbt);
	}
}
