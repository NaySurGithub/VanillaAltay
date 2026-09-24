<?php

declare(strict_types=1);

namespace behaviorpack\script;

use pocketmine\entity\Entity;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerEntityInteractEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\world\World;
use function is_string;

/**
 * Turns server events into script events. After-events are queued and
 * delivered on the next tick; before-events are dispatched synchronously so
 * that scripts can cancel them. Nothing is sent for events no script
 * subscribed to.
 */
final class ScriptEventListener{

	public function __construct(
		private ScriptLoader $loader,
		private ScriptValues $values,
		private ScriptStorage $storage
	){}

	public function register(PluginBase $plugin) : void{
		$manager = $plugin->getServer()->getPluginManager();
		$manager->registerEvent(PlayerJoinEvent::class, function(PlayerJoinEvent $event) : void{
			$this->onJoin($event);
		}, EventPriority::MONITOR, $plugin);
		$manager->registerEvent(PlayerQuitEvent::class, function(PlayerQuitEvent $event) : void{
			$this->onQuit($event);
		}, EventPriority::MONITOR, $plugin);
		$manager->registerEvent(PlayerRespawnEvent::class, function(PlayerRespawnEvent $event) : void{
			$this->onRespawn($event);
		}, EventPriority::MONITOR, $plugin);
		$manager->registerEvent(BlockBreakEvent::class, function(BlockBreakEvent $event) : void{
			$this->beforeBreak($event);
		}, EventPriority::HIGH, $plugin);
		$manager->registerEvent(BlockBreakEvent::class, function(BlockBreakEvent $event) : void{
			$this->afterBreak($event);
		}, EventPriority::MONITOR, $plugin);
		$manager->registerEvent(BlockPlaceEvent::class, function(BlockPlaceEvent $event) : void{
			$this->afterPlace($event);
		}, EventPriority::MONITOR, $plugin);
		$manager->registerEvent(PlayerInteractEvent::class, function(PlayerInteractEvent $event) : void{
			$this->beforeInteract($event);
		}, EventPriority::HIGH, $plugin);
		$manager->registerEvent(PlayerInteractEvent::class, function(PlayerInteractEvent $event) : void{
			$this->afterInteract($event);
		}, EventPriority::MONITOR, $plugin);
		$manager->registerEvent(PlayerEntityInteractEvent::class, function(PlayerEntityInteractEvent $event) : void{
			$this->afterEntityInteract($event);
		}, EventPriority::MONITOR, $plugin);
		$manager->registerEvent(PlayerItemUseEvent::class, function(PlayerItemUseEvent $event) : void{
			$this->beforeItemUse($event);
		}, EventPriority::HIGH, $plugin);
		$manager->registerEvent(PlayerItemUseEvent::class, function(PlayerItemUseEvent $event) : void{
			$this->afterItemUse($event);
		}, EventPriority::MONITOR, $plugin);
		$manager->registerEvent(EntityDamageEvent::class, function(EntityDamageEvent $event) : void{
			$this->afterHurt($event);
		}, EventPriority::MONITOR, $plugin);
		$manager->registerEvent(EntityDeathEvent::class, function(EntityDeathEvent $event) : void{
			$this->afterDeath($event);
		}, EventPriority::MONITOR, $plugin);
		$manager->registerEvent(EntitySpawnEvent::class, function(EntitySpawnEvent $event) : void{
			$this->afterSpawn($event);
		}, EventPriority::MONITOR, $plugin);
		$manager->registerEvent(EntityDespawnEvent::class, function(EntityDespawnEvent $event) : void{
			$entity = $event->getEntity();
			if(!$entity instanceof Player){
				$this->storage->forget("e:" . $entity->getId());
			}
		}, EventPriority::MONITOR, $plugin);
		$manager->registerEvent(PlayerChatEvent::class, function(PlayerChatEvent $event) : void{
			$this->beforeChat($event);
		}, EventPriority::HIGH, $plugin);
		$manager->registerEvent(PlayerChatEvent::class, function(PlayerChatEvent $event) : void{
			$this->afterChat($event);
		}, EventPriority::MONITOR, $plugin);
	}

	private function onJoin(PlayerJoinEvent $event) : void{
		$player = $event->getPlayer();
		if($this->loader->wants("after.playerJoin")){
			$this->loader->queueEvent("playerJoin", ["playerId" => (string) $player->getId(), "playerName" => $player->getName()]);
		}
		if($this->loader->wants("after.playerSpawn")){
			$this->loader->queueEvent("playerSpawn", ["player" => $this->values->entityRef($player), "initialSpawn" => true]);
		}
	}

	private function onQuit(PlayerQuitEvent $event) : void{
		$player = $event->getPlayer();
		if($this->loader->wants("before.playerLeave")){
			$this->loader->dispatchSync("playerLeave", ["player" => $this->values->entityRef($player)]);
		}
		if($this->loader->wants("after.playerLeave")){
			$this->loader->queueEvent("playerLeave", ["playerId" => (string) $player->getId(), "playerName" => $player->getName()]);
		}
		$this->loader->queueEvent("__quit", ["id" => $player->getId()]);
	}

