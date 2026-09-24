<?php

declare(strict_types=1);

namespace redstone\block;

use pocketmine\block\Block;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\Slime;
use pocketmine\block\tile\Tile;
use pocketmine\block\tile\TileFactory;
use pocketmine\block\Transparent;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\event\block\BlockTeleportEvent;
use pocketmine\item\Item;
use pocketmine\math\Axis;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use redstone\block\power\Movable;
use redstone\block\power\Powerable;
use redstone\block\power\PowerableTrait;
use redstone\block\tile\piston\PistonArm;
use redstone\block\tile\piston\PistonMoveInfo;
use redstone\block\utils\PistonStructureResolver;
use redstone\event\PistonPullBlockEvent;
use redstone\event\PistonPushBlockEvent;
use redstone\vanilla\ExtraVanillaBlocks;
use redstone\world\RedstoneWorld;
use redstone\world\sound\PistonInSound;
use redstone\world\sound\PistonOutSound;
use ReflectionMethod;
use RuntimeException;
use function abs;
use function assert;
use function count;
use function max;
use function min;

class Piston extends Transparent implements Powerable, Movable{
	use OptimizedBlockTrait;
	use PowerableTrait;

	public const SLIME_LAUNCH_MOTION = 0.75;

	public const STATE_CONTRACT_IDLE = 0;
	public const STATE_CONTRACT_BEGIN = 1;
	public const STATE_RETRACT_BEGIN = 2;
	public const STATE_RETRACT_WAITING = 3;
	public const STATE_RETRACT_IDLE = 4;

	protected int $facing = Facing::NORTH;

	/** @var self::STATE_* */
	protected int $state = self::STATE_RETRACT_IDLE;

	protected int $activation_delay = 0;
	protected int $deactivation_delay = 0;
	protected bool $requires_strong_power = false;

