<?php

declare(strict_types=1);

namespace redstone\block\utils;

use Closure;
use pocketmine\block\Block;
use pocketmine\block\GlazedTerracotta;
use pocketmine\block\HoneyBlock;
use pocketmine\block\Slime;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function array_merge;
use function array_search;
use function array_slice;
use function count;

final class PistonStructureResolver{

	public const PUSH_LIMIT = 12;

	/** @var list<Block> */
	private array $to_push = [];

	/** @var list<Block> */
	private array $to_destroy = [];

	/** @var list<int> */
	private array $to_push_hashes = [];

	private int $push_direction;
	private Vector3 $start_pos;

	/**
	 * @param Closure(Block, Vector3) : bool $can_move
	 */
	public function __construct(
		readonly private World $world,
		readonly private Vector3 $piston_pos,
		int $piston_direction,
		readonly private bool $extending,
		readonly private Closure $can_move
	){
		if($extending){
			$this->push_direction = $piston_direction;
			$this->start_pos = $piston_pos->getSide($piston_direction);
		}else{
			$this->push_direction = Facing::opposite($piston_direction);
			$this->start_pos = $piston_pos->getSide($piston_direction, 2);
		}
	}

	public static function isSticky(Block $block) : bool{
		return $block instanceof Slime || $block instanceof HoneyBlock;
	}

	private static function sticksToPiston(Block $block) : bool{
		return !($block instanceof GlazedTerracotta);
	}

	private static function canStickTogether(Block $sticky, Block $adjacent) : bool{
		if(!self::isSticky($sticky) || !self::sticksToPiston($adjacent)){
			return false;
		}
		if(self::isSticky($adjacent)){
			return $sticky->getTypeId() === $adjacent->getTypeId();
		}
		return true;
	}

	private static function isEmpty(Block $block) : bool{
		return $block->canBeReplaced();
	}

	private static function breaksWhenMoved(Block $block) : bool{
		return $block->canBeFlowedInto();
	}

	private function canPush(Block $block) : bool{
		if(!$this->extending && $block instanceof GlazedTerracotta){
			return false;
		}
		return ($this->can_move)($block, $block->getPosition()->getSide($this->push_direction));
	}

	private function getBlock(Vector3 $pos) : Block{
		return $this->world->getBlockAt((int) $pos->x, (int) $pos->y, (int) $pos->z);
	}

	private function isPiston(Vector3 $pos) : bool{
		return $pos->equals($this->piston_pos);
	}

	private function indexOf(Vector3 $pos) : int{
		$index = array_search(World::blockHash((int) $pos->x, (int) $pos->y, (int) $pos->z), $this->to_push_hashes, true);
		return $index === false ? -1 : $index;
	}

	private function push(Block $block) : void{
		$pos = $block->getPosition();
		$this->to_push[] = $block;
		$this->to_push_hashes[] = World::blockHash((int) $pos->x, (int) $pos->y, (int) $pos->z);
	}

	public function resolve() : bool{
		$this->to_push = [];
		$this->to_push_hashes = [];
		$this->to_destroy = [];

		$start = $this->getBlock($this->start_pos);
		if(self::isEmpty($start)){
			return true;
		}
		if(self::breaksWhenMoved($start)){
			if($this->extending){
				$this->to_destroy[] = $start;
			}
			return true;
		}
		if(!$this->canPush($start)){
			return false;
		}
		if(!$this->addBlockLine($this->start_pos)){
			return false;
		}
		for($i = 0; $i < count($this->to_push); $i++){
			$block = $this->to_push[$i];
			if(self::isSticky($block) && !$this->addBranchingBlocks($block)){
				return false;
			}
		}
		return true;
	}

	private function addBlockLine(Vector3 $origin) : bool{
		$block = $this->getBlock($origin);
		if(self::isEmpty($block) || self::breaksWhenMoved($block)){
			return true;
		}
		if(!$this->canPush($block) || $this->isPiston($origin) || $this->indexOf($origin) > -1){
			return true;
		}

		$backward = Facing::opposite($this->push_direction);
		$count = 1;
		if($count + count($this->to_push) > self::PUSH_LIMIT){
			return false;
		}
		while(self::isSticky($block)){
			$pos = $origin->getSide($backward, $count);
			$previous = $block;
			$block = $this->getBlock($pos);
			if(self::isEmpty($block) || self::breaksWhenMoved($block) || !self::canStickTogether($previous, $block) || !$this->canPush($block) || $this->isPiston($pos)){
				break;
			}
			$count++;
			if($count + count($this->to_push) > self::PUSH_LIMIT){
				return false;
			}
		}

		$added = 0;
		for($k = $count - 1; $k >= 0; $k--){
			$this->push($this->getBlock($origin->getSide($backward, $k)));
			$added++;
		}

		$step = 1;
		while(true){
			$pos = $origin->getSide($this->push_direction, $step);
			$index = $this->indexOf($pos);
			if($index > -1){
				$this->reorderListAtCollision($added, $index);
				for($i = 0; $i <= $index + $added; $i++){
					$block = $this->to_push[$i];
					if(self::isSticky($block) && !$this->addBranchingBlocks($block)){
						return false;
					}
				}
				return true;
			}

			$block = $this->getBlock($pos);
			if(self::isEmpty($block)){
				return true;
			}
			if($this->isPiston($pos)){
				return false;
			}
			if(self::breaksWhenMoved($block)){
				$this->to_destroy[] = $block;
				return true;
			}
			if(!$this->canPush($block)){
				return false;
			}
			if(count($this->to_push) >= self::PUSH_LIMIT){
				return false;
			}
			$this->push($block);
			$added++;
			$step++;
		}
	}

	private function reorderListAtCollision(int $count, int $index) : void{
		$size = count($this->to_push);
		$this->to_push = array_merge(
			array_slice($this->to_push, 0, $index),
			array_slice($this->to_push, $size - $count, $count),
			array_slice($this->to_push, $index, $size - $count - $index)
		);
		$this->to_push_hashes = array_merge(
			array_slice($this->to_push_hashes, 0, $index),
			array_slice($this->to_push_hashes, $size - $count, $count),
			array_slice($this->to_push_hashes, $index, $size - $count - $index)
		);
	}

	private function addBranchingBlocks(Block $sticky) : bool{
		$push_axis = Facing::axis($this->push_direction);
		$pos = $sticky->getPosition();
		foreach(Facing::ALL as $side){
			if(Facing::axis($side) === $push_axis){
				continue;
			}
			$neighbour_pos = $pos->getSide($side);
			$neighbour = $this->getBlock($neighbour_pos);
			if(self::canStickTogether($sticky, $neighbour) && !$this->addBlockLine($neighbour_pos)){
				return false;
			}
		}
		return true;
	}

	/**
	 * @return list<Block>
	 */
	public function getToPush() : array{
		return $this->to_push;
	}

	/**
	 * @return list<Block>
	 */
	public function getToDestroy() : array{
		return $this->to_destroy;
	}

	public function getPushDirection() : int{
		return $this->push_direction;
	}
}
