<?php

declare(strict_types=1);

namespace behaviorpack\custom;

use behaviorpack\BehaviorPackException;
use behaviorpack\custom\block\ExtraBlockComponents;
use behaviorpack\custom\block\LiquidDetection;
use behaviorpack\custom\block\PlacementFilter;
use customiesdevs\customies\block\BlockComponents;
use customiesdevs\customies\block\component\BlockComponent;
use customiesdevs\customies\block\component\CollisionBoxComponent;
use pocketmine\block\Block;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockToolType;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\BlockTypeTags;
use pocketmine\block\utils\SupportType;
use pocketmine\math\Axis;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use redstone\block\power\PistonMovable;
use Throwable;
use function count;
use function in_array;
use function is_array;
use function max;
use function mt_rand;
use function str_starts_with;

/**
 * A block defined by a behavior pack minecraft:block file. Every setting
 * comes from a plain definition array built by CustomBlockRegistrar, so the
 * block can be rebuilt on the async workers.
 *
 * @phpstan-type Definition array{
 *     identifier: string,
 *     name: string,
 *     hardness: float,
 *     blastResistance: float,
 *     lightLevel: int,
 *     lightFilter: int,
 *     frictionFactor: float,
 *     flammability: array{int, int}|null,
 *     collision: list<float>|null,
 *     components: array<string, mixed>,
 *     properties: list<array{string, list<bool|int|string>}>,
 *     permutations: list<array{string, array<string, mixed>}>,
 *     traits: array{cardinal: bool, yRotationOffset: int, facing: bool, blockFace: bool, verticalHalf: bool}
 * }
 */
class BehaviorBlock extends Block implements BlockComponents, PistonMovable{

	/** @var array<string, BlockComponent> */
	private array $extraComponents = [];

	/**
	 * @phpstan-param Definition $definition
	 */
	public function __construct(
		BlockIdentifier $idInfo,
		BlockTypeInfo $typeInfo,
		protected array $definition
	){
		parent::__construct($idInfo, $definition["name"], $typeInfo);
	}

	/**
	 * @phpstan-param Definition $definition
	 */
	public static function create(int $typeId, array $definition) : Block{
		$typeInfo = new BlockTypeInfo(new BlockBreakInfo(
			$definition["hardness"],
			BlockToolType::NONE,
			0,
			$definition["blastResistance"]
		), isset($definition["components"]["minecraft:flower_pottable"]) ? [BlockTypeTags::POTTABLE_PLANTS] : []);
		if(count($definition["properties"]) > 0){
			return new BehaviorPermutableBlock(new BlockIdentifier($typeId), $typeInfo, $definition);
		}
		return new BehaviorBlock(new BlockIdentifier($typeId), $typeInfo, $definition);
	}

	public function getIdentifier() : string{
		return $this->definition["identifier"];
	}

	public function addComponent(BlockComponent $component) : void{
		$this->extraComponents[$component->getName()] = $component;
	}

	public function hasComponent(string $name) : bool{
		return isset($this->getComponents()[$name]);
	}

	/**
	 * @return array<string, BlockComponent>
	 */
	public function getComponents() : array{
		$components = [];
		foreach($this->definition["components"] as $name => $value){
			try{
				$component = BlockComponentMapper::map($name, $value);
			}catch(Throwable){
				$component = null;
			}
			if($component !== null){
				$components[$component->getName()] = $component;
			}
		}
		if(!isset($components["minecraft:collision_box"]) && $this->definition["collision"] === null){
			$components["minecraft:collision_box"] = new CollisionBoxComponent(false);
		}
		foreach($this->extraComponents as $name => $component){
			$components[$name] = $component;
		}
		return $components;
	}

	/**
	 * Returns the JSON value of a component of the block, or null when the
	 * block does not have it.
	 */
	protected function componentValue(string $name) : mixed{
		if($this instanceof BehaviorPermutableBlock){
			return $this->getActiveComponent($name);
		}
		return $this->definition["components"][$name] ?? null;
	}

