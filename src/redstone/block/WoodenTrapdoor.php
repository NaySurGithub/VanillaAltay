<?php

declare(strict_types=1);

namespace redstone\block;

use redstone\block\power\Powerable;
use redstone\block\power\PowerableOpenableTrait;

final class WoodenTrapdoor extends \pocketmine\block\WoodenTrapdoor implements Powerable{
	use OptimizedBlockTrait;
	use PowerableOpenableTrait;
}
