<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\movement;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\Navigator;
use pocketmine\block\BaseFire;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Cactus;
use pocketmine\block\Campfire;
use pocketmine\block\Door;
use pocketmine\block\FenceGate;
use pocketmine\block\Lava;
use pocketmine\block\Magma;
use pocketmine\block\NetherPortal;
use pocketmine\block\PowderSnow;
use pocketmine\block\SweetBerryBush;
use pocketmine\block\Water;
use pocketmine\block\WitherRose;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\sound\DoorSound;
use pocketmine\world\World;
use function abs;
use function array_reverse;
use function atan2;
use function ceil;
use function count;
use function floor;
use function fmod;
use function in_array;
use function is_array;
use function max;
use function min;
use function mt_rand;
use function sin;
use function sqrt;
use const INF;
use const M_PI;

/**
 * A navigator that follows a path computed by a bounded A* search over the
 * block grid. Subclasses define which cells can be crossed and how the entity
 * is steered toward the next node.
 */
abstract class PathNavigator extends Navigator{

	public const MAX_NODES = 400;
	public const MAX_RANGE = 48;

	private const REPATH_COOLDOWN = 10;
	private const STUCK_TICKS = 40;
	private const MAX_STUCK_REPATHS = 3;

	/** @var array<int, string> */
	private static array $blockNames = [];

	/** @var list<array{int, int, int}> */
	protected array $path = [];
	protected int $index = 0;
	protected ?Vector3 $pathTarget = null;
	protected int $repathCooldown = 0;
	protected int $stuckTicks = 0;
	protected int $stuckRepaths = 0;
	protected float $bestDistance = INF;
	protected int $jumpCooldown = 0;
	protected int $swayTicks = 0;

	/** @var array<string, array{int, int, int}> */
	protected array $openedDoors = [];

	public function __construct(
		BehaviorEntity $entity,
		protected NavigationOptions $options
	){
		parent::__construct($entity);
	}

	public function getOptions() : NavigationOptions{
		return $this->options;
	}

	public function setOptions(NavigationOptions $options) : void{
		$this->options = $options;
		$this->path = [];
		$this->index = 0;
		$this->pathTarget = null;
	}

	/**
	 * @return list<array{int, int, int}>
	 */
	public function getPath() : array{
		return $this->path;
	}

	public function moveTo(Vector3 $destination, float $speedMultiplier = 1.0, float $reachDistance = 0.5) : bool{
		$this->speedMultiplier = $speedMultiplier;
		$this->reachDistance = $reachDistance;
		if($this->destination !== null && $this->pathTarget !== null && count($this->path) > 0 && $this->pathTarget->distanceSquared($destination) < 1.0){
			$this->destination = $destination;
			return true;
		}
		$this->destination = $destination;
		$this->stuckRepaths = 0;
		if(!$this->computePath()){
			$this->destination = null;
			return false;
		}
		return true;
	}

	public function stop() : void{
		parent::stop();
		$this->path = [];
		$this->index = 0;
		$this->pathTarget = null;
		$this->stuckTicks = 0;
		$this->stuckRepaths = 0;
	}

	/**
	 * Called when the navigator is replaced, to leave the world as it was.
	 */
	public function release() : void{
		$this->stop();
		foreach($this->openedDoors as $key => $position){
			$this->setDoorOpen($position[0], $position[1], $position[2], false);
			unset($this->openedDoors[$key]);
		}
	}

	protected function computePath() : bool{
		if($this->destination === null){
			return false;
		}
		$start = $this->startNode();
		$goal = $this->goalNode($this->destination);
		$result = $this->search($start, $goal);
		$this->pathTarget = $this->destination->asVector3();
		$this->stuckTicks = 0;
		$this->bestDistance = INF;
		$this->repathCooldown = self::REPATH_COOLDOWN;
		if(count($result) === 0){
			$this->path = [];
			$this->index = 0;
			return false;
		}
		$this->path = $result;
		$this->index = count($result) > 1 ? 1 : 0;
		return true;
	}

	/**
	 * @return array{int, int, int}
	 */
	protected function startNode() : array{
		$location = $this->entity->getLocation();
		return [(int) floor($location->x), (int) floor($location->y + 0.5), (int) floor($location->z)];
	}