	/**
	 * Returns whether the block has the given block tag, declared by a
	 * "tag:" component.
	 */
	public function hasBlockTag(string $tag) : bool{
		return isset($this->definition["components"]["tag:" . $tag]) || isset($this->definition["components"]["tag:minecraft:" . $tag]);
	}

	/**
	 * Returns the current value of a block state, or null when the block has
	 * no such state.
	 */
	protected function stateValue(string $name) : bool|int|string|null{
		if(!$this instanceof BehaviorPermutableBlock){
			return null;
		}
		return $this->getPropertyValue($name);
	}

	public function canBeReplaced() : bool{
		return $this->componentValue("minecraft:replaceable") !== null;
	}

	public function canBeFlowedInto() : bool{
		$detection = $this->componentValue("minecraft:liquid_detection");
		return $detection !== null && LiquidDetection::canBeFlowedInto($detection);
	}

	public function isTransparent() : bool{
		$conductivity = $this->componentValue("minecraft:redstone_conductivity");
		if($conductivity === null){
			return parent::isTransparent();
		}
		try{
			return !ExtraBlockComponents::redstoneConductivity($conductivity)[0];
		}catch(BehaviorPackException){
			return parent::isTransparent();
		}
	}

	public function getSupportType(int $facing) : SupportType{
		$support = $this->componentValue("minecraft:support");
		if($support !== null){
			try{
				$shape = ExtraBlockComponents::supportShape($support);
			}catch(BehaviorPackException){
				$shape = null;
			}
			if($shape === ExtraBlockComponents::SUPPORT_FENCE){
				return Facing::axis($facing) === Axis::Y ? SupportType::CENTER : SupportType::NONE;
			}
			if($shape === ExtraBlockComponents::SUPPORT_STAIR){
				return $this->getStairSupportType($facing);
			}
		}
		$box = $this->definition["collision"];
		if($box === null || $box[0] > 0.0 || $box[1] > 0.0 || $box[2] > 0.0 || $box[3] < 1.0 || $box[4] < 1.0 || $box[5] < 1.0){
			return SupportType::NONE;
		}
		return SupportType::FULL;
	}

	private function getStairSupportType(int $facing) : SupportType{
		$upsideDown = $this->stateValue("minecraft:vertical_half") === "top";
		if(($facing === Facing::UP && $upsideDown) || ($facing === Facing::DOWN && !$upsideDown)){
			return SupportType::FULL;
		}
		$back = match($this->stateValue("minecraft:cardinal_direction")){
			"north" => Facing::NORTH,
			"south" => Facing::SOUTH,
			"west" => Facing::WEST,
			"east" => Facing::EAST,
			default => null
		};
		return $back !== null && $facing === $back ? SupportType::FULL : SupportType::NONE;
	}

	public function canBePlacedAt(Block $blockReplace, Vector3 $clickVector, int $face, bool $isClickedBlock) : bool{
		if(!parent::canBePlacedAt($blockReplace, $clickVector, $face, $isClickedBlock)){
			return false;
		}
		$filter = $this->componentValue("minecraft:placement_filter");
		$position = $blockReplace->getPosition();
		if($filter === null || !$position->isValid()){
			return true;
		}
		return PlacementFilter::allowsFace($filter, $position->getWorld(), $position->asVector3(), $face);
	}

	/**
	 * @return array{string, bool}
	 */
	private function movement() : array{
		$movable = $this->componentValue("minecraft:movable");
		if($movable === null){
			return [ExtraBlockComponents::MOVEMENT_PUSH_PULL, false];
		}
		try{
			return ExtraBlockComponents::movable($movable);
		}catch(BehaviorPackException){
			return [ExtraBlockComponents::MOVEMENT_PUSH_PULL, false];
		}
	}

