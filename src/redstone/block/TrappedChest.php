<?php

declare(strict_types=1);

namespace redstone\block;

use Generator;
use pocketmine\block\tile\Chest as ChestTile;
use pocketmine\block\TrappedChest as VanillaTrappedChest;
use pocketmine\math\Facing;
use redstone\block\power\PowerSource;
use redstone\block\power\Transmittable;
use redstone\block\power\Waitable;
use redstone\block\utils\HelperUtils;
use redstone\world\RedstoneWorldManager;
use function count;
use function min;

class TrappedChest extends VanillaTrappedChest implements PowerSource, Waitable{
	use OptimizedBlockTrait;

	public function getPowerLevel() : int{
		$tile = $this->position->world->getTileAt($this->position->x, $this->position->y, $this->position->z);
		if(!($tile instanceof ChestTile)){
			return 0;
		}
		return min(15, count($tile->getInventory()->getViewers()));
	}

	public function getOutputPowerLevel() : int{
		return $this->getPowerLevel();
	}

	public function canPower(int $side) : bool{
		return true;
	}

	public function canStronglyPower(int $side) : bool{
		return $side === Facing::DOWN;
	}

	public function onViewersChange() : void{
		RedstoneWorldManager::$any->get($this->position->world)->scheduleWaitableUpdate($this, 1);
	}

	public function onRedstoneTickReceive() : void{
		foreach($this->getRelyingBlocks() as $block){
			$block->power($this);
		}
	}

	/**
	 * @return Generator<Transmittable>
	 */
	protected function getRelyingBlocks() : Generator{
		foreach(Facing::ALL as $side){
			$block = HelperUtils::getBlockAtSide($this->position, $side);
			if($block instanceof Transmittable){
				yield $block;
			}
		}

		$below = HelperUtils::getBlockAtSide($this->position, Facing::DOWN);
		foreach(Facing::ALL as $side){
			if($side !== Facing::UP){
				$block = HelperUtils::getBlockAtSide($below->position, $side);
				if($block instanceof Transmittable){
					yield $block;
				}
			}
		}
	}
}
