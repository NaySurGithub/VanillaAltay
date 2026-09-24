<?php

declare(strict_types=1);

namespace redstone\block\tile\piston;

use pocketmine\block\Block;
use pocketmine\block\tile\Tile;
use pocketmine\math\Vector3;

final class PistonMoveInfo{

	public function __construct(
		readonly public Block $block,
		readonly public ?Tile $tile,
		readonly public Vector3 $from,
		readonly public Vector3 $to
	){}
}