	public function canBeMoved() : bool{
		return $this->movement()[0] !== ExtraBlockComponents::MOVEMENT_IMMOVABLE;
	}

	public function breaksWhenMoved() : bool{
		return $this->movement()[0] === ExtraBlockComponents::MOVEMENT_POPPED;
	}

	public function canBePulled() : bool{
		return in_array($this->movement()[0], [ExtraBlockComponents::MOVEMENT_PUSH_PULL, ExtraBlockComponents::MOVEMENT_POPPED], true);
	}

	public function sticksToSameType() : bool{
		return $this->movement()[1];
	}

	/**
	 * Applies the liquid and placement reactions of the block, returns
	 * whether the block was removed.
	 */
	private function reactToNeighbours() : bool{
		if(!$this->position->isValid()){
			return false;
		}
		$detection = $this->componentValue("minecraft:liquid_detection");
		if($detection !== null && LiquidDetection::react($detection, $this)){
			return true;
		}
		$filter = $this->componentValue("minecraft:placement_filter");
		if($filter !== null && !PlacementFilter::isSupported($filter, $this)){
			$this->position->getWorld()->useBreakOn($this->position);
			return true;
		}
		return false;
	}

	public function getLightLevel() : int{
		return $this->definition["lightLevel"];
	}

	public function getLightFilter() : int{
		return $this->definition["lightFilter"];
	}

	public function getFrictionFactor() : float{
		return $this->definition["frictionFactor"];
	}

	public function getFlameEncouragement() : int{
		return $this->definition["flammability"][0] ?? 0;
	}

	public function getFlammability() : int{
		return $this->definition["flammability"][1] ?? 0;
	}

	public function isSolid() : bool{
		return $this->definition["collision"] !== null;
	}

	/**
	 * Receives the custom component hooks of the blocks: the hook name, the
	 * block and the extra event data.
	 *
	 * @var (\Closure(string, Block, array<string, mixed>) : void)|null
	 */
	public static ?\Closure $hookListener = null;

	/** @var array<int, true> */
	private static array $scheduled = [];

	/** @var array<int, true> */
	private static array $pendingReactions = [];

	public function hasCustomComponents() : bool{
		foreach($this->definition["components"] as $name => $value){
			$name = (string) $name;
			if($name === "minecraft:custom_components" || !str_starts_with($name, "minecraft:")){
				return true;
			}
		}
		return false;
	}

	private function callHook(string $hook, array $extra = []) : void{
		if(self::$hookListener !== null && $this->hasCustomComponents()){
			(self::$hookListener)($hook, $this, $extra);
		}
	}

	public function ticksRandomly() : bool{
		return $this->hasCustomComponents();
	}

	public function onRandomTick() : void{
		$this->callHook("onRandomTick");
		$this->scheduleTick(false);
	}

