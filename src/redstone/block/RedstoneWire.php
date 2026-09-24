<?php

declare(strict_types=1);

namespace redstone\block;

use Generator;
use pocketmine\block\Block;
use pocketmine\block\RedstoneWire as VanillaRedstoneWire;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\World;
use redstone\block\power\PowerSource;
use redstone\block\power\Transmittable;
use redstone\block\utils\HelperUtils;
use redstone\vanilla\ExtraVanillaItems;
use redstone\world\RedstoneWorldManager;
use function array_values;
use function assert;
use function max;

class RedstoneWire extends VanillaRedstoneWire implements PowerSource, Transmittable{
	use OptimizedBlockTrait;

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		return HelperUtils::getBlockAtSide($this->position, Facing::DOWN)->isSolid() && parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onNearbyBlockChange() : void{
		if(!HelperUtils::getBlockAtSide($this->position, Facing::DOWN)->isSolid()){
			$this->position->world->useBreakOn($this->position);
			return;
		}
		$diff = $this->recalculateBestPower() - $this->signalStrength;
		if($diff !== 0){
			$this->updatePowerLevels([$this], $diff);
		}else{
			foreach($this->getRelyingHorizontalBlocks() as $block){
				if(!($block instanceof self)){
					$block->power($this);
				}
			}
		}
		parent::onNearbyBlockChange();
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		$this->signalStrength = 0;
		if(parent::onBreak($item, $player, $returnedItems)){
			foreach($this->getRelyingBlocks() as $block){
				$block->power($this);
			}
			return true;
		}

		return false;
	}

	public function getPowerLevel() : int{
		return $this->signalStrength;
	}

	public function getOutputPowerLevel() : int{
		return max($this->signalStrength - 1, 0);
	}

	public function canStronglyPower(int $side) : bool{
		if($side === Facing::DOWN){
			return true;
		}

		if($side === Facing::UP){
			return false;
		}

		$above_transparent = HelperUtils::getBlockAtSide($this->position, Facing::UP)->isTransparent();
		foreach($side === Facing::NORTH || $side === Facing::SOUTH ? [Facing::WEST, Facing::EAST] : [Facing::NORTH, Facing::SOUTH] as $side2){
			$block = HelperUtils::getBlockAtSide($this->position, $side2);
			if($block instanceof PowerSource){
				return false;
			}

			if($above_transparent){
				$up = HelperUtils::getBlockAtSide($block->position, Facing::UP);
				if($up instanceof self){
					return false;
				}
			}

			if($block->isTransparent()){
				$down = HelperUtils::getBlockAtSide($block->position, Facing::DOWN);
				if($down instanceof self){
					return false;
				}
			}
		}

		return true;
	}

	private function collectBreadthFirst(array $blocks) : array{
		$queue = [];
		foreach($blocks as $block){
			if($block instanceof self){
				$queue[] = [$block, 0];
			}
		}
		$queue_index = 0;
		$wires = [];
		$others = [];
		while(isset($queue[$queue_index])){
			[$wire, $distance] = $queue[$queue_index++];
			assert($wire instanceof self);
			if(isset($wires[$hash = World::blockHash($wire->position->x, $wire->position->y, $wire->position->z)])){
				continue;
			}
			$wires[$hash] = [$wire, $distance];
			foreach($wire->getRelyingBlocks() as $neighbour){
				$hash2 = World::blockHash($neighbour->position->x, $neighbour->position->y, $neighbour->position->z);
				if($neighbour instanceof self){
					if($distance <= 15 && !isset($wires[$hash2])){
						$queue[] = [$neighbour, $distance + 1];
					}
				}elseif(isset($others[$hash2])){
					$others[$hash2][1][$hash] = $wire;
				}else{
					$others[$hash2] = [$neighbour, [$hash => $wire]];
				}
			}
		}
		return [array_values($wires), $others];
	}

	private function updatePowerLevels(array $blocks, int $diff) : void{
		[$wires, $others] = $this->collectBreadthFirst($blocks);
		foreach($wires as [$wire, $distance]){
			assert($wire instanceof self);
			if($wire->signalStrength !== 0){
				$wire->signalStrength = 0;
				$wire->position->world->setBlockAt($wire->position->x, $wire->position->y, $wire->position->z, $wire, false);
			}
		}
		$reverse = $diff < 0; // power reduced
		do{
			$changed = 0;
			foreach($reverse ? HelperUtils::reverse($wires) : $wires as [$wire, $distance]){
				assert($wire instanceof self);
				$new_power = $wire->recalculateBestPower();
				if($new_power !== $wire->signalStrength){
					$wire->signalStrength = $new_power;
					$wire->position->world->setBlockAt($wire->position->x, $wire->position->y, $wire->position->z, $wire, false);
					$changed++;
				}
			}
			$reverse = !$reverse;
		}while($changed > 0);
		foreach($others as [$block, $power_list]){
			foreach($power_list as $entry){
				$block->power($entry);
			}
		}
	}

