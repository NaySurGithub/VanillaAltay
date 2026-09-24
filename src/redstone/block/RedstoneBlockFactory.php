<?php

declare(strict_types=1);

namespace redstone\block;

use redstone\block\utils\HelperUtils;

final class RedstoneBlockFactory{

	public static function init() : void{
		HelperUtils::init();
	}
}