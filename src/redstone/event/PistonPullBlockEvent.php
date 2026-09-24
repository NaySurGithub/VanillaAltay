<?php

declare(strict_types=1);

namespace redstone\event;

use pocketmine\block\Block;
use pocketmine\event\block\BlockEvent;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use redstone\block\Piston;
use redstone\block\tile\piston\PistonMoveInfo;

final class PistonPullBlockEvent extends BlockEvent implements Cancellable{
	use CancellableTrait;

	/**
	 * @param Piston $piston
	 * @param Block $block
	 * @param list<PistonMoveInfo> $movements
	 */
	public function __construct(
		readonly public Piston $piston,
		Block $block,
		readonly public array $movements = []
	){
		parent::__construct($block);
	}
}