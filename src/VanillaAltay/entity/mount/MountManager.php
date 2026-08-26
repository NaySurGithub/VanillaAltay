<?php

declare(strict_types=1);

namespace VanillaAltay\entity\mount;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use pocketmine\player\Player;
use VanillaAltay\entity\passive\RideableAnimal;

final class MountManager implements Listener
{
	/** @var array<int,RideableAnimal> */
	private static array $mounts = [];

	public static function mount(Player $player, RideableAnimal $mount) : void
	{
		self::dismount($player);
		self::$mounts[$player->getId()] = $mount;
		$mount->setRider($player);
		self::link($player, $mount, EntityLink::TYPE_RIDER);
	}

	public static function dismount(Player $player) : void
	{
		if (($mount = self::$mounts[$player->getId()] ?? null) !== null) {
			self::link($player, $mount, EntityLink::TYPE_REMOVE);
			$mount->setRider(null);
			unset(self::$mounts[$player->getId()]);
		}
	}

	private static function link(Player $player, RideableAnimal $mount, int $type) : void
	{
		$packet = SetActorLinkPacket::create(new EntityLink($mount->getId(), $player->getId(), $type, true, true, 0));
		$mount->getWorld()->broadcastPacketToViewers($mount->getPosition(), $packet);
		$player->getNetworkSession()->sendDataPacket($packet);
	}

	public function onPacket(DataPacketReceiveEvent $event) : void
	{
		$packet = $event->getPacket();
		$player = $event->getOrigin()->getPlayer();
		if (!$packet instanceof PlayerAuthInputPacket || $player === null || ($mount = self::$mounts[$player->getId()] ?? null) === null) {
			return;
		}$flags = $packet->getInputFlags();
		if ($flags->get(PlayerAuthInputFlags::START_SNEAKING) || $flags->get(PlayerAuthInputFlags::SNEAK_DOWN)) {
			self::dismount($player);
			return;
		}$mount->applyRiderInput($player, $packet->getMoveVecX(), $packet->getMoveVecZ(), $flags->get(PlayerAuthInputFlags::START_JUMPING));
	}

	public function onQuit(PlayerQuitEvent $event) : void
	{
		self::dismount($event->getPlayer());
	}
}
