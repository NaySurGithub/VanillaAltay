<?php

declare(strict_types=1);

namespace redstone\block\power;

use pocketmine\math\Facing;
use redstone\block\utils\HelperUtils;
use redstone\world\RedstoneWorld;
use redstone\world\RedstoneWorldManager;

trait PowerableTrait{

	public function getActivationDelay() : int{
		return $this->activation_delay;
	}

	public function getDeactivationDelay() : int{
		return $this->deactivation_delay;
	}

	public function requiresStrongPower() : bool{
		return $this->requires_strong_power;
	}

	public function onNearbyBlockChange() : void{
		parent::onNearbyBlockChange();
		$this->recalculatePowerState();
	}

	public function onPostPlace() : void{
		$this->recalculatePowerState();
	}

	public function recalculatePowerState() : void{
		$power = 0;
		$blocks = [];

		if(!$this->requires_strong_power){
			foreach(Facing::ALL as $side){
				if($this->acceptsPowerFromSide($side)){
					$block = HelperUtils::getBlockAtSide($this->position, $side);
					$blocks[$side] = $block;
					if($block instanceof PowerSource && $block->canPower(Facing::opposite($side))){
						$block_power = $block->getOutputPowerLevel();
						if($block_power > $power){
							$power = $block_power;
							if($power === 15){
								break;
							}
						}
					}
				}
			}
		}

		if($power !== 15){
			$world = RedstoneWorldManager::$any->get($this->position->world);
			if($world->isStronglyPowered($this, -1)){
				$power = 15;
			}else{
				foreach(Facing::ALL as $side){
					if($this->acceptsPowerFromSide($side)){
						$block = $blocks[$side] ?? HelperUtils::getBlockAtSide($this->position, $side);
						if($world->isStronglyPowered($block, $opposite_side = Facing::opposite($side), $opposite_side)){
							$power = 15;
							break;
						}
					}
				}
			}
		}

		$this->onReceivePower($power);
	}

	public function power(PowerSource $source) : void{
		$delay = $this->isPowered() ? $this->deactivation_delay : $this->activation_delay;
		if($delay > 0){
			RedstoneWorldManager::$any->get($this->position->world)->scheduleWaitableUpdate($this, RedstoneWorld::redstoneTicks($delay), override: true);
		}else{
			$this->onRedstoneTickReceive();
		}
	}

	public function onRedstoneTickReceive() : void{
		$this->recalculatePowerState();
	}

	public function acceptsPowerFromSide(int $side) : bool{
		return true;
	}

	protected function onReceivePower(int $power) : void{
	}
}