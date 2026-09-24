<?php

declare(strict_types=1);

namespace redstone\block;

use Generator;
use pocketmine\block\Lever as VanillaLever;
use pocketmine\block\utils\LeverFacing;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\RedstonePowerOffSound;
use pocketmine\world\sound\RedstonePowerOnSound;
use redstone\block\power\PowerSource;
use redstone\block\power\Transmittable;
use redstone\block\utils\HelperUtils;

class Lever extends VanillaLever implements PowerSource{
	use OptimizedBlockTrait;

	private ?int $supporting_side = null;

	public function getPowerLevel() : int{
		return $this->activated ? 15 : 0;
	}

	public function getOutputPowerLevel() : int{
		return $this->getPowerLevel();
	}

	public function canPower(int $side) : bool{
		return true;
	}

	/**
	 * @return Generator<Transmittable>
	 */
	protected function getRelyingOnSupportBlocks() : Generator{
		$supporting_side = $this->getSupportingSide();
		$supporting = HelperUtils::getBlockAtSide($this->position, $supporting_side);
		$skip_side = Facing::opposite($supporting_side);
		foreach(Facing::ALL as $side){
			if($side !== $skip_side){
				$block = HelperUtils::getBlockAtSide($supporting->position, $side);
				if($block instanceof Transmittable){
					yield $block;
				}
			}
		}
	}

	/**
	 * @return Generator<Transmittable>
	 */
	protected function getRelyingBlocks() : Generator{
		yield from $this->getRelyingOnSupportBlocks();
		$skip_side = $this->getSupportingSide();
		foreach(Facing::HORIZONTAL as $side){
			if($side !== $skip_side){
				$block = HelperUtils::getBlockAtSide($this->position, $side);
				if($block instanceof Transmittable){
					yield $block;
				}
			}
		}
	}

	public function setFacing(LeverFacing $facing) : VanillaLever{
		$this->supporting_side = null;
		return parent::setFacing($facing);
	}

	private function recalculateSupportingSide() : int{
		if($this->facing === LeverFacing::DOWN_AXIS_X || $this->facing === LeverFacing::DOWN_AXIS_Z){
			return Facing::UP;
		}

		if($this->facing === LeverFacing::UP_AXIS_X || $this->facing === LeverFacing::UP_AXIS_Z){
			return Facing::DOWN;
		}

		return Facing::opposite($this->facing->getFacing());
	}

	public function getSupportingSide() : int{
		return $this->supporting_side ??= $this->recalculateSupportingSide();
	}

	public function canStronglyPower(int $side) : bool{
		return $side === $this->getSupportingSide();
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		if($this->activated){
			$this->activated = false;
			foreach($this->getRelyingOnSupportBlocks() as $block){
				$block->power($this);
			}
		}

		return parent::onBreak($item, $player, $returnedItems);
	}

	private function onInteractParent() : bool{
		$this->activated = !$this->activated;
		$world = $this->position->getWorld();
		$world->setBlock($this->position, $this, false);
		$world->addSound(
			$this->position->add(0.5, 0.5, 0.5),
			$this->activated ? new RedstonePowerOnSound() : new RedstonePowerOffSound()
		);
		return true;
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if($this->onInteractParent()){
			foreach($this->getRelyingBlocks() as $side){
				$side->power($this);
			}
			return true;
		}
		return false;
	}
}