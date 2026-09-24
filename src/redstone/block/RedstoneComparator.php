<?php

declare(strict_types=1);

namespace redstone\block;

use Generator;
use pocketmine\block\Block;
use pocketmine\block\Flowable;
use pocketmine\block\Glowstone;
use pocketmine\block\RedstoneComparator as VanillaRedstoneComparator;
use pocketmine\block\Slab;
use pocketmine\block\tile\Container;
use pocketmine\block\utils\SlabType;
use pocketmine\item\Item;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\sound\RedstonePowerOffSound;
use pocketmine\world\sound\RedstonePowerOnSound;
use redstone\block\power\PowerSource;
use redstone\block\power\Transmittable;
use redstone\block\power\VariablePowerSource;
use redstone\block\power\Waitable;
use redstone\block\tile\comparator\ComparatorInventoryListener;
use redstone\block\tile\comparator\ComparatorWeightRegistry;
use redstone\block\utils\HelperUtils;
use redstone\world\RedstoneWorld;
use redstone\world\RedstoneWorldManager;
use function floor;
use function min;

class RedstoneComparator extends VanillaRedstoneComparator implements VariablePowerSource, Transmittable, Waitable{
	use OptimizedBlockTrait;

	public function getPowerLevel() : int{
		return $this->signalStrength;
	}

	public function getOutputPowerLevel() : int{
		return $this->getPowerLevel();
	}

	public function canPower(int $side) : bool{
		return $side === Facing::opposite($this->facing);
	}

	public function canStronglyPower(int $side) : bool{
		return $this->canPower($side);
	}

	public function setPowerLevel(int $level) : void{
		if($level !== $this->signalStrength){
			$this->signalStrength = $level;
			$this->position->world->setBlockAt($this->position->x, $this->position->y, $this->position->z, $this->setPowered($this->signalStrength > 0), false);
			$facing = HelperUtils::getBlockAtSide($this->position, Facing::opposite($this->facing));
			if($facing instanceof Transmittable){
				$facing->power($this);
			}
			foreach($this->getSupportingBlocks() as $block){
				$block->power($this);
			}
		}
	}

	public function onNearbyBlockChange() : void{
		if(!$this->canBePlacedUpon(HelperUtils::getBlockAtSide($this->position, Facing::DOWN))){
			$this->position->world->useBreakOn($this->position);
			return;
		}
		$this->setPowerLevel($this->recalculatePower());
		$this->notifyContainer();
	}

	public function power(PowerSource $source) : void{
		if($source->getPowerLevel() !== $this->signalStrength){
			RedstoneWorldManager::$any->get($this->position->world)->scheduleWaitableUpdate($this, RedstoneWorld::redstoneTicks(1), RedstoneWorld::WAITABLE_UPDATE_LOW_PRIORITY);
		}
	}

	private function recalculatePower() : int{
		$behind = HelperUtils::getBlockAtSide($this->position, $this->facing);
		$world = RedstoneWorldManager::$any->get($this->position->world);
		if($behind instanceof PowerSource){
			$rear = $behind->getPowerLevel();
		}else{
			$tile = $behind->position->world->getTileAt($behind->position->x, $behind->position->y, $behind->position->z);
			if($tile instanceof Container){
				$inventory = $tile->getInventory();
				$fullness = 0;
				foreach(HelperUtils::fastReadOnlyInventoryContents($inventory) as $item){
					$fullness += min(1, $item->getCount() / $item->getMaxStackSize());
				}
				$rear = $fullness > 0 ? (int) floor(1 + ($fullness / $inventory->getSize()) * 14) : 0;
			}else{
				$rear = 0;
				/** @var PowerSource $source */
				foreach($world->getStronglyPoweringSources($behind, $opposite_side = Facing::opposite($this->facing), $opposite_side) as $source){
					$rear = $source->getPowerLevel();
					break;
				}
			}
		}

		if($rear === 0){
			return 0;
		}

		$side_strengths = [];
		foreach(Facing::axis($this->facing) === Axis::Z ? [Facing::EAST, Facing::WEST] : [Facing::NORTH, Facing::SOUTH] as $side){
			$side_strengths[] = ComparatorWeightRegistry::getValue(HelperUtils::getBlockAtSide($this->position, $side));
		}

		return $this->isSubtractMode ? max($rear - max($side_strengths), 0) :
			($side_strengths[0] <= $rear && $side_strengths[1] <= $rear ? $rear : 0);
	}

	public function onRedstoneTickReceive() : void{
		$this->setPowerLevel($this->recalculatePower());
	}

	public function onContainerInputChange() : void{
		RedstoneWorldManager::$any->get($this->position->world)->scheduleWaitableUpdate($this, RedstoneWorld::redstoneTicks(1), RedstoneWorld::WAITABLE_UPDATE_LOW_PRIORITY);
	}

	/**
	 * @return Generator<Transmittable>
	 */
	public function getSupportingBlocks() : Generator{
		$facing = HelperUtils::getBlockAtSide($this->position, Facing::opposite($this->facing));
		if(!($facing instanceof self)){
			foreach(Facing::ALL as $face){
				if($face !== $this->facing){
					$block = HelperUtils::getBlockAtSide($facing->position, $face);
					if($block instanceof Transmittable){
						yield $block;
					}
				}
			}
		}
	}

	private function canBePlacedUpon(Block $block) : bool{
		return !$block->isTransparent() || ($block instanceof Slab && $block->getSlabType() === SlabType::TOP) || $block instanceof Glowstone;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($this->canBePlacedUpon(HelperUtils::getBlockAtSide($blockReplace->position, Facing::DOWN))){
			if($player !== null){
				$this->facing = Facing::opposite($player->getHorizontalFacing());
			}
			return Flowable::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
		}
		return false;
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		$old_state = $this->isSubtractMode();
		if(parent::onInteract($item, $face, $clickVector, $player, $returnedItems)){
			if($old_state !== $this->isSubtractMode()){
				$this->position->world->addSound($this->position->add(0.5, 0.5, 0.5), $this->isSubtractMode() ? new RedstonePowerOnSound() : new RedstonePowerOffSound());
			}
			return true;
		}
		return false;
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		$powered = $this->powered;
		$this->powered = false;
		if(parent::onBreak($item, $player, $returnedItems)){
			if($powered){
				foreach($this->getSupportingBlocks() as $block){
					$block->power($this);
				}
			}
			return true;
		}
		return false;
	}

	private function notifyContainer() : void{
		$behind_pos = $this->position->getSide($this->facing);
		$behind = $this->position->world->getTileAt($behind_pos->x, $behind_pos->y, $behind_pos->z);
		if($behind instanceof Container){
			$inventory = $behind->getRealInventory();
			$listener = ComparatorInventoryListener::instance($this->facing);
			if(!$inventory->getListeners()->contains($listener)){
				$inventory->getListeners()->add($listener);
				$listener->update($inventory);
			}
		}
	}

	public function onScheduledUpdate() : void{
		$this->notifyContainer();
	}
}
