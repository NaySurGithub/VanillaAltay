<?php

declare(strict_types=1);

namespace redstone\block;

use Generator;
use pocketmine\block\Block;
use pocketmine\block\SimplePressurePlate;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use redstone\block\power\Transmittable;
use redstone\block\utils\HelperUtils;

trait PressurePlateTrait{

	public function writeStateToWorld() : void{
		parent::writeStateToWorld();
		if($this->hasOutputSignal()){
			foreach($this->getRelyingBlocks() as $block){
				$block->power($this);
			}
		}
	}

	protected function recalculateCollisionBoxes() : array{
		return [new AxisAlignedBB(0.0625, 0, 0.0625, 0.9375, $this->hasOutputSignal() ? 0.03125 : 0.0625, 0.9375)];
	}

	public function canBePlacedAt(Block $blockReplace, Vector3 $clickVector, int $face, bool $isClickedBlock) : bool{
		if(parent::canBePlacedAt($blockReplace, $clickVector, $face, $isClickedBlock)){
			$pos = $blockReplace->getPosition();
			$below = $pos->down();
			return $pos->getWorld()->getBlockAt($below->x, $below->y, $below->z)->isSolid();
		}
		return false;
	}

	public function getPowerLevel() : int{
		return $this->hasOutputSignal() ? 15 : 0;
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

	public function getSupportingSide() : int{
		return Facing::DOWN;
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		if($this->hasOutputSignal()){
			$this->setPressed(false);
			foreach($this->getRelyingOnSupportBlocks() as $block){
				$block->power($this);
			}
		}
		return parent::onBreak($item, $player, $returnedItems);
	}
}