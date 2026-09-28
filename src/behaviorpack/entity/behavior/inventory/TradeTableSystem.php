<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\inventory;

use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\network\mcpe\protocol\UpdateTradePacket;
use pocketmine\player\Player;
use function count;
use function is_array;
use function is_int;
use function is_string;
use function max;
use function min;
use function mt_rand;

/**
 * minecraft:economy_trade_table: the trades of the entity, rolled tier by
 * tier from a trading file, shown on the trade screen. Stock, trader
 * experience and tier are kept in the data store.
 */
final class TradeTableSystem extends EntitySystem{

	public const RECIPE_ID_BASE = 0x3f000000;

	private const DATA_TABLE = "trade_table";
	private const DATA_TRADES = "trades";
	private const DATA_TIER = "trade_tier";
	private const DATA_XP = "trade_xp";
	private const DATA_ROLLED = "trade_tiers_rolled";
	private const DATA_PLAYER = "trading_player";

	private const MAX_TRADE_DISTANCE = 8.0;

	private ?TradeInventory $window = null;

	public static function registerPackRoot(string $root) : void{
		TradeTable::registerPackRoot($root);
	}

	public function getTablePath() : string{
		$table = $this->config["table"] ?? "";
		return is_string($table) ? $table : "";
	}

	public function onAdd() : void{
		$table = $this->getTablePath();
		if($table === ""){
			return;
		}
		if($this->entity->getData(self::DATA_TABLE) !== $table){
			$this->entity->setData(self::DATA_TABLE, $table);
			$this->entity->setData(self::DATA_TRADES, null);
			$this->entity->setData(self::DATA_ROLLED, null);
			$this->entity->setData(self::DATA_TIER, null);
		}
		$this->entity->setData(self::DATA_PLAYER, null);
		$this->ensureTrades();
		$this->syncMetadata();
	}

	public function onRemove() : void{
		$this->closeTrade();
	}

	public function getTier() : int{
		$tier = $this->entity->getData(self::DATA_TIER, 0);
		return is_int($tier) ? max(0, $tier) : 0;
	}

