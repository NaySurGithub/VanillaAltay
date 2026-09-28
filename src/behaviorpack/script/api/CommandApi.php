<?php

declare(strict_types=1);

namespace behaviorpack\script\api;

use behaviorpack\script\ScriptException;
use behaviorpack\script\ScriptLoader;
use behaviorpack\script\ScriptValues;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\event\EventPriority;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\serializer\AvailableCommandsPacketAssembler;
use pocketmine\network\mcpe\protocol\serializer\AvailableCommandsPacketDisassembler;
use pocketmine\network\mcpe\protocol\types\command\CommandData;
use pocketmine\network\mcpe\protocol\types\command\CommandHardEnum;
use pocketmine\network\mcpe\protocol\types\command\CommandOverload;
use pocketmine\network\mcpe\protocol\types\command\CommandParameter;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use pocketmine\utils\TextFormat;
use function array_key_exists;
use function array_values;
use function count;
use function cos;
use function deg2rad;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function mt_rand;
use function sin;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function substr_count;
use function trim;
use function usort;

/**
 * Serves the "cmd." requests: custom commands registered by scripts through
 * the CustomCommandRegistry. Each one becomes a server command whose
 * arguments are parsed by type and delivered to the scripts as "__cmd".
 */
final class CommandApi{

	private const TYPES = ["Boolean", "Integer", "Float", "String", "EntitySelector", "PlayerSelector", "Location", "BlockType", "ItemType", "Enum", "EntityType"];

	/** @var array<string, list<string>> */
	private array $enums = [];

	/** @var array<string, array<string, mixed>> */
	private array $commands = [];

	/** @var array<string, Command> */
	private array $registered = [];

	/** @var array<int, CommandSender> */
	private array $senders = [];

	private int $nextSender = 1;

	private bool $listening = false;

	public function __construct(
		private ScriptValues $values,
		private ScriptLoader $loader
	){}

	/**
	 * @param list<mixed> $a
	 */
	public function handle(string $method, array $a) : mixed{
		return match($method){
			"cmd.enum" => $this->registerEnum((string) ($a[0] ?? ""), is_array($a[1] ?? null) ? $a[1] : []),
			"cmd.register" => $this->registerCommand(is_array($a[0] ?? null) ? $a[0] : []),
			"cmd.reply" => $this->reply((int) ($a[0] ?? 0), (int) ($a[1] ?? 0), $a[2] ?? null),
			"cmd.has" => isset($this->commands[strtolower((string) ($a[0] ?? ""))]),
			default => throw new ScriptException("Unknown request: " . $method)
		};
	}