	public function __construct(
		BlockIdentifier $idInfo,
		string $name,
		BlockTypeInfo $typeInfo,
		readonly protected bool $sticky
	){
		parent::__construct($idInfo, $name, $typeInfo);
	}

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->facing($this->facing);
	}

	public function readStateFromWorld() : Block{
		$result = parent::readStateFromWorld();
		if($result !== $this){
			return $result;
		}

		$tile = $this->position->getWorld()->getTileAt($this->position->x, $this->position->y, $this->position->z);
		if($tile instanceof PistonArm){
			$this->state = $tile->state;
		}
		return $this;
	}

	public function writeStateToWorld() : void{
		parent::writeStateToWorld();
		$tile = $this->position->getWorld()->getTileAt($this->position->x, $this->position->y, $this->position->z);
		assert($tile instanceof PistonArm);
		$tile->state = $this->state;
		$tile->sticky = $this->sticky;
		$tile->clearSpawnCompoundCache();
		$this->executeState();
	}

	public function isSticky() : bool{
		return $this->sticky;
	}

	public function getFacing() : int{
		return $this->facing;
	}

	public function setFacing(int $facing) : self{
		$this->facing = $facing;
		return $this;
	}

	/**
	 * @return self::STATE_*
	 */
	public function getState() : int{
		return $this->state;
	}

	/**
	 * @param self::STATE_* $state
	 * @return self
	 */
	public function setState(int $state) : self{
		$this->state = $state;
		return $this;
	}

	private function executeState() : void{
		$initial_state = $this->state;
		do{
			$old_state = $this->state;
			switch($this->state){
				case self::STATE_CONTRACT_BEGIN:
					$block = $this->getSide($this->getSideFacing());
					if($block instanceof PistonArmBlock){
						if($block->getFacing() !== $this->facing){
							$this->setState(self::STATE_RETRACT_BEGIN);
						}else{
							$this->setState(self::STATE_CONTRACT_IDLE);
						}
					}elseif($this->pushBlocks()){
						$this->setState(self::STATE_CONTRACT_IDLE);
						$this->position->world->addSound($this->position->add(0.5, 0.5, 0.5), new PistonOutSound());
					}else{
						$this->setState(self::STATE_RETRACT_IDLE);
					}
					break;
				case self::STATE_CONTRACT_IDLE:
					$block = $this->getSide($this->getSideFacing());
					if(!($block instanceof PistonArmBlock) || $block->getFacing() !== $this->facing){
						$block->position->world->setBlockAt($block->position->x, $block->position->y, $block->position->z, ExtraVanillaBlocks::PISTON_ARM_BLOCK()->setFacing($this->facing), false);
					}
					break;
				case self::STATE_RETRACT_BEGIN:
					$block = $this->getSide($this->getSideFacing());
					if($block instanceof PistonArmBlock && $block->getFacing() === $this->facing){
						$this->setState(self::STATE_RETRACT_WAITING);
					}else{
						$this->setState(self::STATE_RETRACT_IDLE);
					}
					break;
				case self::STATE_RETRACT_WAITING:
					$this->position->world->scheduleDelayedBlockUpdate($this->position, RedstoneWorld::redstoneTicks(1));
					break;
				case self::STATE_RETRACT_IDLE:
					$block = $this->getSide($this->getSideFacing());
					if($block instanceof PistonArmBlock && $block->getFacing() === $this->facing){
						$block->position->world->setBlockAt($block->position->x, $block->position->y, $block->position->z, VanillaBlocks::AIR());
					}
					break;
				default:
					throw new RuntimeException("Unexpected state: {$this->state}");
			}
		}while($old_state !== $this->state);
		if($this->state !== $initial_state){
			$this->position->world->setBlockAt($this->position->x, $this->position->y, $this->position->z, $this, false);
		}
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($player !== null){
			if(abs($player->getPosition()->x - $this->position->x) < 2 && abs($player->getPosition()->z - $this->position->z) < 2){
				$y = $player->getEyePos()->y;

				if($y - $this->position->y > 2){
					$this->facing = Facing::UP;
				}elseif($this->position->y - $y > 0){
					$this->facing = Facing::DOWN;
				}else{
					$this->facing = $player->getHorizontalFacing();
				}
			}else{
				$this->facing = $player->getHorizontalFacing();
			}
		}
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		if($this->state !== self::STATE_RETRACT_IDLE){
			$this->setState(self::STATE_RETRACT_IDLE);
			$this->executeState();
		}
		return parent::onBreak($item, $player, $returnedItems);
	}

	private function canBlockBeBroken(Block $block) : bool{
		return $block->isTransparent() && !$block->isSolid();
	}

	private function canBlockBeMoved(Block $block, Vector3 $future_pos) : bool{
		if($this->canBlockBeBroken($block) || $block->canBeFlowedInto() || !$block->getBreakInfo()->isBreakable()){
			return false;
		}
		if($block instanceof Movable ? !$block->canBeMoved() : ($block->position->world->getTileAt($block->position->x, $block->position->y, $block->position->z) !== null)){
			return false;
		}
		($ev = new BlockTeleportEvent($block, $future_pos))->call();
		return !$ev->isCancelled();
	}

	private function createStructureResolver(bool $extending) : PistonStructureResolver{
		return new PistonStructureResolver(
			$this->position->world,
			$this->position->asVector3(),
			$this->getSideFacing(),
			$extending,
			fn(Block $block, Vector3 $future_pos) : bool => !($block instanceof PistonArmBlock) && $this->canBlockBeMoved($block, $future_pos)
		);
	}

	/**
	 * @return list<PistonMoveInfo>
	 */
	private function createMovements(PistonStructureResolver $resolver) : array{
		$direction = $resolver->getPushDirection();
		$movements = [];
		foreach($resolver->getToPush() as $block){
			$pos = $block->getPosition();
			$movements[] = new PistonMoveInfo($block, $pos->world->getTile($pos), $pos->asVector3(), $pos->getSide($direction));
		}
		return $movements;
	}

	/**
	 * @param list<PistonMoveInfo> $movements
	 * @param list<Block> $to_destroy
	 */
	private function moveStructure(array $movements, array $to_destroy) : void{
		$world = $this->position->world;
		foreach($to_destroy as $block){
			$world->useBreakOn($block->getPosition());
		}

		$tile_nbts = [];
		foreach($movements as $index => $movement){
			$tile_nbts[$index] = $world->getTileAt($movement->from->x, $movement->from->y, $movement->from->z)?->saveNBT();
		}
		foreach($movements as $movement){
			$world->setBlockAt($movement->from->x, $movement->from->y, $movement->from->z, VanillaBlocks::AIR(), false);
		}

		foreach($movements as $index => $movement){
			$to = $movement->to;
			$world->setBlockAt($to->x, $to->y, $to->z, $movement->block, false);
			$tile_nbt = $tile_nbts[$index];
			if($tile_nbt !== null){
				$existing_tile = $world->getTileAt($to->x, $to->y, $to->z);
				if($existing_tile !== null){
					$world->removeTile($existing_tile);
				}
				$tile_nbt->setInt(Tile::TAG_X, $to->x);
				$tile_nbt->setInt(Tile::TAG_Y, $to->y);
				$tile_nbt->setInt(Tile::TAG_Z, $to->z);
				$world->addTile(TileFactory::getInstance()->createFromData($world, $tile_nbt));
			}
		}

		foreach($movements as $movement){
			foreach([$movement->from, $movement->to] as $pos){
				$world->updateAllLight($pos->x, $pos->y, $pos->z);
				$world->notifyNeighbourBlockUpdate($pos);
			}
		}
	}

	private function pullBlocks() : bool{
		$arm_pos = $this->position->getSide($this->getSideFacing());
		$arm = $this->position->world->getBlockAt($arm_pos->x, $arm_pos->y, $arm_pos->z);
		if(!($arm instanceof PistonArmBlock) && !$arm->canBeReplaced() && !$arm->canBeFlowedInto()){
			return false;
		}
		$this->position->world->setBlockAt($arm_pos->x, $arm_pos->y, $arm_pos->z, VanillaBlocks::AIR(), false);

		$resolver = $this->createStructureResolver(false);
		$resolved = $resolver->resolve() && count($resolver->getToPush()) > 0;
		$ev = new PistonPullBlockEvent($this, $this->getSide($this->getSideFacing(), 2), $resolved ? $this->createMovements($resolver) : []);
		if(!$resolved){
			$ev->cancel();
		}
		$ev->call();
		if($ev->isCancelled()){
			return false;
		}
		$this->moveStructure($ev->movements, $resolver->getToDestroy());
		return true;
	}

	private function pushBlocks() : bool{
		$resolver = $this->createStructureResolver(true);
		if(!$resolver->resolve()){
			return false;
		}
		$movements = $this->createMovements($resolver);
		$arm_pos = $this->position->getSide($this->getSideFacing());
		$ev = new PistonPushBlockEvent($this, $arm_pos, $movements);
		$ev->call();
		if($ev->isCancelled()){
			return false;
		}
		$this->launchEntitiesOnSlime($movements);
		$this->pushEntities($movements);
		$this->moveStructure($movements, $resolver->getToDestroy());
		$this->position->world->useBreakOn($arm_pos);
		return true;
	}

	/**
	 * @param list<PistonMoveInfo> $movements
	 */
	private function launchEntitiesOnSlime(array $movements) : void{
		$facing = $this->getSideFacing();
		$direction = Vector3::zero()->getSide($facing);
		$lift = Facing::axis($facing) === Axis::Y ? 0 : 0.25;

		$launched = [];
		foreach($movements as $movement){
			if(!($movement->block instanceof Slime)){
				continue;
			}
			$from = $movement->from;
			$bb = AxisAlignedBB::one()
				->offset($from->x, $from->y, $from->z)
				->addCoord($direction->x, $direction->y, $direction->z)
				->addCoord(0, $lift, 0);
			foreach($this->position->world->getNearbyEntities($bb) as $entity){
				if($entity->isFlaggedForDespawn() || $entity->isClosed() || isset($launched[$entity->getId()])){
					continue;
				}
				$launched[$entity->getId()] = true;
				$motion = $entity->getMotion();
				$entity->setMotion(new Vector3(
					self::getSlimeLaunchMotion($motion->x, $direction->x),
					self::getSlimeLaunchMotion($motion->y, $direction->y),
					self::getSlimeLaunchMotion($motion->z, $direction->z)
				));
			}
		}
	}

	private static function getSlimeLaunchMotion(float $current, int $offset) : float{
		$launch = $offset * self::SLIME_LAUNCH_MOTION;
		if($offset > 0){
			return max($current, $launch);
		}
		if($offset < 0){
			return min($current, $launch);
		}
		return $current;
	}

	/**
	 * @param list<PistonMoveInfo> $movements
	 */
	private function pushEntities(array $movements) : void{
		$facing = $this->getSideFacing();
		$arm_pos = $this->position->getSide($facing);
		$targets = [$arm_pos];
		foreach($movements as $movement){
			$targets[] = $movement->to;
		}

		$pushed = [];
		foreach($targets as $target){
			$target_pos = $target->getSide($facing)->add(0.5, 0.5, 0.5);
			foreach($this->position->world->getNearbyEntities(AxisAlignedBB::one()
				->contract(0.0625, 0.0625, 0.0625)
				->offset($target->x, $target->y, $target->z)) as $entity){
				if($entity->isFlaggedForDespawn() || $entity->isClosed() || isset($pushed[$entity->getId()])){
					continue;
				}
				$pushed[$entity->getId()] = true;
				$pos = $entity->getPosition();
				$diff = Vector3::zero()->getSide($facing);
				$diff->x *= abs($target_pos->x - $pos->x);
				$diff->y *= abs($target_pos->y - $pos->y);
				$diff->z *= abs($target_pos->z - $pos->z);
				$_move = new ReflectionMethod($entity, "move");
				$_updateMovement = new ReflectionMethod($entity, "updateMovement");
				$_move->invoke($entity, $diff->x, $diff->y, $diff->z);
				$_updateMovement->invoke($entity);
			}
		}
	}

	public function getSideFacing() : int{
		return $this->facing >= 2 ? Facing::opposite($this->facing) : $this->facing;
	}

	public function isPowered() : bool{
		return $this->state !== self::STATE_RETRACT_IDLE;
	}

	public function acceptsPowerFromSide(int $side) : bool{
		return $side !== $this->getSideFacing();
	}

	protected function onReceivePower(int $power) : void{
		if($power > 0){
			if($this->state !== self::STATE_CONTRACT_BEGIN && $this->state !== self::STATE_CONTRACT_IDLE && $this->state !== self::STATE_RETRACT_BEGIN){
				$next_state = self::STATE_CONTRACT_BEGIN;
			}else{
				$next_state = null;
			}
		}else{
			if($this->state !== self::STATE_RETRACT_BEGIN && /*$this->state !== self::STATE_RETRACT_WAITING && */$this->state !== self::STATE_RETRACT_IDLE && $this->state !== self::STATE_CONTRACT_BEGIN){
				$next_state = self::STATE_RETRACT_BEGIN;
			}else{
				$next_state = null;
			}
		}
		if($next_state !== null){
			$this->position->world->setBlockAt($this->position->x, $this->position->y, $this->position->z, $this->setState($next_state), false);
		}
	}

	public function onScheduledUpdate() : void{
		if($this->state === self::STATE_RETRACT_WAITING){
			if($this->sticky){
				$this->pullBlocks();
			}
			$this->position->world->addSound($this->position->add(0.5, 0.5, 0.5), new PistonInSound());
			$this->position->world->setBlockAt($this->position->x, $this->position->y, $this->position->z, $this->setState(self::STATE_RETRACT_IDLE), false);
		}
	}

	public function canBeMoved() : bool{
		return $this->state === self::STATE_RETRACT_IDLE;
	}
}