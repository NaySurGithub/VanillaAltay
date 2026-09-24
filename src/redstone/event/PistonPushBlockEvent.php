<?php

declare(strict_types=1);

namespace redstone\event;

use pocketmine\block\Block;
use pocketmine\event\block\BlockEvent;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\math\Vector3;
use redstone\block\Piston;
use redstone\block\tile\piston\PistonMoveInfo;

final class PistonPushBlockEvent extends BlockEvent implements Cancellable{
	use CancellableTrait;

	/**
	 * @param Piston $piston
	 * @param Vector3 $arm_pos
	 * @param list<PistonMoveInfo> $movements
	 */
	public function __construct(
		readonly public Piston $piston,
		readonly public Vector3 $arm_pos,
		readonly public array $movements
	){
		parent::__construct($this->piston);
	}
}