	/**
	 * Schedules the next minecraft:tick update of the block.
	 */
	private function scheduleTick(bool $force) : void{
		$tick = $this->definition["components"]["minecraft:tick"] ?? null;
		if(!is_array($tick) || !$this->position->isValid()){
			return;
		}
		$hash = World::blockHash($this->position->getFloorX(), $this->position->getFloorY(), $this->position->getFloorZ());
		if(!$force && isset(self::$scheduled[$hash])){
			return;
		}
		$range = is_array($tick["interval_range"] ?? null) ? $tick["interval_range"] : [10, 10];
		$min = max(1, (int) ($range[0] ?? 10));
		$max = max($min, (int) ($range[1] ?? $min));
		self::$scheduled[$hash] = true;
		$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, mt_rand($min, $max));
	}

	public function onPostPlace() : void{
		parent::onPostPlace();
		if($this->reactToNeighbours()){
			return;
		}
		$this->scheduleTick(true);
	}

	/**
	 * Schedules the liquid and placement reactions for the next tick, once
	 * every neighbour update handler of the block has run.
	 */
	private function scheduleReaction() : void{
		if(!$this->position->isValid()){
			return;
		}
		if($this->componentValue("minecraft:liquid_detection") === null && $this->componentValue("minecraft:placement_filter") === null){
			return;
		}
		$hash = World::blockHash($this->position->getFloorX(), $this->position->getFloorY(), $this->position->getFloorZ());
		self::$pendingReactions[$hash] = true;
		$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, 1);
	}

	public function onScheduledUpdate() : void{
		$hash = World::blockHash($this->position->getFloorX(), $this->position->getFloorY(), $this->position->getFloorZ());
		if(isset(self::$pendingReactions[$hash])){
			unset(self::$pendingReactions[$hash]);
			if($this->reactToNeighbours() || !isset(self::$scheduled[$hash])){
				return;
			}
		}
		unset(self::$scheduled[$hash]);
		$tick = $this->definition["components"]["minecraft:tick"] ?? null;
		if(!is_array($tick)){
			return;
		}
		$this->callHook("onTick");
		$current = $this->position->getWorld()->getBlock($this->position);
		if(($tick["looping"] ?? true) !== false && $current instanceof BehaviorBlock && $current->getTypeId() === $this->getTypeId()){
			$current->scheduleTick(true);
		}
	}

	/** @var array<int, int> */
	private static array $powerLevels = [];

	/**
	 * Returns the redstone power the block receives from its neighbours.
	 */
	public function getReceivedPower() : int{
		if(!$this->position->isValid() || !\interface_exists(\redstone\block\power\PowerSource::class)){
			return 0;
		}
		$power = 0;
		foreach(\pocketmine\math\Facing::ALL as $side){
			$block = $this->position->getWorld()->getBlock($this->position->getSide($side));
			if($block instanceof \redstone\block\power\PowerSource && $block->canPower(\pocketmine\math\Facing::opposite($side))){
				$power = max($power, $block->getOutputPowerLevel());
			}
		}
		if($power < 15 && \class_exists(\redstone\world\RedstoneWorldManager::class)){
			$world = \redstone\world\RedstoneWorldManager::$any->get($this->position->getWorld());
			foreach(\pocketmine\math\Facing::ALL as $side){
				$neighbour = $this->position->getWorld()->getBlock($this->position->getSide($side));
				$opposite = \pocketmine\math\Facing::opposite($side);
				if($world->isStronglyPowered($neighbour, $opposite, $opposite)){
					$power = 15;
					break;
				}
			}
		}
		return $power;
	}

	public function onNearbyBlockChange() : void{
		parent::onNearbyBlockChange();
		$this->scheduleReaction();
		$consumer = $this->definition["components"]["minecraft:redstone_consumer"] ?? null;
		if($consumer === null || !$this->position->isValid()){
			return;
		}
		$hash = World::blockHash($this->position->getFloorX(), $this->position->getFloorY(), $this->position->getFloorZ());
		$power = $this->getReceivedPower();
		$previous = self::$powerLevels[$hash] ?? 0;
		if($power === $previous){
			return;
		}
		self::$powerLevels[$hash] = $power;
		$minimum = is_array($consumer) && is_int($consumer["min_power"] ?? null) ? $consumer["min_power"] : 0;
		if($power >= $minimum || $previous >= $minimum){
			$this->callHook("onRedstoneUpdate", ["powerLevel" => $power, "previousPowerLevel" => $previous]);
		}
	}

	public function onEntityLand(\pocketmine\entity\Entity $entity) : ?float{
		$this->callHook("onEntityFallOn", ["entity" => $entity, "fallDistance" => $entity->getFallDistance()]);
		return parent::onEntityLand($entity);
	}

	protected function recalculateCollisionBoxes() : array{
		$box = $this->definition["collision"];
		if($box === null){
			return [];
		}
		return [new AxisAlignedBB($box[0], $box[1], $box[2], $box[3], $box[4], $box[5])];
	}
}