	/**
	 * @return array{int, int, int}
	 */
	protected function goalNode(Vector3 $destination) : array{
		return [(int) floor($destination->x), (int) floor($destination->y + 0.5), (int) floor($destination->z)];
	}

	/**
	 * Runs the A* search. Returns the path to the goal, or to the explored
	 * node closest to it when the goal cannot be reached within the budget.
	 *
	 * @param array{int, int, int} $start
	 * @param array{int, int, int} $goal
	 * @return list<array{int, int, int}>
	 */
	protected function search(array $start, array $goal) : array{
		$open = new \SplPriorityQueue();
		$open->setExtractFlags(\SplPriorityQueue::EXTR_DATA);
		$startKey = self::key($start[0], $start[1], $start[2]);
		$costs = [$startKey => 0.0];
		$parents = [$startKey => null];
		$nodes = [$startKey => $start];
		$closed = [];
		$bestKey = $startKey;
		$bestHeuristic = self::heuristic($start, $goal);
		$open->insert($startKey, -$bestHeuristic);
		$expanded = 0;
		$reached = false;
		while(!$open->isEmpty() && $expanded < self::MAX_NODES){
			$key = $open->extract();
			if(isset($closed[$key])){
				continue;
			}
			$closed[$key] = true;
			$expanded++;
			$node = $nodes[$key];
			$heuristic = self::heuristic($node, $goal);
			if($heuristic < $bestHeuristic){
				$bestHeuristic = $heuristic;
				$bestKey = $key;
			}
			if($this->isGoal($node, $goal)){
				$bestKey = $key;
				$reached = true;
				break;
			}
			foreach($this->neighbors($node) as [$next, $cost]){
				if(abs($next[0] - $start[0]) > self::MAX_RANGE || abs($next[1] - $start[1]) > self::MAX_RANGE || abs($next[2] - $start[2]) > self::MAX_RANGE){
					continue;
				}
				$nextKey = self::key($next[0], $next[1], $next[2]);
				if(isset($closed[$nextKey])){
					continue;
				}
				$total = $costs[$key] + $cost;
				if(isset($costs[$nextKey]) && $costs[$nextKey] <= $total){
					continue;
				}
				$costs[$nextKey] = $total;
				$parents[$nextKey] = $key;
				$nodes[$nextKey] = $next;
				$open->insert($nextKey, -($total + self::heuristic($next, $goal)));
			}
		}
		if($bestKey === $startKey && !$reached){
			return [];
		}
		$result = [];
		$key = $bestKey;
		while($key !== null){
			$result[] = $nodes[$key];
			$key = $parents[$key];
		}
		return array_reverse($result);
	}

	/**
	 * @param array{int, int, int} $node
	 * @param array{int, int, int} $goal
	 */
	protected function isGoal(array $node, array $goal) : bool{
		return $node[0] === $goal[0] && $node[2] === $goal[2] && abs($node[1] - $goal[1]) <= 1;
	}

	/**
	 * Returns the reachable neighbours of a node with the cost to reach them.
	 *
	 * @param array{int, int, int} $node
	 * @return list<array{array{int, int, int}, float}>
	 */
	abstract protected function neighbors(array $node) : array;

	/**
	 * Moves the entity toward a point for one tick.
	 */
	abstract protected function steer(Vector3 $point, bool $final) : void;

	/**
	 * Called every tick while there is no destination.
	 */
	protected function idle() : void{
	}

	/**
	 * Returns whether a node of the path can still be crossed.
	 *
	 * @param array{int, int, int} $node
	 */
	protected function isNodeValid(array $node) : bool{
		return $this->isPassable($node[0], $node[1], $node[2]);
	}

	/**
	 * Returns the point the entity aims at to reach a node.
	 *
	 * @param array{int, int, int} $node
	 */
	protected function nodePoint(array $node) : Vector3{
		return new Vector3($node[0] + 0.5, $node[1], $node[2] + 0.5);
	}

	/**
	 * @param array{int, int, int} $node
	 */
	protected function hasReachedNode(array $node, Vector3 $point) : bool{
		$location = $this->entity->getLocation();
		$radius = max(0.35, min(0.8, $this->entity->getSize()->getWidth() / 2));
		return ($point->x - $location->x) ** 2 + ($point->z - $location->z) ** 2 <= $radius * $radius && abs($point->y - $location->y) < 1.0;
	}

