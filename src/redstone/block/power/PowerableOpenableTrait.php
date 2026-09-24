<?php

declare(strict_types=1);

namespace redstone\block\power;

use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\world\sound\DoorSound;
use redstone\block\utils\PoweredBlockData;
use redstone\world\RedstoneWorldManager;

/**
 * Openable blocks whose Bedrock state has no powered bit. The last received
 * power state is kept in the redstone world so that only power changes open
 * or close the block, leaving the player free to toggle it while it stays
 * powered or unpowered.
 */
trait PowerableOpenableTrait{
	use PowerableTrait;

	protected int $activation_delay = 0;
	protected int $deactivation_delay = 0;
	protected bool $requires_strong_power = false;

	public function isPowered() : bool{
		$data = RedstoneWorldManager::$any->get($this->position->world)->getExtraDataAt($this->position->x, $this->position->y, $this->position->z);
		return $data instanceof PoweredBlockData ? $data->powered : $this->open;
	}

	protected function onReceivePower(int $power) : void{
		$powered = $power > 0;
		if($powered === $this->isPowered()){
			return;
		}

		RedstoneWorldManager::$any->get($this->position->world)->setExtraDataAt($this->position->x, $this->position->y, $this->position->z, new PoweredBlockData($powered));
		if($powered !== $this->open){
			$this->position->world->setBlockAt($this->position->x, $this->position->y, $this->position->z, $this->setOpen($powered), false);
			$this->position->world->addSound($this->position, new DoorSound());
		}
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		RedstoneWorldManager::$any->get($this->position->world)->removeExtraDataAt($this->position->x, $this->position->y, $this->position->z);
		return parent::onBreak($item, $player, $returnedItems);
	}
}
