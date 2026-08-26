<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use VanillaAltay\entity\mount\MountManager;

use function cos;
use function sin;

use const M_PI;

abstract class RideableAnimal extends ConfiguredAnimal
{
	private ?Player $rider = null;

	protected function syncNetworkData(EntityMetadataCollection $properties) : void
	{
		parent::syncNetworkData($properties);
		$properties->setByte(EntityMetadataProperties::CAN_RIDE_TARGET, 1);
		$properties->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, new Vector3(0, $this->getSize()->getHeight() * .8, 0));
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool
	{
		if (parent::onInteract($player, $clickPos)) {
			return true;
		}if ($this->rider === null) {
			MountManager::mount($player, $this);
			return true;
		}return false;
	}

	public function setRider(?Player $rider) : void
	{
		$this->rider = $rider;
	}

	public function applyRiderInput(Player $rider, float $strafe, float $forward, bool $jump) : void
	{
		if ($this->rider !== $rider) {
			return;
		}$yaw = $rider->getLocation()->yaw * M_PI / 180;
		$speed = static::SPEED;
		$x = (-sin($yaw) * $forward + cos($yaw) * $strafe) * $speed;
		$z = (cos($yaw) * $forward + sin($yaw) * $strafe) * $speed;
		$this->setRotation($rider->getLocation()->yaw, 0);
		$vertical = ($jump && $this->onGround) ? 0.42 : $this->getMotion()->y;
		$this->setMotion(new Vector3($x, $vertical, $z));
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool
	{
		if ($this->rider !== null && ($this->rider->isClosed() || $this->rider->getWorld() !== $this->getWorld())) {
			MountManager::dismount($this->rider);
		}return parent::entityBaseTick($tickDiff);
	}
}