	protected function hasReachedDestination(Vector3 $destination) : bool{
		$location = $this->entity->getLocation();
		$dx = $destination->x - $location->x;
		$dz = $destination->z - $location->z;
		return $dx * $dx + $dz * $dz <= $this->reachDistance * $this->reachDistance && abs($destination->y - $location->y) < 1.5;
	}

	public function tick() : void{
		$this->tickDoors();
		if($this->jumpCooldown > 0 && !$this->isHopping()){
			$this->jumpCooldown = 0;
		}
		if($this->destination === null){
			$this->idle();
			return;
		}
		if($this->hasReachedDestination($this->destination)){
			$this->stop();
			$this->idle();
			return;
		}
		if($this->repathCooldown > 0){
			$this->repathCooldown--;
		}
		if($this->pathTarget !== null && $this->repathCooldown <= 0 && $this->pathTarget->distanceSquared($this->destination) > 2.25){
			if(!$this->computePath()){
				$this->stop();
				return;
			}
		}
		while($this->index < count($this->path)){
			$node = $this->path[$this->index];
			if(!$this->hasReachedNode($node, $this->nodePoint($node))){
				break;
			}
			$this->index++;
			$this->stuckTicks = 0;
			$this->bestDistance = INF;
		}
		if($this->index < count($this->path) && !$this->isNodeValid($this->path[$this->index]) && $this->repathCooldown <= 0){
			if(!$this->computePath()){
				$this->stop();
				return;
			}
		}
		$final = $this->index >= count($this->path);
		$point = $final ? $this->destination : $this->nodePoint($this->path[$this->index]);
		if(!$final){
			$this->openDoorsAhead();
		}
		$distance = $this->entity->getLocation()->distanceSquared($point);
		if($distance < $this->bestDistance - 0.01){
			$this->bestDistance = $distance;
			$this->stuckTicks = 0;
		}elseif(++$this->stuckTicks >= self::STUCK_TICKS){
			if(++$this->stuckRepaths > self::MAX_STUCK_REPATHS || !$this->computePath()){
				$this->stop();
				return;
			}
			return;
		}
		$this->steer($point, $final);
	}

	protected function getBaseSpeed() : float{
		if($this->entity->isInWater()){
			$underwater = $this->entity->getData("underwater_speed");
			if($underwater !== null){
				return (float) $underwater;
			}
		}
		return $this->entity->getMovementSpeed();
	}

	protected function getFlyingSpeed() : float{
		$flying = $this->entity->getData("flying_speed");
		return $flying !== null ? (float) $flying : $this->entity->getMovementSpeed();
	}

	/**
	 * Turns the entity toward a direction, limited by the maximum turn of its
	 * movement component.
	 */
	protected function face(float $dx, float $dy, float $dz, bool $withPitch) : void{
		$location = $this->entity->getLocation();
		$horizontal = sqrt($dx * $dx + $dz * $dz);
		if($horizontal < 0.0001 && abs($dy) < 0.0001){
			return;
		}
		$yaw = $location->yaw;
		if($horizontal >= 0.0001){
			$wanted = atan2($dz, $dx) / M_PI * 180 - 90;
			$diff = fmod($wanted - $location->yaw + 540.0, 360.0) - 180.0;
			$maxTurn = (float) $this->entity->getData("movement_max_turn", 0.0);
			if($maxTurn > 0){
				$diff = max(-$maxTurn, min($maxTurn, $diff));
			}
			$yaw = fmod($location->yaw + $diff + 360.0, 360.0);
		}
		$pitch = $withPitch ? -atan2($dy, $horizontal) / M_PI * 180 : $location->pitch;
		$this->entity->setRotation($yaw, $pitch);
	}

	/**
	 * Adds the side to side motion of "minecraft:movement.sway".
	 */
	protected function applySway(Vector3 $velocity) : Vector3{
		$amplitude = (float) $this->entity->getData("movement_sway_amplitude", 0.0);
		if($amplitude <= 0){
			return $velocity;
		}
		$frequency = (float) $this->entity->getData("movement_sway_frequency", 0.5);
		$this->swayTicks++;
		$offset = sin($this->swayTicks / 20 * $frequency * 2 * M_PI) * $amplitude;
		return $velocity->add(-$velocity->z * $offset, 0, $velocity->x * $offset);
	}

	protected function isHopping() : bool{
		$mode = $this->entity->getData("movement_mode");
		return $mode === "jump" || $mode === "skip";
	}

