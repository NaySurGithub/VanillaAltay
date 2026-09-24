<?php

declare(strict_types=1);

namespace redstone\block;

use Generator;
use pocketmine\block\Block;
use pocketmine\block\Opaque;
use pocketmine\block\utils\PoweredByRedstoneTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use redstone\block\power\PowerSource;
use redstone\block\power\Transmittable;
use redstone\block\power\Waitable;
use redstone\block\utils\HelperUtils;
use redstone\world\RedstoneWorld;
use redstone\world\RedstoneWorldManager;
use function abs;

class Observer extends Opaque implements PowerSource, Waitable{
	use OptimizedBlockTrait;
	use PoweredByRedstoneTrait;

	protected int $facing = Facing::DOWN;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->facing($this->facing);
		$w->bool($this->powered);
	}

	public function getFacing() : int{
		return $this->facing;
	}

	public function setFacing(int $facing) : self{
		$this->facing = $facing;
		return $this;
	}

	public function getOutputSide() : int{
		return Facing::opposite($this->facing);
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($player !== null){
			if(abs($player->getPosition()->x - $this->position->x) < 2 && abs($player->getPosition()->z - $this->position->z) < 2){
				$y = $player->getEyePos()->y;

				if($y - $this->position->y > 2){
					$this->facing = Facing::DOWN;
				}elseif($this->position->y - $y > 0){
					$this->facing = Facing::UP;
				}else{
					$this->facing = $player->getHorizontalFacing();
				}
			}else{
				$this->facing = $player->getHorizontalFacing();
			}
		}

		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function getPowerLevel() : int{
		return $this->powered ? 15 : 0;
	}

	public function getOutputPowerLevel() : int{
		return $this->getPowerLevel();
	}

	public function canPower(int $side) : bool{
		return $side === $this->getOutputSide();
	}

	public function canStronglyPower(int $side) : bool{
		return $side === $this->getOutputSide();
	}

	public function onObservedBlockChange() : void{
		RedstoneWorldManager::$any->get($this->position->world)->scheduleWaitableUpdate($this, RedstoneWorld::redstoneTicks(1));
	}

	public function onRedstoneTickReceive() : void{
		$this->powered = !$this->powered;
		$this->position->world->setBlockAt($this->position->x, $this->position->y, $this->position->z, $this, false);
		foreach($this->getRelyingBlocks() as $block){
			$block->power($this);
		}
		if($this->powered){
			RedstoneWorldManager::$any->get($this->position->world)->scheduleWaitableUpdate($this, RedstoneWorld::redstoneTicks(1));
		}
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		if($this->powered){
			$this->powered = false;
			foreach($this->getRelyingBlocks() as $block){
				$block->power($this);
			}
		}

		return parent::onBreak($item, $player, $returnedItems);
	}

	/**
	 * @return Generator<Transmittable>
	 */
	protected function getRelyingBlocks() : Generator{
		$output_side = $this->getOutputSide();
		$output = HelperUtils::getBlockAtSide($this->position, $output_side);
		if($output instanceof Transmittable){
			yield $output;
		}
		$skip_side = Facing::opposite($output_side);
		foreach(Facing::ALL as $side){
			if($side !== $skip_side){
				$block = HelperUtils::getBlockAtSide($output->position, $side);
				if($block instanceof Transmittable){
					yield $block;
				}
			}
		}
	}
}