	private function onRespawn(PlayerRespawnEvent $event) : void{
		if($this->loader->wants("after.playerSpawn")){
			$this->loader->queueEvent("playerSpawn", ["player" => $this->values->entityRef($event->getPlayer()), "initialSpawn" => false]);
		}
	}

	private function beforeBreak(BlockBreakEvent $event) : void{
		if(!$this->loader->wants("before.playerBreakBlock")){
			return;
		}
		$block = $event->getBlock();
		$done = $this->loader->dispatchSync("playerBreakBlock", [
			"player" => $this->values->entityRef($event->getPlayer()),
			"block" => $this->values->blockRef($block),
			"dimension" => $this->dimension($block->getPosition()->getWorld()),
			"itemStack" => $this->values->item($event->getItem())
		]);
		if(($done["c"] ?? false) === true){
			$event->cancel();
		}
	}

	private function afterBreak(BlockBreakEvent $event) : void{
		$block = $event->getBlock();
		$typeId = $this->values->blockTypeId($block);
		$wantsEvent = $this->loader->wants("after.playerBreakBlock");
		$wantsHook = $this->loader->hasBlockHook($typeId);
		if(!$wantsEvent && !$wantsHook){
			return;
		}
		$data = [
			"player" => $this->values->entityRef($event->getPlayer()),
			"block" => $this->values->blockRef($block),
			"dimension" => $this->dimension($block->getPosition()->getWorld()),
			"brokenBlockPermutation" => $this->values->permutation($block)
		];
		if($wantsHook){
			$this->loader->queueEvent("__comp", ["k" => "b", "h" => "onPlayerBreak", "t" => $typeId, "d" => $data]);
		}
		if($wantsEvent){
			$item = $this->values->item($event->getItem());
			$data["itemStackBeforeBreak"] = $item;
			$data["itemStackAfterBreak"] = $item;
			$this->loader->queueEvent("playerBreakBlock", $data);
		}
	}

	private function afterPlace(BlockPlaceEvent $event) : void{
		$wantsEvent = $this->loader->wants("after.playerPlaceBlock");
		$world = $event->getBlockAgainst()->getPosition()->getWorld();
		$dimension = $this->dimension($world);
		$player = $this->values->entityRef($event->getPlayer());
		foreach($event->getTransaction()->getBlocks() as [$x, $y, $z, $placed]){
			$ref = ["\$b" => [$x, $y, $z], "d" => $dimension["\$d"]];
			$typeId = $this->values->blockTypeId($placed);
			if($this->loader->hasBlockHook($typeId)){
				$this->loader->queueEvent("__comp", ["k" => "b", "h" => "onPlace", "t" => $typeId, "d" => [
					"block" => $ref,
					"dimension" => $dimension,
					"previousBlock" => $this->values->permutation($world->getBlockAt($x, $y, $z))
				]]);
			}
			if($wantsEvent){
				$this->loader->queueEvent("playerPlaceBlock", ["player" => $player, "block" => $ref, "dimension" => $dimension]);
			}
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function interactData(PlayerInteractEvent $event) : array{
		$block = $event->getBlock();
		return [
			"player" => $this->values->entityRef($event->getPlayer()),
			"block" => $this->values->blockRef($block),
			"blockFace" => $event->getFace(),
			"faceLocation" => $this->values->vectorOut($event->getTouchVector()),
			"itemStack" => $this->values->item($event->getItem()),
			"isFirstEvent" => true
		];
	}

	private function beforeInteract(PlayerInteractEvent $event) : void{
		if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK || !$this->loader->wants("before.playerInteractWithBlock")){
			return;
		}
		$done = $this->loader->dispatchSync("playerInteractWithBlock", $this->interactData($event));
		if(($done["c"] ?? false) === true){
			$event->cancel();
		}
	}

	private function afterInteract(PlayerInteractEvent $event) : void{
		if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){
			return;
		}
		$block = $event->getBlock();
		$blockTypeId = $this->values->blockTypeId($block);
		$itemTypeId = $event->getItem()->isNull() ? "" : $this->values->itemTypeId($event->getItem());
		$wantsEvent = $this->loader->wants("after.playerInteractWithBlock");
		$blockHook = $this->loader->hasBlockHook($blockTypeId);
		$itemHook = $itemTypeId !== "" && $this->loader->hasItemHook($itemTypeId);
		if(!$wantsEvent && !$blockHook && !$itemHook){
			return;
		}
		$data = $this->interactData($event);
		$dimension = $this->dimension($block->getPosition()->getWorld());
		if($blockHook){
			$this->loader->queueEvent("__comp", ["k" => "b", "h" => "onPlayerInteract", "t" => $blockTypeId, "d" => [
				"block" => $data["block"],
				"dimension" => $dimension,
				"player" => $data["player"],
				"face" => $data["blockFace"],
				"faceLocation" => $data["faceLocation"]
			]]);
		}
		if($itemHook){
			$this->loader->queueEvent("__comp", ["k" => "i", "h" => "onUseOn", "t" => $itemTypeId, "d" => [
				"itemStack" => $data["itemStack"],
				"source" => $data["player"],
				"block" => $data["block"],
				"blockFace" => $data["blockFace"],
				"faceLocation" => $data["faceLocation"],
				"usedOnBlockPermutation" => $this->values->permutation($block)
			]]);
		}
		if($wantsEvent){
			$this->loader->queueEvent("playerInteractWithBlock", $data);
		}
	}

