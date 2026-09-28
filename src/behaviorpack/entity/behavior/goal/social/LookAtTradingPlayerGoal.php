<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\social;

use behaviorpack\entity\behavior\Goal;
use behaviorpack\entity\behavior\inventory\TradeTableSystem;
use pocketmine\player\Player;

/**
 * "minecraft:behavior.look_at_trading_player": looks at the player trading
 * with the entity.
 */
class LookAtTradingPlayerGoal extends Goal{

	private ?Player $player = null;

	public function getControls() : int{
		return self::LOOK;
	}

	public function canUse() : bool{
		$system = $this->entity->getSystem("minecraft:economy_trade_table");
		if(!$system instanceof TradeTableSystem){
			return false;
		}
		$player = $system->getTradingPlayer();
		if($player === null || !SocialHelper::isValid($player, $this->entity)){
			return false;
		}
		$this->player = $player;
		return true;
	}

	public function stop() : void{
		$this->player = null;
	}

	public function tick(int $tickDiff) : void{
		if($this->player !== null){
			$this->entity->lookAt($this->player->getEyePos());
		}
	}
}
