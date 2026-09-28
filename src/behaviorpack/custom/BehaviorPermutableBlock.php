<?php

declare(strict_types=1);

namespace behaviorpack\custom;

use behaviorpack\custom\connection\CardinalConnections;
use behaviorpack\custom\connection\MultiBlock;
use behaviorpack\custom\connection\StairCorner;
use behaviorpack\custom\connection\StateCondition;
use customiesdevs\customies\block\permutations\BlockProperty;
use customiesdevs\customies\block\permutations\Permutable;
use customiesdevs\customies\block\permutations\Permutation;
use pocketmine\block\Block;
use pocketmine\data\bedrock\block\convert\BlockStateReader;
use pocketmine\data\bedrock\block\convert\BlockStateWriter;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\World;
use Throwable;
use function array_key_exists;
use function array_search;
use function count;
use function intdiv;
use function is_bool;
use function is_int;

/**
 * A behavior pack block with states, from description.states or from the
 * placement traits. The state is stored as one index in the cartesian
 * product of every property, last property varying fastest.
 *
 * @phpstan-import-type Definition from BehaviorBlock
 */
final class BehaviorPermutableBlock extends BehaviorBlock implements Permutable{

	public const CARDINAL_DIRECTION = "minecraft:cardinal_direction";
	public const FACING_DIRECTION = "minecraft:facing_direction";
	public const BLOCK_FACE = "minecraft:block_face";
	public const VERTICAL_HALF = "minecraft:vertical_half";

	public const FACING_NAMES = [
		Facing::DOWN => "down",
		Facing::UP => "up",
		Facing::NORTH => "north",
		Facing::SOUTH => "south",
		Facing::WEST => "west",
		Facing::EAST => "east"
	];

	private int $stateIndex = 0;

	/**
	 * @return list<BlockProperty>
	 */
	public function getBlockProperties() : array{
		$properties = [];
		foreach($this->definition["properties"] as [$name, $values]){
			$properties[] = new BlockProperty($name, $values);
		}
		return $properties;
	}

	/**
	 * @return list<Permutation>
	 */
	public function getPermutations() : array{
		$permutations = [];
		foreach($this->definition["permutations"] as [$condition, $components]){
			$permutation = new Permutation($condition);
			foreach($components as $name => $value){
				try{
					$component = BlockComponentMapper::map($name, $value);
				}catch(Throwable){
					$component = null;
				}
				if($component !== null){
					$permutation->withComponent($component->getName(), $component->getValue());
				}
			}
			$permutations[] = $permutation;
		}
		return $permutations;
	}

	/**
	 * @return list<bool|int|string>
	 */
	public function getCurrentBlockProperties() : array{
		$current = [];
		foreach($this->getValueIndexes() as $i => $valueIndex){
			$current[] = $this->definition["properties"][$i][1][$valueIndex];
		}
		return $current;
	}

	public function serializeState(BlockStateWriter $blockStateOut) : void{
		foreach($this->getCurrentBlockProperties() as $i => $value){
			$name = $this->definition["properties"][$i][0];
			if(is_bool($value)){
				$blockStateOut->writeBool($name, $value);
			}elseif(is_int($value)){
				$blockStateOut->writeInt($name, $value);
			}else{
				$blockStateOut->writeString($name, $value);
			}
		}
	}

	public function deserializeState(BlockStateReader $blockStateIn) : void{
		$indexes = [];
		foreach($this->definition["properties"] as [$name, $values]){
			$first = $values[0];
			if(is_bool($first)){
				$value = $blockStateIn->readBool($name);
			}elseif(is_int($first)){
				$value = $blockStateIn->readInt($name);
			}else{
				$value = $blockStateIn->readString($name);
			}
			$index = array_search($value, $values, true);
			$indexes[] = $index === false ? 0 : $index;
		}
		$this->setValueIndexes($indexes);
	}

	/**
	 * Sets the value of a property by name, ignored when the block has no
	 * such property or the value is not one of its values.
	 */
	public function setPropertyValue(string $name, bool|int|string $value) : void{
		$indexes = $this->getValueIndexes();
		foreach($this->definition["properties"] as $i => [$propertyName, $values]){
			if($propertyName !== $name){
				continue;
			}
			$index = array_search($value, $values, true);
			if($index !== false){
				$indexes[$i] = $index;
				$this->setValueIndexes($indexes);
			}
			return;
		}
	}

	/**
	 * @phpstan-return Definition
	 */
	public function getDefinition() : array{
		return $this->definition;
	}

	/**
	 * Returns the current value of a property by name, or null when the
	 * block has no such property.
	 */
	public function getPropertyValue(string $name) : bool|int|string|null{
		foreach($this->getCurrentBlockProperties() as $i => $value){
			if($this->definition["properties"][$i][0] === $name){
				return $value;
			}
		}
		return null;
	}

	/**
	 * Returns the value of a component in the current state: the base
	 * component overridden by every matching permutation, last one winning.
	 */
	public function getActiveComponent(string $name) : mixed{
		$value = $this->definition["components"][$name] ?? null;
		$lookup = fn(string $state) : bool|int|string|null => $this->getPropertyValue($state);
		foreach($this->definition["permutations"] as [$condition, $components]){
			if(array_key_exists($name, $components) && StateCondition::evaluate($condition, $lookup)){
				$value = $components[$name];
			}
		}
		return $value;
	}

