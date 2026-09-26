<?php

declare(strict_types=1);

namespace dummy\block;

use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\data\bedrock\block\convert\BlockStateReader;
use pocketmine\math\Facing;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\Tag;
use function count;
use function implode;
use function ksort;

/**
 * Every vanilla state of a block the server does not implement, along with the
 * physical properties read from the vanilla block data.
 */
final class DummyBlockType{

	private const FACE_NAMES = [
		Facing::DOWN => "down",
		Facing::UP => "up",
		Facing::NORTH => "north",
		Facing::SOUTH => "south",
		Facing::WEST => "west",
		Facing::EAST => "east"
	];

	private const LEGACY_HORIZONTAL = [
		Facing::SOUTH => 0,
		Facing::WEST => 1,
		Facing::NORTH => 2,
		Facing::EAST => 3
	];

	/** @var array<string, int> */
	private array $indexes = [];

	/**
	 * @param list<BlockStateData> $states
	 */
	public function __construct(
		private string $name,
		private array $states,
		public readonly int $lightLevel,
		public readonly bool $transparent,
		public readonly bool $passable,
		public readonly float $friction,
		public readonly int $flameEncouragement,
		public readonly int $flammability
	){
		foreach($states as $index => $state){
			$this->indexes[self::key($state->getStates())] = $index;
		}
	}

	public function getName() : string{
		return $this->name;
	}

	public function getStateCount() : int{
		return count($this->states);
	}

	public function getState(int $index) : BlockStateData{
		return $this->states[$index] ?? $this->states[0];
	}

	/**
	 * Reads the saved state of the block and returns its index, falling back on the first state when the saved
	 * values match none of the vanilla states.
	 */
	public function read(BlockStateReader $in) : int{
		$values = [];
		foreach($this->states[0]->getStates() as $stateName => $tag){
			$values[$stateName] = match(true){
				$tag instanceof IntTag => new IntTag($in->readInt($stateName)),
				$tag instanceof StringTag => new StringTag($in->readString($stateName)),
				default => new ByteTag($in->readBool($stateName) ? 1 : 0)
			};
		}
		return $this->indexes[self::key($values)] ?? 0;
	}

	/**
	 * Returns the state the block should take when placed: states describing a direction are turned toward the
	 * player or the clicked face, every other state is kept.
	 */
	public function orient(int $index, int $clickedFace, ?int $playerFacing) : int{
		$states = $this->getState($index)->getStates();
		$towardPlayer = $playerFacing === null ? null : Facing::opposite($playerFacing);
		$overrides = [];
		if(isset($states["minecraft:cardinal_direction"]) && $towardPlayer !== null){
			$overrides["minecraft:cardinal_direction"] = new StringTag(self::FACE_NAMES[$towardPlayer]);
		}
		if(isset($states["direction"]) && $towardPlayer !== null){
			$overrides["direction"] = new IntTag(self::LEGACY_HORIZONTAL[$towardPlayer]);
		}
		if(isset($states["minecraft:facing_direction"])){
			$overrides["minecraft:facing_direction"] = new StringTag(self::FACE_NAMES[$clickedFace]);
		}
		if(isset($states["minecraft:block_face"])){
			$overrides["minecraft:block_face"] = new StringTag(self::FACE_NAMES[$clickedFace]);
		}
		if(isset($states["facing_direction"])){
			$overrides["facing_direction"] = new IntTag($clickedFace);
		}
		if(count($overrides) === 0){
			return $index;
		}
		return $this->indexes[self::key($overrides + $states)] ?? $index;
	}

	/**
	 * @param array<string, Tag> $values
	 */
	private static function key(array $values) : string{
		ksort($values);
		$parts = [];
		foreach($values as $name => $tag){
			$parts[] = $name . "=" . $tag->getValue();
		}
		return implode(";", $parts);
	}
}
