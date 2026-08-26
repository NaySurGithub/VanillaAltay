<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\entity\Location;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;

use function array_rand;
use function in_array;
use function mt_rand;

class Slime extends ConfiguredMonster
{
	protected const NETWORK_ID = EntityIds::SLIME;

	protected const NAME = "Slime";

	protected const HEIGHT = 2.55;

	protected const WIDTH = 2.55;

	protected const HEALTH = 16;

	protected const SPEED = .3;

	protected const DAMAGE = 6.0;

	private int $slimeSize = 4;

	private bool $didSplit = false;

	protected function initEntity(CompoundTag $nbt) : void
	{
		$size = $nbt->getInt("Size", 0);
		$this->slimeSize = in_array($size, [1,2,4], true) ? $size : [1,2,4][array_rand([1,2,4])];
		parent::initEntity($nbt);
		$this->setScale((.51 + $this->slimeSize * .51) / 2.55);
	}

	protected function getVanillaMaxHealth() : int
	{
		return match ($this->slimeSize) {
			1 => 1,2 => 4,default => 16,
		};
	}

	protected function getAttackDamage() : float
	{
		return match ($this->slimeSize) {
			1 => 0,2 => 2,default => 6,
		};
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void
	{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::VARIANT, $this->slimeSize);
	}

	public function setSlimeSize(int $size) : void
	{
		$this->slimeSize = $size;
		$this->setMaxHealth($this->getVanillaMaxHealth());
		$this->setHealth($this->getMaxHealth());
		$this->setScale((.51 + $size * .51) / 2.55);
		$this->getNetworkProperties()->setInt(EntityMetadataProperties::VARIANT, $size);
	}

	public function saveNBT() : CompoundTag
	{
		return parent::saveNBT()->setInt("Size", $this->slimeSize);
	}

	public function kill() : void
	{
		if (!$this->didSplit && $this->isAlive() && $this->slimeSize > 1) {
			$this->didSplit = true;
			$smaller = $this->slimeSize === 4 ? 2 : 1;
			for ($i = 0,$count = mt_rand(2, 4); $i < $count; $i++) {
				$child = new static(Location::fromObject($this->getPosition()->add(mt_rand(-5, 5) / 10, 0, mt_rand(-5, 5) / 10), $this->getWorld()));
				$child->setSlimeSize($smaller);
				$child->spawnToAll();
			}
		}parent::kill();
	}

	protected function isMagmaCube() : bool
	{
		return false;
	}

	public function getDrops() : array
	{
		if ($this->slimeSize !== 1) {
			return [];
		}return [$this->isMagmaCube() ? VanillaItems::MAGMA_CREAM()->setCount(mt_rand(0, 1)) : VanillaItems::SLIMEBALL()->setCount(mt_rand(1, 2))];
	}

	public function getXpDropAmount() : int
	{
		return $this->slimeSize;
	}
}