	/**
	 * Applies a horizontal velocity, hopping between moves for the jump and
	 * skip movements.
	 */
	protected function applyHorizontal(float $vx, float $vz) : void{
		$motion = $this->entity->getMotion();
		if(!$this->isHopping()){
			$this->entity->setMotion(new Vector3($vx, $motion->y, $vz));
			return;
		}
		if(!$this->entity->isOnGround()){
			$this->entity->setMotion(new Vector3($vx, $motion->y, $vz));
			return;
		}
		if($this->jumpCooldown > 0){
			$this->jumpCooldown--;
			$this->entity->setMotion(new Vector3(0, $motion->y, 0));
			return;
		}
		$this->entity->jump();
		$motion = $this->entity->getMotion();
		$this->entity->setMotion(new Vector3($vx, $motion->y, $vz));
		$delay = $this->entity->getData("movement_jump_delay");
		if(is_array($delay) && count($delay) === 2){
			$min = (int) $delay[0];
			$max = max($min, (int) $delay[1]);
			$this->jumpCooldown = mt_rand($min, $max);
		}
	}

	protected function world() : World{
		return $this->entity->getWorld();
	}

	protected function block(int $x, int $y, int $z) : Block{
		return $this->world()->getBlockAt($x, $y, $z);
	}

	protected function entityHeightCells() : int{
		return max(1, (int) ceil($this->entity->getSize()->getHeight() - 0.01));
	}

	public static function blockName(Block $block) : string{
		$stateId = $block->getStateId();
		if(isset(self::$blockNames[$stateId])){
			return self::$blockNames[$stateId];
		}
		try{
			$name = GlobalBlockStateHandlers::getSerializer()->serializeBlock($block)->getName();
		}catch(\Throwable){
			$name = "";
		}
		return self::$blockNames[$stateId] = $name;
	}

	public static function isHarmful(Block $block) : bool{
		return $block instanceof BaseFire || $block instanceof Lava || $block instanceof Magma || $block instanceof Cactus ||
			$block instanceof SweetBerryBush || $block instanceof Campfire || $block instanceof WitherRose || $block instanceof PowderSnow;
	}

	protected function isAvoided(Block $block) : bool{
		if($block instanceof Lava && !$this->options->canWalkInLava){
			return true;
		}
		if($this->options->avoidDamageBlocks && self::isHarmful($block)){
			return true;
		}
		if($this->options->avoidPortals && $block instanceof NetherPortal){
			return true;
		}
		return count($this->options->blocksToAvoid) > 0 && in_array(self::blockName($block), $this->options->blocksToAvoid, true);
	}

	protected function canCrossDoor(Block $block) : bool{
		if(!$this->options->canPassDoors){
			return false;
		}
		if($block instanceof FenceGate){
			return $block->isOpen();
		}
		if(!$block instanceof Door){
			return false;
		}
		if($block->isOpen()){
			return true;
		}
		if($block->getTypeId() === BlockTypeIds::IRON_DOOR){
			return $this->options->canOpenIronDoors;
		}
		return $this->options->canOpenDoors;
	}

	/**
	 * Returns whether a block stops the entity from standing in its cell.
	 */
	protected function isObstacle(Block $block) : bool{
		if($block instanceof Door || $block instanceof FenceGate){
			return !$this->canCrossDoor($block);
		}
		$y = $block->getPosition()->y;
		foreach($block->getCollisionBoxes() as $box){
			if($box->maxY > $y + 0.25){
				return true;
			}
		}
		return false;
	}

	protected function isCellOpen(int $x, int $y, int $z) : bool{
		$block = $this->block($x, $y, $z);
		return !$this->isObstacle($block) && !$this->isAvoided($block);
	}

	/**
	 * Returns whether the entity fits in the column starting at a cell.
	 */
	protected function isPassable(int $x, int $y, int $z) : bool{
		$height = $this->entityHeightCells();
		for($i = 0; $i < $height; $i++){
			if(!$this->isCellOpen($x, $y + $i, $z)){
				return false;
			}
		}
		return true;
	}

