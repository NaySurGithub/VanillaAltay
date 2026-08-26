<?php

declare(strict_types=1);

namespace VanillaAltay\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Location;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use VanillaAltay\entity\VanillaEntityRegistry;

use function count;
use function is_finite;
use function is_numeric;
use function str_starts_with;
use function substr;

final class SummonCommand extends Command
{
	private const MIN_COORDINATE = -30000000;

	private const MAX_COORDINATE = 30000000;

	public function __construct()
	{
		parent::__construct(
			"summon",
			"Summons a VanillaAltay entity",
			"/summon <entity> [x y z]",
		);
		$this->setPermission(DefaultPermissionNames::GROUP_OPERATOR);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool
	{
		if (!$this->testPermission($sender)) {
			return true;
		}

		if (!$sender instanceof Player) {
			$sender->sendMessage(TextFormat::RED . "This command can only be used in game");
			return true;
		}

		if (count($args) !== 1 && count($args) !== 4) {
			$sender->sendMessage(TextFormat::RED . "Usage: " . $this->getUsage());
			return true;
		}

		$position = $sender->getPosition();
		if (count($args) === 4) {
			$x = $this->parseCoordinate($args[1], $position->x);
			$y = $this->parseCoordinate($args[2], $position->y);
			$z = $this->parseCoordinate($args[3], $position->z);

			if ($x === null || $y === null || $z === null) {
				$sender->sendMessage(TextFormat::RED . "Invalid coordinates");
				return true;
			}
		} else {
			$x = $position->x;
			$y = $position->y;
			$z = $position->z;
		}

		$location = new Location(
			$x,
			$y,
			$z,
			$sender->getWorld(),
			$sender->getLocation()->yaw,
			0.0,
		);
		$entity = VanillaEntityRegistry::createForSummon($args[0], $location);
		if ($entity === null) {
			$sender->sendMessage(TextFormat::RED . "Unknown VanillaAltay entity: " . $args[0]);
			return true;
		}

		$entity->spawnToAll();
		$sender->sendMessage(
			TextFormat::GREEN . "Summoned " . $entity->getName() .
			TextFormat::GREEN . " at " . $x . ", " . $y . ", " . $z,
		);

		return true;
	}

	private function parseCoordinate(string $input, float $origin) : ?float
	{
		$relative = str_starts_with($input, "~");
		$value = $relative ? substr($input, 1) : $input;

		if ($value === "") {
			$value = "0";
		}
		if (!is_numeric($value)) {
			return null;
		}

		$result = (float) $value + ($relative ? $origin : 0.0);
		if (!is_finite($result) || $result < self::MIN_COORDINATE || $result > self::MAX_COORDINATE) {
			return null;
		}

		return $result;
	}
}