	public function onNearbyBlockChange() : void{
		parent::onNearbyBlockChange();
		if(!$this->position->isValid()){
			return;
		}
		$world = $this->position->getWorld();
		$position = $this->position->asVector3();
		if(MultiBlock::parts($this) > 0 && !MultiBlock::isIntact($this, $world, $position)){
			$world->useBreakOn($position);
			return;
		}
		$before = $this->stateIndex;
		$this->deriveState($world, $position);
		if($this->stateIndex !== $before){
			$world->setBlock($position, $this);
		}
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		$world = $this->position->getWorld();
		$position = $this->position->asVector3();
		$result = parent::onBreak($item, $player, $returnedItems);
		if(MultiBlock::parts($this) > 0){
			MultiBlock::removeOthers($this, $world, $position);
		}
		return $result;
	}

	/**
	 * Recomputes the states derived from the neighbours: stair corner and
	 * cardinal connections.
	 */
	private function deriveState(World $world, Vector3 $position) : void{
		$traits = $this->definition["traits"];
		if($traits["corner"] ?? false){
			StairCorner::apply($this, $world, $position);
		}
		if($traits["connection"] ?? false){
			CardinalConnections::apply($this, $world, $position);
		}
	}

	/**
	 * Adds the upper or lower parts of a multi block to the transaction, or
	 * returns false when one of them has no room.
	 */
	private function placeParts(BlockTransaction $tx, World $world, Vector3 $position) : bool{
		$parts = MultiBlock::parts($this);
		$step = MultiBlock::step($this);
		$this->setPropertyValue(MultiBlock::PART, 0);
		$targets = [];
		for($i = 1; $i < $parts; $i++){
			$target = $position->getSide($step, $i);
			if(!$world->isInWorld($target->getFloorX(), $target->getFloorY(), $target->getFloorZ()) || !$world->getBlock($target)->canBeReplaced()){
				return false;
			}
			$targets[$i] = $target;
		}
		foreach($targets as $i => $target){
			$part = clone $this;
			$part->setPropertyValue(MultiBlock::PART, $i);
			$part->deriveState($world, $target);
			$tx->addBlock($target, $part);
		}
		return true;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$traits = $this->definition["traits"];
		if($player !== null && $traits["cardinal"]){
			$facing = $player->getHorizontalFacing();
			$turns = (($traits["yRotationOffset"] % 360 + 360) % 360) / 90;
			for($i = 0; $i < $turns; $i++){
				$facing = Facing::rotateY($facing, true);
			}
			$this->setPropertyValue(self::CARDINAL_DIRECTION, self::FACING_NAMES[$facing]);
		}
		if($player !== null && $traits["facing"]){
			$pitch = $player->getLocation()->getPitch();
			$facing = $pitch > 45 ? Facing::DOWN : ($pitch < -45 ? Facing::UP : $player->getHorizontalFacing());
			$this->setPropertyValue(self::FACING_DIRECTION, self::FACING_NAMES[$facing]);
		}
		if($traits["blockFace"]){
			$this->setPropertyValue(self::BLOCK_FACE, self::FACING_NAMES[$face] ?? "up");
		}
		if($traits["verticalHalf"]){
			$top = $face === Facing::DOWN || ($face !== Facing::UP && $clickVector->y > 0.5);
			$this->setPropertyValue(self::VERTICAL_HALF, $top ? "top" : "bottom");
		}
		$world = $blockReplace->getPosition()->getWorld();
		$position = $blockReplace->getPosition()->asVector3();
		$this->deriveState($world, $position);
		if(MultiBlock::parts($this) > 0 && !$this->placeParts($tx, $world, $position)){
			return false;
		}
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$index = $this->stateIndex;
		$w->boundedIntAuto(0, $this->getStateCount() - 1, $index);
		$this->stateIndex = $index;
	}

	private function getStateCount() : int{
		$count = 1;
		foreach($this->definition["properties"] as [, $values]){
			$count *= count($values);
		}
		return $count;
	}

	/**
	 * @return list<int>
	 */
	private function getValueIndexes() : array{
		$indexes = [];
		$remaining = $this->stateIndex;
		for($i = count($this->definition["properties"]) - 1; $i >= 0; $i--){
			$size = count($this->definition["properties"][$i][1]);
			$indexes[$i] = $remaining % $size;
			$remaining = intdiv($remaining, $size);
		}
		$ordered = [];
		for($i = 0, $total = count($indexes); $i < $total; $i++){
			$ordered[] = $indexes[$i];
		}
		return $ordered;
	}

	/**
	 * @param array<int, int> $indexes
	 */
	private function setValueIndexes(array $indexes) : void{
		$state = 0;
		foreach($this->definition["properties"] as $i => [, $values]){
			$state = $state * count($values) + ($indexes[$i] ?? 0);
		}
		$this->stateIndex = $state;
	}
}
