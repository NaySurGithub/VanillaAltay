<?php

declare(strict_types=1);

namespace behaviorpack\script;

use pocketmine\console\ConsoleCommandSender;
use pocketmine\lang\Translatable;

/**
 * Runs the commands of scripts with console permissions, without printing
 * their output.
 */
final class ScriptCommandSender extends ConsoleCommandSender{

	public function sendMessage(Translatable|string $message) : void{

	}

	public function getName() : string{
		return "Script";
	}
}
