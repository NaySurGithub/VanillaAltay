<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use VanillaAltay\entity\ai\goal\MeleeAttackGoal;
use VanillaAltay\entity\ai\goal\NearestPlayerTargetGoal;
use VanillaAltay\entity\ai\goal\RandomStrollGoal;
use VanillaAltay\entity\ai\GoalSelector;

use function mt_rand;

final class Enderman extends Monster
{
	protected function registerGoals(GoalSelector $targets, GoalSelector $goals) : void
	{
		$targets->add(1, new NearestPlayerTargetGoal(64, fn(\pocketmine\player\Player $player) : bool => $this->isPlayerStaring($player)));
		$goals->add(1, new MeleeAttackGoal(.3, 7));
		$goals->add(10, new RandomStrollGoal(.2, 12));
	}

	private function isPlayerStaring(\pocketmine\player\Player $player) : bool
	{
		$to = $this->getPosition()->add(0, $this->getEyeHeight(), 0)->subtractVector($player->getPosition()->add(0, $player->getEyeHeight(), 0));
		$distance = $to->length();
		return $distance > 0 && $player->getDirectionVector()->dot($to->normalize()) > 1 - (.025 / $distance);
	}

	public static function getNetworkTypeId() : string
	{
		return EntityIds::ENDERMAN;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(2.9, 0.6);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 40;
	}

	protected function getAttackSpeed() : float
	{
		return 0.3;
	}

	protected function getAttackDamage() : float
	{
		return 7;
	}

	public function getName() : string
	{
		return "Enderman";
	}

	public function getDrops() : array
	{
		return [VanillaItems::ENDER_PEARL()->setCount(mt_rand(0, 1))];
	}

	public function getXpDropAmount() : int
	{
		return 5;
	}

	public function attack(EntityDamageEvent $source) : void
	{
		parent::attack($source);
		if (!$source->isCancelled() && mt_rand(0, 1) === 0) {
			$this->randomTeleport();
		}
	}

	private function randomTeleport() : void
	{
		for ($attempt = 0; $attempt < 16; ++$attempt) {
			$x = $this->location->getFloorX() + mt_rand(-16, 16);
			$z = $this->location->getFloorZ() + mt_rand(-16, 16);
			$y = $this->getWorld()->getHighestBlockAt($x, $z);
			if ($y === null) {
				continue;
			}
			$destination = new \pocketmine\math\Vector3($x + 0.5, $y + 1, $z + 0.5);
			if ($this->getWorld()->getBlock($destination)->canBeReplaced() && $this->getWorld()->getBlock($destination->up())->canBeReplaced()) {
				$this->teleport($destination);
				return;
			}
		}
	}
}