	public function power(PowerSource $source) : void{
		if($source->getOutputPowerLevel() !== $this->signalStrength){
			$blocks = [$this];
			foreach(Facing::ALL as $side){
				$block = $source->getSide($side);
				if($block->position->equals($this->position)){
					continue;
				}
				$blocks[] = $block;
			}
			$this->updatePowerLevels($blocks, $this->recalculateBestPower() - $this->signalStrength);
		}
	}

	public function canPower(int $side) : bool{
		return $side !== Facing::UP && ($side === Facing::DOWN || $this->canPowerHorizontalSide($side));
	}

	protected function canPowerHorizontalSide(int $horizontal_side) : bool{
		$above_transparent = HelperUtils::getBlockAtSide($this->position, Facing::UP)->isTransparent();
		$side_opposite = Facing::opposite($horizontal_side);
		foreach(Facing::HORIZONTAL as $side){
			if($side === $side_opposite){
				continue;
			}

			$block = HelperUtils::getBlockAtSide($this->position, $side);
			if($block instanceof self){
				return false;
			}

			if($above_transparent){
				$up = HelperUtils::getBlockAtSide($block->position, Facing::UP);
				if($up instanceof self){
					return false;
				}
			}

			if($block->isTransparent()){
				$down = HelperUtils::getBlockAtSide($block->position, Facing::DOWN);
				if($down instanceof self){
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * @return Generator<Transmittable>
	 */
	protected function getRelyingBlocks() : Generator{
		$down = HelperUtils::getBlockAtSide($this->position, Facing::DOWN);
		if($down instanceof Transmittable){
			yield $down;
		}

		foreach(Facing::ALL as $side){
			if($side !== Facing::UP){
				$block = HelperUtils::getBlockAtSide($down->position, $side);
				if($block instanceof Transmittable){
					yield $block;
				}
			}
		}

		yield from $this->getRelyingHorizontalBlocks();
	}

	/**
	 * @return Generator<Transmittable>
	 */
	protected function getRelyingHorizontalBlocks() : Generator{
		$blocks = [];
		foreach(Facing::HORIZONTAL as $side){
			$block = HelperUtils::getBlockAtSide($this->position, $side);
			if($block instanceof Transmittable){
				yield $block;
			}
			$blocks[$side] = $block;
		}
		$above_transparent = HelperUtils::getBlockAtSide($this->position, Facing::UP)->isTransparent();
		foreach($blocks as $side => $block){
			if(!($block instanceof self) && $this->canStronglyPower($side)){
				$side_opposite = Facing::opposite($side);
				foreach(Facing::ALL as $side2){
					if($side2 === $side_opposite){
						continue;
					}

					$block2 = HelperUtils::getBlockAtSide($block->position, $side2);
					if($block2 instanceof Transmittable){
						yield $block2;
					}
				}
			}

			if($above_transparent){
				$up = HelperUtils::getBlockAtSide($block->position, Facing::UP);
				if($up instanceof self){
					yield $up;
				}
			}

			if($block->isTransparent()){
				$down = HelperUtils::getBlockAtSide($block->position, Facing::DOWN);
				if($down instanceof self){
					yield $down;
				}
			}
		}
	}

	protected function recalculateBestPower() : int{
		$world = RedstoneWorldManager::$any->get($this->position->world);
		$above = HelperUtils::getBlockAtSide($this->position, Facing::UP);
		$below = HelperUtils::getBlockAtSide($this->position, Facing::DOWN);
		foreach([Facing::DOWN => $above, Facing::UP => $below] as $opposite_side => $block){
			foreach($world->getStronglyPoweringSources($block, $opposite_side) as $power_source){
				if(!($power_source instanceof self)){
					return 15;
				}
			}
		}

		$best_power = 0;
		$above_transparent = $above->isTransparent();
		foreach(Facing::HORIZONTAL as $side){
			$block = HelperUtils::getBlockAtSide($this->position, $side);
			if($block instanceof PowerSource){
				if($block instanceof self || $block->canPower(Facing::opposite($side))){
					$power = $block->getOutputPowerLevel();
					if($power > $best_power){
						$best_power = $power;
						if($best_power === 15){
							break;
						}
					}
				}
			}else{
				foreach($world->getStronglyPoweringSources($block, Facing::opposite($side)) as $power_source){
					if(!($power_source instanceof self)){
						return 15;
					}
				}
				if($above_transparent){
					$up = HelperUtils::getBlockAtSide($block->position, Facing::UP);
					if($up instanceof self){
						$power = $up->getOutputPowerLevel();
						if($power > $best_power){
							$best_power = $power;
							if($best_power === 15){
								break;
							}
						}
					}
				}
				if($block->isTransparent()){
					$down = HelperUtils::getBlockAtSide($block->position, Facing::DOWN);
					if($down instanceof self){
						$power = $down->getOutputPowerLevel();
						if($power > $best_power){
							$best_power = $power;
							if($best_power === 15){
								break;
							}
						}
					}
				}
			}
		}
		return $best_power;
	}

	public function asItem() : Item{
		return ExtraVanillaItems::REDSTONE_DUST();
	}
}