	public function getTraderExperience() : int{
		$xp = $this->entity->getData(self::DATA_XP, 0);
		return is_int($xp) ? max(0, $xp) : 0;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function getTrades() : array{
		$trades = $this->entity->getData(self::DATA_TRADES, []);
		if(!is_array($trades)){
			return [];
		}
		$result = [];
		foreach($trades as $trade){
			if(is_array($trade)){
				$result[] = $trade;
			}
		}
		return $result;
	}

	/**
	 * @param list<array<string, mixed>> $trades
	 */
	private function setTrades(array $trades) : void{
		$this->entity->setData(self::DATA_TRADES, $trades);
	}

	/**
	 * Rolls the trades of every unlocked tier not rolled yet.
	 */
	private function ensureTrades() : void{
		$table = $this->getTablePath();
		if($table === ""){
			return;
		}
		$requirements = TradeTable::tierRequirements($table);
		$xp = $this->getTraderExperience();
		$tier = $this->getTier();
		while(isset($requirements[$tier + 1]) && $xp >= $requirements[$tier + 1]){
			$tier++;
		}
		$this->entity->setData(self::DATA_TIER, $tier);
		$rolled = $this->entity->getData(self::DATA_ROLLED, []);
		$rolled = is_array($rolled) ? $rolled : [];
		$trades = $this->getTrades();
		$changed = false;
		for($index = 0; $index <= $tier && $index < count($requirements); $index++){
			if(isset($rolled[(string) $index])){
				continue;
			}
			$rolled[(string) $index] = true;
			foreach(TradeTable::rollTier($table, $index, $this->entity) as $trade){
				$trades[] = $trade;
			}
			$changed = true;
		}
		if($changed){
			$this->entity->setData(self::DATA_ROLLED, $rolled);
			$this->setTrades($trades);
		}
	}

	private function syncMetadata() : void{
		$requirements = TradeTable::tierRequirements($this->getTablePath());
		$this->entity->setMetadata(EntityMetadataProperties::TRADE_TIER, "int", $this->getTier());
		$this->entity->setMetadata(EntityMetadataProperties::MAX_TRADE_TIER, "int", max(0, count($requirements) - 1));
		$this->entity->setMetadata(EntityMetadataProperties::TRADE_XP, "int", $this->getTraderExperience());
		$discount = $this->config["cured_discount"] ?? null;
		if(is_array($discount) && ($this->entity->getData("cured", false) === true)){
			$this->entity->setMetadata(EntityMetadataProperties::LOW_TIER_CURED_TRADE_DISCOUNT, "int", (int) ($discount[0] ?? 0));
			$this->entity->setMetadata(EntityMetadataProperties::HIGH_TIER_CURED_TRADE_DISCOUNT, "int", (int) ($discount[1] ?? 0));
		}
	}

	public function getDisplayName() : string{
		$name = $this->config["display_name"] ?? null;
		if(is_string($name) && $name !== ""){
			return $name;
		}
		return $this->entity->getNameTag() !== "" ? $this->entity->getNameTag() : $this->entity->getName();
	}

	public function getTradingPlayer() : ?Player{
		$name = $this->entity->getData(self::DATA_PLAYER);
		if(!is_string($name)){
			return null;
		}
		$player = $this->entity->getWorld()->getServer()->getPlayerExact($name);
		if($player === null || !$player->isOnline()){
			$this->entity->setData(self::DATA_PLAYER, null);
			return null;
		}
		return $player;
	}

	/**
	 * Opens the trade screen to a player. Fails when the entity has no
	 * trades, is a baby, or already trades with someone else.
	 */
	public function openTrade(Player $player) : bool{
		if(!$this->entity->isAlive() || $this->entity->hasComponent("minecraft:is_baby")){
			return false;
		}
		$current = $this->getTradingPlayer();
		if($current !== null && $current !== $player){
			return false;
		}
		$this->ensureTrades();
		if($this->getTrades() === []){
			return false;
		}
		$this->syncMetadata();
		EntityInventory::registerOpener($player);
		$window = new TradeInventory($this, $player);
		$this->window = $window;
		$this->entity->setData(self::DATA_PLAYER, $player->getName());
		$this->entity->setMetadata(EntityMetadataProperties::TRADING_PLAYER_EID, "long", $player->getId());
		if(!$player->setCurrentWindow($window)){
			$this->window = null;
			$this->entity->setData(self::DATA_PLAYER, null);
			$this->entity->setMetadata(EntityMetadataProperties::TRADING_PLAYER_EID, "long", 0);
			return false;
		}
		$this->entity->getNavigator()->stop();
		$this->entity->lookAt($player->getEyePos());
		return true;
	}

	public function closeTrade() : void{
		$window = $this->window;
		$player = $this->getTradingPlayer();
		if($window !== null && $player !== null && $player->getCurrentWindow() === $window){
			$player->removeCurrentWindow();
		}
		$this->window = null;
		$this->entity->setData(self::DATA_PLAYER, null);
		$this->entity->setMetadata(EntityMetadataProperties::TRADING_PLAYER_EID, "long", 0);
	}

	/**
	 * Called by the trade window once the player closed it.
	 */
	public function onTradeWindowClosed(Player $player) : void{
		if($this->entity->getData(self::DATA_PLAYER) === $player->getName()){
			$this->window = null;
			$this->entity->setData(self::DATA_PLAYER, null);
			$this->entity->setMetadata(EntityMetadataProperties::TRADING_PLAYER_EID, "long", 0);
		}
	}

	public function getTradeWindow() : ?TradeInventory{
		return $this->window;
	}

	public function createTradePacket(Player $player, int $windowId) : UpdateTradePacket{
		return UpdateTradePacket::create(
			$windowId,
			WindowTypes::TRADING,
			0,
			$this->getTier(),
			$this->entity->getId(),
			$player->getId(),
			$this->getDisplayName(),
			($this->config["new_screen"] ?? true) !== false,
			true,
			new CacheableNbt($this->buildOffers())
		);
	}

	/**
	 * Builds the offers compound read by the trade screen.
	 */
	public function buildOffers() : CompoundTag{
		$recipes = [];
		$tier = $this->getTier();
		foreach($this->getTrades() as $index => $trade){
			$tradeTier = is_int($trade["tier"] ?? null) ? $trade["tier"] : 0;
			if($tradeTier > $tier){
				continue;
			}
			$buyA = ItemCodec::decode($trade["a"] ?? null);
			$sell = ItemCodec::decode($trade["sell"] ?? null);
			if($buyA->isNull() || $sell->isNull()){
				continue;
			}
			$recipe = CompoundTag::create()
				->setTag("buyA", $buyA->nbtSerialize())
				->setInt("buyCountA", $buyA->getCount())
				->setTag("sell", $sell->nbtSerialize())
				->setInt("maxUses", (int) ($trade["max_uses"] ?? 7))
				->setInt("uses", (int) ($trade["uses"] ?? 0))
				->setByte("rewardExp", ($trade["reward_exp"] ?? true) === true ? 1 : 0)
				->setInt("traderExp", (int) ($trade["trader_exp"] ?? 1))
				->setInt("tier", $tradeTier)
				->setInt("demand", (int) ($trade["demand"] ?? 0))
				->setFloat("priceMultiplierA", (float) ($trade["pma"] ?? 0.0))
				->setFloat("priceMultiplierB", (float) ($trade["pmb"] ?? 0.0))
				->setInt("netId", self::RECIPE_ID_BASE + $index);
			$buyB = ItemCodec::decode($trade["b"] ?? null);
			if(!$buyB->isNull()){
				$recipe->setTag("buyB", $buyB->nbtSerialize());
				$recipe->setInt("buyCountB", $buyB->getCount());
			}else{
				$recipe->setInt("buyCountB", 0);
			}
			$recipes[] = $recipe;
		}
		$requirements = [];
		foreach(TradeTable::tierRequirements($this->getTablePath()) as $index => $required){
			$requirements[] = CompoundTag::create()->setInt((string) $index, $required);
		}
		return CompoundTag::create()
			->setTag("Recipes", new ListTag($recipes, NBT::TAG_Compound))
			->setTag("TierExpRequirements", new ListTag($requirements, NBT::TAG_Compound));
	}

	/**
	 * Returns the index of the trade sent with a recipe network id.
	 */
	public static function tradeIndex(int $recipeId) : ?int{
		$index = $recipeId - self::RECIPE_ID_BASE;
		return $index >= 0 ? $index : null;
	}

	/**
	 * Performs a trade for a player: takes the wanted items from the trade
	 * slots then from the inventory of the player, gives the result, uses
	 * the stock and rewards the experience. Returns whether it happened.
	 */
	public function executeTrade(Player $player, int $index, int $repetitions = 1) : bool{
		$trades = $this->getTrades();
		$trade = $trades[$index] ?? null;
		if($trade === null || (is_int($trade["tier"] ?? null) ? $trade["tier"] : 0) > $this->getTier()){
			return false;
		}
		$maxUses = (int) ($trade["max_uses"] ?? 7);
		$uses = (int) ($trade["uses"] ?? 0);
		$repetitions = min(max(1, $repetitions), $maxUses - $uses);
		if($repetitions <= 0){
			return false;
		}
		$buyA = ItemCodec::decode($trade["a"] ?? null);
		$buyB = ItemCodec::decode($trade["b"] ?? null);
		$sell = ItemCodec::decode($trade["sell"] ?? null);
		if($buyA->isNull() || $sell->isNull()){
			return false;
		}
		$window = $this->window !== null && $player->getCurrentWindow() === $this->window ? $this->window : null;
		$done = 0;
		for($i = 0; $i < $repetitions; $i++){
			if(!$this->hasItems($player, $window, $buyA) || (!$buyB->isNull() && !$this->hasItems($player, $window, $buyB))){
				break;
			}
			$this->takeItems($player, $window, $buyA);
			if(!$buyB->isNull()){
				$this->takeItems($player, $window, $buyB);
			}
			foreach($player->getInventory()->addItem(clone $sell) as $left){
				$player->getWorld()->dropItem($player->getLocation(), $left);
			}
			$done++;
		}
		if($done === 0){
			return false;
		}
		$trade["uses"] = $uses + $done;
		$trades[$index] = $trade;
		$this->setTrades($trades);
		$this->entity->setData(self::DATA_XP, $this->getTraderExperience() + $done * (int) ($trade["trader_exp"] ?? 1));
		if(($trade["reward_exp"] ?? true) === true){
			$experience = 0;
			for($i = 0; $i < $done; $i++){
				$experience += 3 + mt_rand(0, 3);
			}
			$this->entity->getWorld()->dropExperience($this->entity->getPosition()->add(0, 0.5, 0), $experience);
		}
		$this->ensureTrades();
		$this->syncMetadata();
		$this->resendOffers($player);
		return true;
	}

	private function resendOffers(Player $player) : void{
		$window = $this->window;
		$manager = $player->getNetworkSession()->getInvManager();
		if($window === null || $manager === null){
			return;
		}
		$windowId = $manager->getWindowId($window);
		if($windowId !== null){
			$player->getNetworkSession()->sendDataPacket($this->createTradePacket($player, $windowId));
		}
	}

	private function countIn(Item $wanted, Item ...$items) : int{
		$count = 0;
		foreach($items as $item){
			if($item->equals($wanted, true, false)){
				$count += $item->getCount();
			}
		}
		return $count;
	}

	private function hasItems(Player $player, ?TradeInventory $window, Item $wanted) : bool{
		$count = $this->countIn($wanted, ...$player->getInventory()->getContents());
		if($window !== null){
			$count += $this->countIn($wanted, ...$window->getContents());
		}
		return $count >= $wanted->getCount();
	}

	private function takeItems(Player $player, ?TradeInventory $window, Item $wanted) : void{
		$left = $wanted->getCount();
		$inventories = $window !== null ? [$window, $player->getInventory()] : [$player->getInventory()];
		foreach($inventories as $inventory){
			foreach($inventory->getContents() as $slot => $item){
				if($left <= 0){
					return;
				}
				if(!$item->equals($wanted, true, false)){
					continue;
				}
				$taken = min($left, $item->getCount());
				$left -= $taken;
				$item->pop($taken);
				$inventory->setItem($slot, $item);
			}
		}
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if(($this->config["show_trade_screen"] ?? true) === false){
			return false;
		}
		return $this->openTrade($player);
	}

	public function tick(int $tickDiff) : void{
		if($this->entity->getData(self::DATA_PLAYER) === null){
			return;
		}
		$player = $this->getTradingPlayer();
		if($player === null){
			$this->closeTrade();
			return;
		}
		if($player->getWorld() !== $this->entity->getWorld() || $player->getPosition()->distanceSquared($this->entity->getPosition()) > self::MAX_TRADE_DISTANCE ** 2 || $player->getCurrentWindow() !== $this->window){
			$this->closeTrade();
			return;
		}
		$this->entity->lookAt($player->getEyePos());
	}

	public function onDeath() : void{
		$this->closeTrade();
	}
}