	private function afterEntityInteract(PlayerEntityInteractEvent $event) : void{
		if(!$this->loader->wants("after.playerInteractWithEntity")){
			return;
		}
		$player = $event->getPlayer();
		$item = $this->values->item($player->getInventory()->getItemInHand());
		$this->loader->queueEvent("playerInteractWithEntity", [
			"player" => $this->values->entityRef($player),
			"target" => $this->values->entityRef($event->getEntity()),
			"itemStack" => $item,
			"beforeItemStack" => $item
		]);
	}

	private function beforeItemUse(PlayerItemUseEvent $event) : void{
		if($event->getItem()->isNull() || !$this->loader->wants("before.itemUse")){
			return;
		}
		$done = $this->loader->dispatchSync("itemUse", [
			"source" => $this->values->entityRef($event->getPlayer()),
			"itemStack" => $this->values->item($event->getItem())
		]);
		if(($done["c"] ?? false) === true){
			$event->cancel();
		}
	}

	private function afterItemUse(PlayerItemUseEvent $event) : void{
		$item = $event->getItem();
		if($item->isNull()){
			return;
		}
		$typeId = $this->values->itemTypeId($item);
		$wantsEvent = $this->loader->wants("after.itemUse");
		$wantsHook = $this->loader->hasItemHook($typeId);
		if(!$wantsEvent && !$wantsHook){
			return;
		}
		$data = [
			"source" => $this->values->entityRef($event->getPlayer()),
			"itemStack" => $this->values->item($item)
		];
		if($wantsHook){
			$this->loader->queueEvent("__comp", ["k" => "i", "h" => "onUse", "t" => $typeId, "d" => $data]);
		}
		if($wantsEvent){
			$this->loader->queueEvent("itemUse", $data);
		}
	}

	private function afterHurt(EntityDamageEvent $event) : void{
		if(!$this->loader->wants("after.entityHurt")){
			return;
		}
		$this->loader->queueEvent("entityHurt", [
			"hurtEntity" => $this->values->entityRef($event->getEntity()),
			"damage" => $event->getFinalDamage(),
			"damageSource" => $this->damageSource($event)
		]);
	}

	private function afterDeath(EntityDeathEvent $event) : void{
		if(!$this->loader->wants("after.entityDie")){
			return;
		}
		$entity = $event->getEntity();
		$cause = $entity->getLastDamageCause();
		$this->loader->queueEvent("entityDie", [
			"deadEntity" => $this->values->entityRef($entity),
			"damageSource" => $cause === null ? ["cause" => "none"] : $this->damageSource($cause)
		]);
	}

	private function afterSpawn(EntitySpawnEvent $event) : void{
		$entity = $event->getEntity();
		if($entity instanceof Player || !$this->loader->wants("after.entitySpawn")){
			return;
		}
		$this->loader->queueEvent("entitySpawn", ["entity" => $this->values->entityRef($entity), "cause" => "Spawned"]);
	}

	private function beforeChat(PlayerChatEvent $event) : void{
		if(!$this->loader->wants("before.chatSend")){
			return;
		}
		$done = $this->loader->dispatchSync("chatSend", [
			"sender" => $this->values->entityRef($event->getPlayer()),
			"message" => $event->getMessage()
		]);
		if(($done["c"] ?? false) === true){
			$event->cancel();
			return;
		}
		$message = $done["m"]["message"] ?? null;
		if(is_string($message)){
			$event->setMessage($message);
		}
	}

	private function afterChat(PlayerChatEvent $event) : void{
		if(!$this->loader->wants("after.chatSend")){
			return;
		}
		$this->loader->queueEvent("chatSend", [
			"sender" => $this->values->entityRef($event->getPlayer()),
			"message" => $event->getMessage()
		]);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function damageSource(EntityDamageEvent $event) : array{
		$source = ["cause" => ScriptApi::damageCauseName($event->getCause())];
		if($event instanceof EntityDamageByChildEntityEvent){
			$child = $event->getChild();
			if($child !== null){
				$source["damagingProjectile"] = $this->values->entityRef($child);
			}
		}
		if($event instanceof EntityDamageByEntityEvent){
			$damager = $event->getDamager();
			if($damager instanceof Entity){
				$source["damagingEntity"] = $this->values->entityRef($damager);
			}
		}
		return $source;
	}

	/**
	 * @return array{"$d": string}
	 */
	private function dimension(World $world) : array{
		return ["\$d" => $this->values->dimensionId($world)];
	}
}
