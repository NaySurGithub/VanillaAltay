<?php

declare(strict_types=1);

namespace redstone\block;

use redstone\block\power\PowerableDoorTrait;
use redstone\block\power\Powerable;

final class CopperDoor extends \pocketmine\block\CopperDoor implements Powerable{
	use PowerableDoorTrait;
}