	/**
	 * @param list<mixed> $values
	 */
	private function registerEnum(string $name, array $values) : bool{
		if($name === ""){
			throw new ScriptException("Enum names cannot be empty");
		}
		if(isset($this->enums[$name])){
			throw new ScriptException("The enum " . $name . " is already registered");
		}
		$list = [];
		foreach($values as $value){
			if(is_string($value) && $value !== "" && !in_array($value, $list, true)){
				$list[] = $value;
			}
		}
		if(count($list) === 0){
			throw new ScriptException("The enum " . $name . " has no values");
		}
		$this->enums[$name] = $list;
		return true;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function registerCommand(array $data) : bool{
		$fullName = strtolower(trim((string) ($data["name"] ?? "")));
		$parts = explode(":", $fullName, 2);
		if(count($parts) !== 2 || $parts[0] === "" || $parts[1] === "" || str_contains($parts[1], " ")){
			throw new ScriptException("Custom command names must be of the form namespace:name");
		}
		if(isset($this->commands[$fullName])){
			throw new ScriptException("The command " . $fullName . " is already registered");
		}
		$parameters = [];
		foreach(["mandatoryParameters" => false, "optionalParameters" => true] as $key => $optional){
			$list = $data[$key] ?? [];
			if(!is_array($list)){
				continue;
			}
			foreach($list as $parameter){
				if(!is_array($parameter)){
					throw new ScriptException("Invalid parameter in " . $fullName);
				}
				$type = (string) ($parameter["type"] ?? "");
				if(!in_array($type, self::TYPES, true)){
					throw new ScriptException("Invalid parameter type " . $type . " in " . $fullName);
				}
				$name = (string) ($parameter["name"] ?? "");
				if($name === ""){
					throw new ScriptException("Parameters must have a name in " . $fullName);
				}
				$enum = null;
				if($type === "Enum"){
					$enum = (string) ($parameter["enumName"] ?? $name);
					if(!isset($this->enums[$enum])){
						throw new ScriptException("The enum " . $enum . " is not registered");
					}
				}
				$parameters[] = ["name" => $name, "type" => $type, "enum" => $enum, "optional" => $optional];
			}
		}
		$permissionLevel = (int) ($data["permissionLevel"] ?? 0);
		$this->commands[$fullName] = [
			"name" => $fullName,
			"description" => (string) ($data["description"] ?? ""),
			"permissionLevel" => $permissionLevel,
			"parameters" => $parameters
		];
		$this->createCommand($fullName, $parts[1], (string) ($data["description"] ?? ""), $permissionLevel);
		return true;
	}

	private function createCommand(string $fullName, string $shortName, string $description, int $permissionLevel) : void{
		$plugin = $this->loader->getPlugin();
		$server = $plugin->getServer();
		$api = $this;
		$command = new class($plugin, $api, $fullName, $shortName, $description) extends Command implements PluginOwned{

			public function __construct(
				private Plugin $plugin,
				private CommandApi $api,
				private string $fullName,
				string $shortName,
				string $description
			){
				parent::__construct($shortName, $description, "/" . $shortName, [$fullName]);
			}

			public function getOwningPlugin() : Plugin{
				return $this->plugin;
			}

			public function execute(CommandSender $sender, string $commandLabel, array $args){
				if(!$this->testPermission($sender)){
					return false;
				}
				return $this->api->execute($this->fullName, $sender, $args);
			}
		};
		$command->setPermission($permissionLevel <= 0 ? DefaultPermissionNames::GROUP_USER : DefaultPermissionNames::GROUP_OPERATOR);
		$server->getCommandMap()->register($plugin->getName(), $command);
		$this->registered[$fullName] = $command;
		$this->listen();
		foreach($server->getOnlinePlayers() as $player){
			$player->getNetworkSession()->syncAvailableCommands();
		}
	}

	private function listen() : void{
		if($this->listening){
			return;
		}
		$this->listening = true;
		$plugin = $this->loader->getPlugin();
		$plugin->getServer()->getPluginManager()->registerEvent(DataPacketSendEvent::class, function(DataPacketSendEvent $event) : void{
			$packets = $event->getPackets();
			$changed = false;
			foreach($packets as $index => $packet){
				if($packet instanceof AvailableCommandsPacket){
					$packets[$index] = $this->rewritePacket($packet);
					$changed = true;
				}
			}
			if($changed){
				$event->setPackets($packets);
			}
		}, EventPriority::HIGHEST, $plugin);
	}

	private function rewritePacket(AvailableCommandsPacket $packet) : AvailableCommandsPacket{
		$byLabel = [];
		foreach($this->registered as $fullName => $command){
			if($command->isRegistered()){
				$byLabel[strtolower($command->getLabel())] = $fullName;
			}
		}
		if(count($byLabel) === 0){
			return $packet;
		}
		$data = AvailableCommandsPacketDisassembler::disassemble($packet);
		$commands = [];
		foreach($data->commandData as $command){
			$fullName = $byLabel[$command->getName()] ?? null;
			if($fullName !== null){
				$command = new CommandData(
					$command->getName(),
					$this->commands[$fullName]["description"],
					$command->getFlags(),
					$command->getPermission(),
					$command->getAliases(),
					[$this->overload($fullName)],
					$command->getChainedSubCommandData()
				);
			}
			$commands[] = $command;
		}
		return AvailableCommandsPacketAssembler::assemble($commands, array_values($data->unusedHardEnums), array_values($data->unusedSoftEnums));
	}

	private function overload(string $fullName) : CommandOverload{
		$parameters = [];
		foreach($this->commands[$fullName]["parameters"] as $parameter){
			$name = $parameter["name"];
			$optional = $parameter["optional"];
			$parameters[] = match($parameter["type"]){
				"Boolean" => CommandParameter::enum($name, new CommandHardEnum("Boolean", ["true", "false"]), 0, $optional),
				"Integer" => CommandParameter::standard($name, AvailableCommandsPacket::ARG_TYPE_INT, 0, $optional),
				"Float" => CommandParameter::standard($name, AvailableCommandsPacket::ARG_TYPE_FLOAT, 0, $optional),
				"EntitySelector", "PlayerSelector" => CommandParameter::standard($name, AvailableCommandsPacket::ARG_TYPE_TARGET, 0, $optional),
				"Location" => CommandParameter::standard($name, AvailableCommandsPacket::ARG_TYPE_POSITION, 0, $optional),
				"Enum" => CommandParameter::enum($name, new CommandHardEnum((string) $parameter["enum"], $this->enums[(string) $parameter["enum"]]), 0, $optional),
				default => CommandParameter::standard($name, AvailableCommandsPacket::ARG_TYPE_STRING, 0, $optional)
			};
		}
		return new CommandOverload(chaining: false, parameters: $parameters);
	}

	/**
	 * Parses the arguments of a custom command and queues it for the scripts.
	 *
	 * @param list<string> $args
	 */
	public function execute(string $fullName, CommandSender $sender, array $args) : bool{
		$definition = $this->commands[$fullName] ?? null;
		if($definition === null){
			return false;
		}
		$tokens = $this->joinSelectors($args);
		$values = [];
		$position = 0;
		try{
			foreach($definition["parameters"] as $parameter){
				if($position >= count($tokens)){
					if(!$parameter["optional"]){
						throw new ScriptException("Missing argument: " . $parameter["name"]);
					}
					break;
				}
				$values[] = $this->parseArgument($parameter, $tokens, $position, $sender);
			}
			if($position < count($tokens)){
				throw new ScriptException("Unexpected argument: " . $tokens[$position]);
			}
		}catch(ScriptException $e){
			$sender->sendMessage(TextFormat::RED . $e->getMessage());
			$sender->sendMessage(TextFormat::RED . $this->usage($definition));
			return false;
		}
		$token = $this->nextSender++;
		$this->senders[$token] = $sender;
		$origin = ["sourceType" => "Server"];
		if($sender instanceof Entity){
			$ref = $this->values->entityRef($sender);
			$origin = ["sourceType" => "Entity", "sourceEntity" => $ref, "initiator" => $ref];
		}
		$this->loader->queueEvent("__cmd", ["name" => $fullName, "token" => $token, "origin" => $origin, "args" => $values]);
		return true;
	}

	/**
	 * @param array<string, mixed> $definition
	 */
	private function usage(array $definition) : string{
		$parts = ["/" . $definition["name"]];
		foreach($definition["parameters"] as $parameter){
			$label = $parameter["name"] . ": " . ($parameter["type"] === "Enum" ? implode("|", $this->enums[(string) $parameter["enum"]]) : $parameter["type"]);
			$parts[] = $parameter["optional"] ? "[" . $label . "]" : "<" . $label . ">";
		}
		return "Usage: " . implode(" ", $parts);
	}

	/**
	 * Rejoins selector arguments that the command map split on spaces.
	 *
	 * @param list<string> $args
	 * @return list<string>
	 */
	private function joinSelectors(array $args) : array{
		$result = [];
		$current = null;
		foreach($args as $arg){
			if($current !== null){
				$current .= " " . $arg;
				if(substr_count($current, "[") <= substr_count($current, "]")){
					$result[] = $current;
					$current = null;
				}
				continue;
			}
			if(str_starts_with($arg, "@") && substr_count($arg, "[") > substr_count($arg, "]")){
				$current = $arg;
				continue;
			}
			$result[] = $arg;
		}
		if($current !== null){
			$result[] = $current;
		}
		return $result;
	}

	/**
	 * @param array<string, mixed> $parameter
	 * @param list<string>         $tokens
	 */
	private function parseArgument(array $parameter, array $tokens, int &$position, CommandSender $sender) : mixed{
		$name = $parameter["name"];
		$token = $tokens[$position];
		switch($parameter["type"]){
			case "Boolean":
				$position++;
				$lower = strtolower($token);
				if($lower !== "true" && $lower !== "false"){
					throw new ScriptException("Expected true or false for " . $name);
				}
				return $lower === "true";
			case "Integer":
				$position++;
				if(!is_numeric($token) || (string) (int) $token !== trim($token, "+")){
					throw new ScriptException("Expected an integer for " . $name);
				}
				return (int) $token;
			case "Float":
				$position++;
				if(!is_numeric($token)){
					throw new ScriptException("Expected a number for " . $name);
				}
				return (float) $token;
			case "String":
				$position++;
				return $token;
			case "Enum":
				$position++;
				$values = $this->enums[(string) $parameter["enum"]];
				foreach($values as $value){
					if(strtolower($value) === strtolower($token)){
						return $value;
					}
				}
				throw new ScriptException("Invalid value " . $token . " for " . $name . ", expected one of: " . implode(", ", $values));
			case "EntitySelector":
			case "PlayerSelector":
				$position++;
				$entities = $this->select($token, $sender, $parameter["type"] === "PlayerSelector");
				if(count($entities) === 0){
					throw new ScriptException("No targets matched selector");
				}
				$refs = [];
				foreach($entities as $entity){
					$refs[] = $this->values->entityRef($entity);
				}
				return $refs;
			case "Location":
				if($position + 3 > count($tokens)){
					throw new ScriptException("Expected x y z for " . $name);
				}
				$coordinates = [$tokens[$position], $tokens[$position + 1], $tokens[$position + 2]];
				$position += 3;
				return $this->values->vectorOut($this->location($coordinates, $sender, $name));
			case "ItemType":
			case "BlockType":
			case "EntityType":
				$position++;
				return ScriptValues::namespaced($token);
		}
		throw new ScriptException("Unsupported parameter type for " . $name);
	}

	/**
	 * @param list<string> $coordinates
	 */
	private function location(array $coordinates, CommandSender $sender, string $name) : Vector3{
		$origin = $sender instanceof Entity ? $sender->getPosition()->asVector3() : new Vector3(0, 0, 0);
		$local = str_starts_with($coordinates[0], "^");
		foreach($coordinates as $coordinate){
			if(str_starts_with($coordinate, "^") !== $local){
				throw new ScriptException("Cannot mix local and world coordinates for " . $name);
			}
		}
		$offsets = [];
		foreach($coordinates as $index => $coordinate){
			$relative = str_starts_with($coordinate, "~") || str_starts_with($coordinate, "^");
			$number = $relative ? substr($coordinate, 1) : $coordinate;
			if($number !== "" && !is_numeric($number)){
				throw new ScriptException("Invalid coordinate " . $coordinate . " for " . $name);
			}
			$offsets[$index] = ["relative" => $relative, "value" => $number === "" ? 0.0 : (float) $number];
		}
		if($local){
			$yaw = $sender instanceof Entity ? deg2rad($sender->getLocation()->getYaw()) : 0.0;
			$pitch = $sender instanceof Entity ? deg2rad($sender->getLocation()->getPitch()) : 0.0;
			$forward = new Vector3(-sin($yaw) * cos($pitch), -sin($pitch), cos($yaw) * cos($pitch));
			$left = new Vector3(cos($yaw), 0, sin($yaw));
			$up = $forward->cross($left)->multiply(-1);
			$eye = $sender instanceof Entity ? $origin->add(0, $sender->getEyeHeight(), 0) : $origin;
			return $eye
				->addVector($left->multiply($offsets[0]["value"]))
				->addVector($up->multiply($offsets[1]["value"]))
				->addVector($forward->multiply($offsets[2]["value"]));
		}
		$axes = [$origin->x, $origin->y, $origin->z];
		$result = [];
		foreach($offsets as $index => $offset){
			$result[$index] = $offset["relative"] ? $axes[$index] + $offset["value"] : $offset["value"];
		}
		return new Vector3($result[0], $result[1], $result[2]);
	}

	/**
	 * Resolves a target selector or a player name.
	 *
	 * @return list<Entity>
	 */
	private function select(string $token, CommandSender $sender, bool $playersOnly) : array{
		$server = $this->values->getServer();
		if(!str_starts_with($token, "@")){
			$player = $server->getPlayerExact($token);
			return $player === null ? [] : [$player];
		}
		$kind = strtolower(substr($token, 1, 1));
		$arguments = $this->selectorArguments(substr($token, 2));
		if(!in_array($kind, ["a", "p", "s", "r", "e"], true)){
			throw new ScriptException("Unknown selector " . $token);
		}
		if($playersOnly && $kind === "e" && isset($arguments["type"]) && $arguments["type"] !== "minecraft:player" && $arguments["type"] !== "player"){
			throw new ScriptException("Only players may be selected");
		}
		$origin = $sender instanceof Entity ? $sender->getPosition() : null;
		if($kind === "s"){
			$candidates = $sender instanceof Entity ? [$sender] : [];
		}elseif($kind === "e" && !$playersOnly){
			$candidates = [];
			$worlds = $origin !== null ? [$origin->getWorld()] : $server->getWorldManager()->getWorlds();
			foreach($worlds as $world){
				foreach($world->getEntities() as $entity){
					if(!$entity->isClosed() && !$entity->isFlaggedForDespawn() && ($entity instanceof Player || $entity->isAlive())){
						$candidates[] = $entity;
					}
				}
			}
		}else{
			$candidates = array_values($server->getOnlinePlayers());
		}
		$filtered = [];
		foreach($candidates as $entity){
			if($this->matches($entity, $arguments, $origin)){
				$filtered[] = $entity;
			}
		}
		if($origin !== null){
			usort($filtered, function(Entity $a, Entity $b) use ($origin) : int{
				return $a->getPosition()->distanceSquared($origin) <=> $b->getPosition()->distanceSquared($origin);
			});
		}
		$limit = isset($arguments["c"]) && is_numeric($arguments["c"]) ? (int) $arguments["c"] : null;
		if($kind === "p"){
			$limit ??= 1;
		}
		if($kind === "r"){
			$shuffled = [];
			while(count($filtered) > 0){
				$index = mt_rand(0, count($filtered) - 1);
				$shuffled[] = $filtered[$index];
				unset($filtered[$index]);
				$filtered = array_values($filtered);
			}
			$filtered = $shuffled;
			$limit ??= 1;
		}
		if($limit !== null){
			$filtered = $limit >= 0 ? \array_slice($filtered, 0, $limit) : \array_slice(\array_reverse($filtered), 0, -$limit);
		}
		return $filtered;
	}

	/**
	 * @return array<string, string>
	 */
	private function selectorArguments(string $body) : array{
		$body = trim($body);
		if($body === ""){
			return [];
		}
		if(!str_starts_with($body, "[") || substr($body, -1) !== "]"){
			throw new ScriptException("Invalid selector arguments");
		}
		$result = [];
		foreach(explode(",", substr($body, 1, strlen($body) - 2)) as $pair){
			$pair = trim($pair);
			if($pair === ""){
				continue;
			}
			$parts = explode("=", $pair, 2);
			if(count($parts) !== 2){
				throw new ScriptException("Invalid selector argument " . $pair);
			}
			$result[strtolower(trim($parts[0]))] = trim(trim($parts[1]), "\"");
		}
		return $result;
	}

	/**
	 * @param array<string, string> $arguments
	 */
	private function matches(Entity $entity, array $arguments, ?Vector3 $origin) : bool{
		if(array_key_exists("type", $arguments)){
			$type = $arguments["type"];
			$negate = str_starts_with($type, "!");
			$wanted = ScriptValues::namespaced($negate ? substr($type, 1) : $type);
			if(($this->values->entityTypeId($entity) === $wanted) === $negate){
				return false;
			}
		}
		if(array_key_exists("name", $arguments)){
			$wanted = $arguments["name"];
			$negate = str_starts_with($wanted, "!");
			$wanted = $negate ? substr($wanted, 1) : $wanted;
			$name = $entity instanceof Player ? $entity->getName() : $entity->getNameTag();
			if(($name === $wanted) === $negate){
				return false;
			}
		}
		if(array_key_exists("r", $arguments) && is_numeric($arguments["r"]) && $origin !== null){
			$radius = (float) $arguments["r"];
			if($entity->getPosition()->distanceSquared($origin) > $radius * $radius){
				return false;
			}
		}
		if(array_key_exists("rm", $arguments) && is_numeric($arguments["rm"]) && $origin !== null){
			$radius = (float) $arguments["rm"];
			if($entity->getPosition()->distanceSquared($origin) < $radius * $radius){
				return false;
			}
		}
		return true;
	}

	private function reply(int $token, int $status, mixed $message) : bool{
		$sender = $this->senders[$token] ?? null;
		unset($this->senders[$token]);
		if($sender === null || ($sender instanceof Player && !$sender->isConnected())){
			return false;
		}
		if(is_string($message) && $message !== ""){
			$sender->sendMessage(($status === 1 ? TextFormat::RED : "") . $message);
		}
		return true;
	}
}
