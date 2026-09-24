<?php

declare(strict_types=1);

namespace redstone\block;

use redstone\block\power\Powerable;
use redstone\block\power\PowerableOpenableTrait;

final class FenceGate extends \pocketmine\block\FenceGate implements Powerable{
	use OptimizedBlockTrait;
	use PowerableOpenableTrait;
}