	/**
	 * Returns the height the entity stands at in a cell, or null when nothing
	 * holds it there.
	 */
	protected function supportHeight(int $x, int $y, int $z) : ?float{
		$top = null;
		foreach($this->block($x, $y, $z)->getCollisionBoxes() as $box){
			if($box->maxY <= $y + 0.25){
				$top = max($top ?? $box->maxY, $box->maxY);
			}
		}
		if($top !== null){
			return $top;
		}
		$below = $this->block($x, $y - 1, $z);
		foreach($below->getCollisionBoxes() as $box){
			if($box->maxY > $y + 0.01){
				return null;
			}
			$top = max($top ?? $box->maxY, $box->maxY);
		}
		if($top !== null && $top >= $y - 0.5){
			return $top;
		}
		if($below instanceof Water && ($this->options->canPathOverWater || $this->options->canFloat)){
			return (float) $y;
		}
		if($below instanceof Lava && $this->options->canPathOverLava){
			return (float) $y;
		}
		return null;
	}

	protected function isWater(int $x, int $y, int $z) : bool{
		return $this->block($x, $y, $z) instanceof Water;
	}

	protected function isDaytime() : bool{
		$time = $this->world()->getTimeOfDay() % World::TIME_FULL;
		return $time < World::TIME_SUNSET + 300 || $time > World::TIME_SUNRISE;
	}

	protected function isInSun(int $x, int $y, int $z) : bool{
		return $this->isDaytime() && $this->world()->getRealBlockSkyLightAt($x, $y, $z) >= 15;
	}

	/**
	 * Returns the extra cost of standing in a cell.
	 */
	protected function cellPenalty(int $x, int $y, int $z) : float{
		$penalty = 0.0;
		$block = $this->block($x, $y, $z);
		if($block instanceof Water){
			$penalty += $this->options->canSwim ? 0.0 : 2.0;
		}
		if(self::isHarmful($block) || self::isHarmful($this->block($x, $y - 1, $z))){
			$penalty += 8.0;
		}
		if($this->options->avoidSun && $this->isInSun($x, $y, $z)){
			$penalty += 8.0;
		}
		if($block instanceof Door && !$block->isOpen()){
			$penalty += 1.0;
		}
		return $penalty;
	}

	private function openDoorsAhead() : void{
		if(!$this->options->canOpenDoors && !$this->options->canOpenIronDoors){
			return;
		}
		$end = min(count($this->path), $this->index + 2);
		for($i = $this->index; $i < $end; $i++){
			[$x, $y, $z] = $this->path[$i];
			for($dy = 0; $dy < 2; $dy++){
				$block = $this->block($x, $y + $dy, $z);
				if($block instanceof Door && !$block->isOpen() && $this->canCrossDoor($block)){
					$bottom = $block->isTop() ? $y + $dy - 1 : $y + $dy;
					$this->setDoorOpen($x, $bottom, $z, true);
					$this->openedDoors[self::key($x, $bottom, $z)] = [$x, $bottom, $z];
					break;
				}
			}
		}
	}

	private function tickDoors() : void{
		if(count($this->openedDoors) === 0){
			return;
		}
		$location = $this->entity->getLocation();
		foreach($this->openedDoors as $key => $position){
			if(($position[0] + 0.5 - $location->x) ** 2 + ($position[2] + 0.5 - $location->z) ** 2 + ($position[1] - $location->y) ** 2 > 6.25){
				$this->setDoorOpen($position[0], $position[1], $position[2], false);
				unset($this->openedDoors[$key]);
			}
		}
	}

	private function setDoorOpen(int $x, int $y, int $z, bool $open) : void{
		$world = $this->world();
		$block = $world->getBlockAt($x, $y, $z);
		if(!$block instanceof Door || $block->isOpen() === $open){
			return;
		}
		$block->setOpen($open);
		$world->setBlock($block->getPosition(), $block);
		$other = $block->getSide($block->isTop() ? Facing::DOWN : Facing::UP);
		if($other instanceof Door && $other->hasSameTypeId($block)){
			$other->setOpen($open);
			$world->setBlock($other->getPosition(), $other);
		}
		$world->addSound($block->getPosition(), new DoorSound());
	}

	public static function key(int $x, int $y, int $z) : string{
		return $x . ":" . $y . ":" . $z;
	}

	/**
	 * @param array{int, int, int} $a
	 * @param array{int, int, int} $b
	 */
	protected static function heuristic(array $a, array $b) : float{
		return sqrt(($a[0] - $b[0]) ** 2 + ($a[1] - $b[1]) ** 2 + ($a[2] - $b[2]) ** 2);
	}
}
