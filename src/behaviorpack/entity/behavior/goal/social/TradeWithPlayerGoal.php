<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\social;

use behaviorpack\entity\behavior\Goal;
use behaviorpack\entity\behavior\inventory\TradeTableSystem;
use pocketmine\player\Player;
use function is_array;

/**
 * "minecraft:behavior.trade_with_player": stands still and looks at the
 * player trading with the entity.
 */
class TradeWithPlayerGoal extends Goal{

	private ?Player $player = null;

	public function getControls() : int{
		return self::MOVE | self::LOOK | self::JUMP;
	}

	private function tradingPlayer() : ?Player{
		$system = $this->entity->getSystem("minecraft:economy_trade_table");
		if(!$system instanceof TradeTableSystem){
			return null;
		}
		$player = $system->getTradingPlayer();
		if($player === null || !$player->isOnline() || !SocialHelper::isValid($player, $this->entity)){
			return null;
		}
		return $player;
	}

	public function canUse() : bool{
		$player = $this->tradingPlayer();
		if($player === null){
			return false;
		}
		if(is_array($this->config["filters"] ?? null) && !$this->entity->testFilter($this->config["filters"], ["other" => $player])){
			return false;
		}
		$this->player = $player;
		return true;
	}

	public function start() : void{
		$this->entity->getNavigator()->stop();
	}

	public function stop() : void{
		$this->player = null;
	}

	public function tick(int $tickDiff) : void{
		$this->entity->getNavigator()->stop();
		if($this->player !== null){
			$this->entity->lookAt($this->player->getEyePos());
		}
	}
}
