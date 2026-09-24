<?php

declare(strict_types=1);

namespace behaviorpack\script;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use function array_shift;
use function count;
use function implode;
use function str_contains;

/**
 * The /scriptevent command, received by scripts through
 * system.afterEvents.scriptEventReceive.
 */
final class ScriptEventCommand extends Command implements PluginOwned{

	public function __construct(
		private Plugin $plugin,
		private ScriptLoader $loader
	){
		parent::__construct("scriptevent", "Sends an event to the scripts of the behavior packs", "/scriptevent <namespace:id> [message]");
		$this->setPermission(DefaultPermissionNames::GROUP_OPERATOR);
	}

	public function getOwningPlugin() : Plugin{
		return $this->plugin;
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(count($args) === 0){
			$sender->sendMessage("Usage: /scriptevent <namespace:id> [message]");
			return false;
		}
		$id = (string) array_shift($args);
		if(!str_contains($id, ":") || str_contains($id, "minecraft:")){
			$sender->sendMessage("The id must have a namespace other than minecraft");
			return false;
		}
		$this->loader->sendScriptEvent($id, implode(" ", $args), $sender instanceof Entity ? $sender : null);
		return true;
	}
}
