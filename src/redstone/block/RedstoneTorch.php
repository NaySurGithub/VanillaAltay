<?php

declare(strict_types=1);

namespace redstone\block;

use Generator;
use pocketmine\block\RedstoneTorch as VanillaRedstoneTorch;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\player\Player;
use redstone\block\power\PowerSource;
use redstone\block\power\ToggleablePowerSource;
use redstone\block\power\Transmittable;
use redstone\block\power\Waitable;
use redstone\block\utils\HelperUtils;
use redstone\block\utils\RedstoneTorchBlockData;
use redstone\world\RedstoneWorld;
use redstone\world\RedstoneWorldManager;

class RedstoneTorch extends VanillaRedstoneTorch implements ToggleablePowerSource, Transmittable, Waitable{
	use OptimizedBlockTrait;

	public function getPowerLevel() : int{
		return $this->lit ? 15 : 0;
	}

	public function getOutputPowerLevel() : int{
		return $this->getPowerLevel();
	}

	public function canPower(int $side) : bool{
		return $side !== Facing::opposite($this->facing);
	}

	public function canStronglyPower(int $side) : bool{
		return $side === Facing::UP;
	}

	public function switch(bool $state) : void{
		if($state === $this->lit){
			return;
		}

		$this->position->world->setBlockAt($this->position->x, $this->position->y, $this->position->z, $this->setLit($state), false);
		foreach($this->getRelyingBlocks() as $block){
			$block->power($this);
		}
	}

	public function power(?PowerSource $source = null) : void{
		RedstoneWorldManager::$any->get($this->position->world)->scheduleWaitableUpdate($this, RedstoneWorld::redstoneTicks(1));
	}

	private function updateState(bool $manual) : void{
		$state = !RedstoneWorldManager::$any->get($this->position->world)->isStronglyPowered(HelperUtils::getBlockAtSide($this->position, Facing::opposite($this->facing)), $this->facing);
		if($state === $this->lit){
			return;
		}

		if($manual){
			$world = RedstoneWorldManager::$any->get($this->position->world);
			$data = $world->getExtraDataAt($this->position->x, $this->position->y, $this->position->z);
			if(!($data instanceof RedstoneTorchBlockData)){
				$data = new RedstoneTorchBlockData();
				$world->setExtraDataAt($this->position->x, $this->position->y, $this->position->z, $data);
			}
			$tick = $world->getTick();
			if($state){
				$data->count($tick);
			}
			if($data->isBurntOut($tick)){
				$state = false;
			}
		}
		$this->switch($state);
	}

	public function onRedstoneTickReceive() : void{
		$this->updateState(true);
	}

	/**
	 * @return Generator<Transmittable>
	 */
	protected function getRelyingBlocks() : Generator{
		$sides = Facing::HORIZONTAL;
		$sides[] = Facing::DOWN;
		foreach($sides as $side){
			$block = HelperUtils::getBlockAtSide($this->position, $side);
			if($block instanceof Transmittable){
				yield $block;
			}
		}

		yield from $this->getRelyingOnSupportBlocks();
	}

	/**
	 * @return Generator<Transmittable>
	 */
	protected function getRelyingOnSupportBlocks() : Generator{
		$up = HelperUtils::getBlockAtSide($this->position, Facing::UP);
		if($up instanceof Transmittable){
			yield $up;
		}
		foreach(Facing::ALL as $side){
			if($side !== Facing::DOWN){
				$block = HelperUtils::getBlockAtSide($up->position, $side);
				if($block instanceof Transmittable){
					yield $block;
				}
			}
		}
	}

	public function onPostPlace() : void{
		$this->power();
		foreach($this->getRelyingBlocks() as $block){
			$block->power($this);
		}
	}

	public function onScheduledUpdate() : void{
		RedstoneWorldManager::$any->get($this->position->world)->removeExtraDataAt($this->position->x, $this->position->y, $this->position->z);
		$this->updateState(false);
	}

	public function onNearbyBlockChange() : void{
		RedstoneWorldManager::$any->get($this->position->world)->removeExtraDataAt($this->position->x, $this->position->y, $this->position->z);
		$this->updateState(false);
		parent::onNearbyBlockChange();
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		if($this->lit){
			$this->lit = false;
			foreach($this->getRelyingOnSupportBlocks() as $block){
				$block->power($this);
			}
		}

		RedstoneWorldManager::$any->get($this->position->world)->removeExtraDataAt($this->position->x, $this->position->y, $this->position->z);
		return parent::onBreak($item, $player, $returnedItems);
	}
}