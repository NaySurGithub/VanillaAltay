<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior;

use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\math\Vector2;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

/**
 * Keeps the last movement input each player sent: the move vector, the jump
 * and sneak keys and the camera yaw. The vehicles steered by their rider
 * read it.
 */
final class PlayerInputTracker{

	/** @var array<int, array{float, float, bool, bool, float}> */
	private static array $inputs = [];

	private static bool $registered = false;

	private function __construct(){
	}

	public static function register(PluginBase $plugin) : void{
		if(self::$registered){
			return;
		}
		self::$registered = true;
		$manager = $plugin->getServer()->getPluginManager();
		$manager->registerEvent(DataPacketReceiveEvent::class, function(DataPacketReceiveEvent $event) : void{
			$packet = $event->getPacket();
			if(!$packet instanceof PlayerAuthInputPacket){
				return;
			}
			$player = $event->getOrigin()->getPlayer();
			if($player === null){
				return;
			}
			$flags = $packet->getInputFlags();
			self::$inputs[$player->getId()] = [
				$packet->getMoveVecX(),
				$packet->getMoveVecZ(),
				$flags->get(PlayerAuthInputFlags::JUMP_DOWN),
				$flags->get(PlayerAuthInputFlags::SNEAKING) || $flags->get(PlayerAuthInputFlags::SNEAK_DOWN),
				$packet->getYaw()
			];
		}, EventPriority::MONITOR, $plugin, true);
		$manager->registerEvent(PlayerQuitEvent::class, function(PlayerQuitEvent $event) : void{
			unset(self::$inputs[$event->getPlayer()->getId()]);
		}, EventPriority::MONITOR, $plugin);
	}

	/**
	 * Returns the movement input: x is the strafe (positive to the left), y
	 * the forward movement.
	 */
	public static function getMoveVector(Player $player) : Vector2{
		$input = self::$inputs[$player->getId()] ?? null;
		return $input === null ? new Vector2(0, 0) : new Vector2($input[0], $input[1]);
	}

	public static function isJumping(Player $player) : bool{
		return self::$inputs[$player->getId()][2] ?? false;
	}

	public static function isSneaking(Player $player) : bool{
		return self::$inputs[$player->getId()][3] ?? false;
	}

	/**
	 * Returns the camera yaw the player last sent, which keeps updating while
	 * it rides.
	 */
	public static function getYaw(Player $player) : float{
		return self::$inputs[$player->getId()][4] ?? $player->getLocation()->yaw;
	}
}
