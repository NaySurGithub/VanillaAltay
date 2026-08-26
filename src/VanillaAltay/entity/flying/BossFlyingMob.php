<?php

declare(strict_types=1);

namespace VanillaAltay\entity\flying;

use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\player\Player;

use function abs;

abstract class BossFlyingMob extends FlyingMonster
{
	private float $lastBossHealth = -1;

	public function spawnTo(Player $player) : void
	{
		parent::spawnTo($player);
		$player->getNetworkSession()->sendDataPacket(BossEventPacket::show($this->getId(), $this->getName(), $this->getHealth() / $this->getMaxHealth()));
	}

	public function despawnFrom(Player $player, bool $send = true) : void
	{
		if ($send) {
			$player->getNetworkSession()->sendDataPacket(BossEventPacket::hide($this->getId()));
		}parent::despawnFrom($player, $send);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool
	{
		$changed = parent::entityBaseTick($tickDiff);
		$percent = $this->getHealth() / $this->getMaxHealth();
		if (abs($percent - $this->lastBossHealth) > .001) {
			$this->lastBossHealth = $percent;
			$packet = BossEventPacket::healthPercent($this->getId(), $percent);
			foreach ($this->getViewers() as $viewer) {
				$viewer->getNetworkSession()->sendDataPacket($packet);
			}
		}return $changed;
	}
}
