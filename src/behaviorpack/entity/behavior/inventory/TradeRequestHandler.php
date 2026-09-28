<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\inventory;

use pocketmine\network\mcpe\protocol\ItemStackRequestPacket;
use pocketmine\network\mcpe\protocol\ItemStackResponsePacket;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CraftRecipeAutoStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CraftRecipeStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackresponse\ItemStackResponse;
use pocketmine\player\Player;

/**
 * Completes the trades requested from the trade screen. The crafting
 * transactions of the server know nothing of entity trades, so a request
 * crafting a trade recipe is performed here and the inventories are synced
 * back to the client.
 */
final class TradeRequestHandler{

	private function __construct(){
	}

	/**
	 * Returns true when the packet was a trade and must not be processed
	 * further (the receive event should be cancelled).
	 */
	public static function handle(Player $player, ItemStackRequestPacket $packet) : bool{
		$window = $player->getCurrentWindow();
		if(!$window instanceof TradeInventory){
			return false;
		}
		$trades = [];
		foreach($packet->getRequests() as $request){
			foreach($request->getActions() as $action){
				if($action instanceof CraftRecipeStackRequestAction || $action instanceof CraftRecipeAutoStackRequestAction){
					$index = TradeTableSystem::tradeIndex($action->getRecipeId());
					if($index !== null){
						$trades[] = [$index, $action->getRepetitions()];
					}
				}
			}
		}
		if($trades === []){
			return false;
		}
		$system = $window->getSystem();
		foreach($trades as [$index, $repetitions]){
			$system->executeTrade($player, $index, $repetitions);
		}
		$responses = [];
		foreach($packet->getRequests() as $request){
			$responses[] = new ItemStackResponse(ItemStackResponse::RESULT_ERROR, $request->getRequestId());
		}
		$session = $player->getNetworkSession();
		$session->sendDataPacket(ItemStackResponsePacket::create($responses));
		$session->getInvManager()?->syncAll();
		return true;
	}
}
