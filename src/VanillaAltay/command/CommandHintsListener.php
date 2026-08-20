<?php

declare(strict_types=1);

namespace VanillaAltay\command;

use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\lang\Translatable;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\serializer\AvailableCommandsPacketAssembler;
use pocketmine\network\mcpe\protocol\types\command\CommandData;
use pocketmine\network\mcpe\protocol\types\command\CommandEnum;
use pocketmine\network\mcpe\protocol\types\command\CommandHardEnum;
use pocketmine\network\mcpe\protocol\types\command\CommandOverload;
use pocketmine\network\mcpe\protocol\types\command\CommandParameter;
use pocketmine\network\mcpe\protocol\types\command\CommandPermissions;
use pocketmine\Server;
use function array_values;
use function count;
use function in_array;
use function strtolower;
use function ucfirst;

final class CommandHintsListener implements Listener{

	/**
	 * Set while sending the replacement.
	 */
	private bool $rebuilding = false;

	/**
	 * @param CommandOverload[][] $overloads
	 * @phpstan-param array<string, list<CommandOverload>> $overloads
	 */
	public function __construct(private array $overloads){}

	/**
	 * @param string[] $biomes
	 * @param string[] $structures
	 *
	 * @return CommandOverload[]
	 * @phpstan-return list<CommandOverload>
	 */
	public static function locateOverloads(array $biomes, array $structures) : array{
		return [
			new CommandOverload(chaining: false, parameters: [
				CommandParameter::enum("mode", new CommandHardEnum("LocateModeBiome", ["biome"]), 0),
				CommandParameter::enum("biome", new CommandHardEnum("LocateBiome", $biomes), 0),
				CommandParameter::standard("radius", AvailableCommandsPacket::ARG_TYPE_INT, 0, true),
				CommandParameter::enum("teleport", new CommandHardEnum("LocateTeleport", ["true", "false"]), 0, true)
			]),
			new CommandOverload(chaining: false, parameters: [
				CommandParameter::enum("mode", new CommandHardEnum("LocateModeStructure", ["structure"]), 0),
				CommandParameter::enum("structure", new CommandHardEnum("LocateStructure", $structures), 0),
				CommandParameter::standard("radius", AvailableCommandsPacket::ARG_TYPE_INT, 0, true),
				CommandParameter::enum("teleport", new CommandHardEnum("LocateTeleport", ["true", "false"]), 0, true)
			])
		];
	}

	/**
	 * @priority NORMAL
	 */
	public function onDataPacketSend(DataPacketSendEvent $event) : void{
		if($this->rebuilding){
			return;
		}

		foreach($event->getPackets() as $packet){
			if($packet instanceof AvailableCommandsPacket){
				$event->cancel();
				break;
			}
		}

		if(!$event->isCancelled()){
			return;
		}

		$this->rebuilding = true;
		try{
			$this->sendReplacements($event);
		}finally{
			$this->rebuilding = false;
		}
	}

	private function sendReplacements(DataPacketSendEvent $event) : void{
		foreach($event->getTargets() as $target){
			$player = $target->getPlayer();
			if($player === null){
				continue;
			}

			$commandData = [];
			foreach(Server::getInstance()->getCommandMap()->getCommands() as $command){
				$label = $command->getLabel();
				if(isset($commandData[$label]) || $label === "help" || !$command->testPermissionSilent($player)){
					continue;
				}

				$name = strtolower($label);
				$aliases = $command->getAliases();
				$aliasObj = null;
				if(count($aliases) > 0){
					if(!in_array($name, $aliases, true)){
						$aliases[] = $name;
					}
					$aliasObj = new CommandHardEnum(ucfirst($label) . "Aliases", $aliases);
				}

				$description = $command->getDescription();

				$commandData[$label] = new CommandData(
					$name,
					$description instanceof Translatable ? $player->getLanguage()->translate($description) : $description,
					0,
					CommandPermissions::NORMAL,
					$aliasObj,
					$this->overloads[$name] ?? [
						new CommandOverload(chaining: false, parameters: [CommandParameter::standard("args", AvailableCommandsPacket::ARG_TYPE_RAWTEXT, 0, true)])
					],
					chainedSubCommandData: []
				);
			}

			$target->sendDataPacket(AvailableCommandsPacketAssembler::assemble(array_values($commandData), [], []));
		}
	}
}
