<?php

declare(strict_types=1);

namespace behaviorpack\script;

use behaviorpack\custom\BehaviorBlock;
use behaviorpack\entity\BehaviorEntity;
use behaviorpack\script\binding\ServerModule;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\block\Block;
use pocketmine\block\inventory\BlockInventory;
use pocketmine\entity\Entity;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockExplodeEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\entity\ProjectileHitBlockEvent;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerEntityInteractEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMissSwingEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\player\PlayerToggleSneakEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\event\world\ChunkLoadEvent;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\item\ConsumableItem;
use pocketmine\item\Item;
use pocketmine\item\Releasable;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\CameraPresetsPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\ServerboundDataDrivenScreenClosedPacket;
use pocketmine\network\mcpe\protocol\ServerboundDataStorePacket;
use pocketmine\network\mcpe\protocol\types\BoolDataStoreValue;
use pocketmine\network\mcpe\protocol\types\DoubleDataStoreValue;
use pocketmine\network\mcpe\protocol\types\StringDataStoreValue;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\inventory\ReleaseItemTransactionData;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\world\World;
use function count;
use function floor;
use function is_array;
use function is_string;
use function max;
use function spl_object_id;

/**
 * Turns server events into script events. After-events are queued and
 * delivered on the next tick; before-events are dispatched synchronously so
 * that scripts can cancel them. Nothing is sent for events no script
 * subscribed to.
 */
final class ScriptEventListener{

	private const INPUT_MODES = [1 => "KeyboardAndMouse", 2 => "Touch", 3 => "Gamepad", 4 => "MotionController"];

	/** @var array<int, Entity> */
	private array $pendingSpawns = [];

	/** @var array<int, true> */
	private array $loadedEntities = [];

	/** @var array<int, array{inventory: Inventory, type: string, block: Block|null}> */
	private array $openContainers = [];

	public function __construct(
		private ScriptLoader $loader,
		private ScriptValues $values,
		private ScriptStorage $storage,
		private ScriptPlayerState $state,
		private ScriptExtendedApi $extended
	){}

	public function register(PluginBase $plugin) : void{
		$manager = $plugin->getServer()->getPluginManager();
		$on = function(string $class, \Closure $handler, int $priority = EventPriority::MONITOR) use ($manager, $plugin) : void{
			$manager->registerEvent($class, $handler, $priority, $plugin);
		};
		$on(PlayerJoinEvent::class, fn(PlayerJoinEvent $event) => $this->onJoin($event));
		$on(PlayerQuitEvent::class, fn(PlayerQuitEvent $event) => $this->onQuit($event));
		$on(PlayerRespawnEvent::class, fn(PlayerRespawnEvent $event) => $this->onRespawn($event));
		$on(BlockBreakEvent::class, fn(BlockBreakEvent $event) => $this->beforeBreak($event), EventPriority::HIGH);
		$on(BlockBreakEvent::class, fn(BlockBreakEvent $event) => $this->afterBreak($event));
		$on(BlockPlaceEvent::class, fn(BlockPlaceEvent $event) => $this->beforePlace($event), EventPriority::HIGH);
		$on(BlockPlaceEvent::class, fn(BlockPlaceEvent $event) => $this->afterPlace($event));
		$on(PlayerInteractEvent::class, fn(PlayerInteractEvent $event) => $this->beforeInteract($event), EventPriority::HIGH);
		$on(PlayerInteractEvent::class, fn(PlayerInteractEvent $event) => $this->afterInteract($event));
		$on(PlayerEntityInteractEvent::class, fn(PlayerEntityInteractEvent $event) => $this->beforeEntityInteract($event), EventPriority::HIGH);
		$on(PlayerEntityInteractEvent::class, fn(PlayerEntityInteractEvent $event) => $this->afterEntityInteract($event));
		$on(PlayerItemUseEvent::class, fn(PlayerItemUseEvent $event) => $this->beforeItemUse($event), EventPriority::HIGH);
		$on(PlayerItemUseEvent::class, fn(PlayerItemUseEvent $event) => $this->afterItemUse($event));
		$on(PlayerItemConsumeEvent::class, fn(PlayerItemConsumeEvent $event) => $this->afterConsume($event));
		$on(EntityDamageEvent::class, fn(EntityDamageEvent $event) => $this->beforeHurt($event), EventPriority::HIGH);
		$on(EntityDamageEvent::class, fn(EntityDamageEvent $event) => $this->afterHurt($event));
		$on(EntityDeathEvent::class, fn(EntityDeathEvent $event) => $this->afterDeath($event));
		$on(EntitySpawnEvent::class, fn(EntitySpawnEvent $event) => $this->onSpawn($event));
		$on(ChunkLoadEvent::class, fn(ChunkLoadEvent $event) => $this->onChunkLoad($event));
		$on(EntityDespawnEvent::class, fn(EntityDespawnEvent $event) => $this->onDespawn($event));
		$on(PlayerChatEvent::class, fn(PlayerChatEvent $event) => $this->beforeChat($event), EventPriority::HIGH);
		$on(PlayerChatEvent::class, fn(PlayerChatEvent $event) => $this->afterChat($event));
		$on(ProjectileHitBlockEvent::class, fn(ProjectileHitBlockEvent $event) => $this->afterProjectileHitBlock($event));
		$on(ProjectileHitEntityEvent::class, fn(ProjectileHitEntityEvent $event) => $this->afterProjectileHitEntity($event));
		$on(EntityItemPickupEvent::class, fn(EntityItemPickupEvent $event) => $this->beforeItemPickup($event), EventPriority::HIGH);
		$on(EntityExplodeEvent::class, fn(EntityExplodeEvent $event) => $this->beforeEntityExplosion($event), EventPriority::HIGH);
		$on(BlockExplodeEvent::class, fn(BlockExplodeEvent $event) => $this->beforeBlockExplosion($event), EventPriority::HIGH);
		$on(EntityTeleportEvent::class, fn(EntityTeleportEvent $event) => $this->afterTeleport($event));
		$on(PlayerItemHeldEvent::class, fn(PlayerItemHeldEvent $event) => $this->afterItemHeld($event));
		$on(PlayerToggleSneakEvent::class, fn(PlayerToggleSneakEvent $event) => $this->afterToggleSneak($event));
		$on(PlayerMissSwingEvent::class, fn(PlayerMissSwingEvent $event) => $this->swing($event->getPlayer(), "Attack"));
		$on(PlayerDropItemEvent::class, fn(PlayerDropItemEvent $event) => $this->swing($event->getPlayer(), "DropItem"));
		$on(InventoryOpenEvent::class, fn(InventoryOpenEvent $event) => $this->onContainerOpen($event));
		$on(InventoryCloseEvent::class, fn(InventoryCloseEvent $event) => $this->onContainerClose($event));
		$on(DataPacketReceiveEvent::class, fn(DataPacketReceiveEvent $event) => $this->onPacketReceive($event));
		$on(DataPacketSendEvent::class, fn(DataPacketSendEvent $event) => $this->onPacketSend($event));
		$on(PlayerMoveEvent::class, fn(PlayerMoveEvent $event) => $this->afterMove($event));
		BehaviorBlock::$hookListener = function(string $hook, Block $block, array $extra) : void{
			$this->blockHook($hook, $block, $extra);
		};
		BehaviorEntity::$projectileHitListener = function(BehaviorEntity $projectile, ?Block $block, int $face, ?Entity $hit, Vector3 $position) : void{
			$this->customProjectileHit($projectile, $block, $face, $hit, $position);
		};
	}

	/**
	 * @param array<string, mixed> $extra
	 */
	private function blockHook(string $hook, Block $block, array $extra) : void{
		$typeId = $this->values->blockTypeId($block);
		if(!$this->loader->hasBlockHook($typeId)){
			return;
		}
		$data = [
			"block" => $this->values->blockRef($block),
			"dimension" => $this->dimension($block->getPosition()->getWorld())
		];
		foreach($extra as $key => $value){
			$data[$key] = $value instanceof Entity ? $this->values->entityRef($value) : $value;
		}
		$this->loader->queueEvent("__comp", ["k" => "b", "h" => $hook, "t" => $typeId, "d" => $data]);
	}

	private function afterMove(PlayerMoveEvent $event) : void{
		$from = $event->getFrom();
		$to = $event->getTo();
		$fromX = $from->getFloorX();
		$fromY = (int) floor($from->y - 0.2);
		$fromZ = $from->getFloorZ();
		$toX = $to->getFloorX();
		$toY = (int) floor($to->y - 0.2);
		$toZ = $to->getFloorZ();
		if($fromX === $toX && $fromY === $toY && $fromZ === $toZ){
			return;
		}
		$world = $to->getWorld();
		$player = $event->getPlayer();
		$old = $world->getBlockAt($fromX, $fromY, $fromZ);
		$new = $world->getBlockAt($toX, $toY, $toZ);
		if($old instanceof BehaviorBlock){
			$this->blockHook("onStepOff", $old, ["entity" => $player]);
		}
		if($new instanceof BehaviorBlock && $player->isOnGround()){
			$this->blockHook("onStepOn", $new, ["entity" => $player]);
		}
	}

	private function itemHook(string $hook, Item $item, array $data) : void{
		if($item->isNull()){
			return;
		}
		$typeId = $this->values->itemTypeId($item);
		if($this->loader->hasItemHook($typeId)){
			$this->loader->queueEvent("__comp", ["k" => "i", "h" => $hook, "t" => $typeId, "d" => $data]);
		}
	}

	private function customProjectileHit(BehaviorEntity $projectile, ?Block $block, int $face, ?Entity $hit, Vector3 $position) : void{
		$data = [
			"projectile" => $this->values->entityRef($projectile),
			"dimension" => $this->dimension($projectile->getWorld()),
			"location" => $this->values->vectorOut($position),
			"hitVector" => $this->values->vectorOut($projectile->getMotion()->lengthSquared() > 0 ? $projectile->getMotion()->normalize() : new Vector3(0, 0, 0))
		];
		$owner = $projectile->getProjectileOwner();
		if($owner !== null){
			$data["source"] = $this->values->entityRef($owner);
		}
		if($block !== null){
			if(!$this->loader->wants("after.projectileHitBlock")){
				return;
			}
			$blockPosition = $block->getPosition();
			$info = [
				"block" => $this->values->blockRef($block),
				"face" => ServerModule::DIRECTIONS[$face] ?? "Up",
				"faceLocation" => ["x" => $position->x - $blockPosition->x, "y" => $position->y - $blockPosition->y, "z" => $position->z - $blockPosition->z]
			];
			$data["getBlockHit"] = static fn() : array => $info;
			$this->loader->queueEvent("projectileHitBlock", $data);
			return;
		}
		if($hit !== null && $this->loader->wants("after.projectileHitEntity")){
			$info = ["entity" => $this->values->entityRef($hit)];
			$data["getEntityHit"] = static fn() : array => $info;
			$this->loader->queueEvent("projectileHitEntity", $data);
		}
	}

	/**
	 * Delivers the spawn events of the entities created during the tick,
	 * leaving out those that were loaded with their chunk.
	 */
	public function flush() : void{
		foreach($this->pendingSpawns as $id => $entity){
			if($entity->isClosed() || isset($this->loadedEntities[$id])){
				continue;
			}
			$this->loader->queueEvent("entitySpawn", ["entity" => $this->values->entityRef($entity), "cause" => "Spawned"]);
		}
		$this->pendingSpawns = [];
		$this->loadedEntities = [];
	}

	private function onPacketSend(DataPacketSendEvent $event) : void{
		$packets = $event->getPackets();
		$result = [];
		$found = false;
		foreach($packets as $packet){
			$result[] = $packet;
			if($packet instanceof StartGamePacket){
				$found = true;
				$result[] = CameraPresetsPacket::create(ScriptExtendedApi::cameraPresets());
			}
		}
		if($found){
			$event->setPackets($result);
		}
	}

	private function onPacketReceive(DataPacketReceiveEvent $event) : void{
		$packet = $event->getPacket();
		$player = $event->getOrigin()->getPlayer();
		if($player === null){
			return;
		}
		if($packet instanceof PlayerAuthInputPacket){
			$flags = $packet->getInputFlags();
			$this->state->setInput(
				$player->getId(),
				$flags->get(PlayerAuthInputFlags::JUMP_DOWN),
				$flags->get(PlayerAuthInputFlags::SNEAK_DOWN),
				new Vector2($packet->getMoveVecX(), $packet->getMoveVecZ()),
				self::INPUT_MODES[$packet->getInputMode()] ?? "KeyboardAndMouse"
			);
			return;
		}
		if($packet instanceof ServerboundDataStorePacket){
			$update = $packet->getUpdate();
			if($update->getName() === "minecraft" && $update->getProperty() === ScriptExtendedApi::DDUI_PROPERTY){
				$data = $update->getData();
				$value = $data instanceof BoolDataStoreValue || $data instanceof DoubleDataStoreValue || $data instanceof StringDataStoreValue ? $data->getValue() : null;
				$this->loader->queueEvent("__ddui", ["p" => $player->getId(), "path" => $update->getPath(), "v" => $value]);
			}
			return;
		}
		if($packet instanceof ServerboundDataDrivenScreenClosedPacket){
			$this->loader->queueEvent("__ddui", ["p" => $player->getId(), "closed" => $packet->getFormId(), "reason" => $packet->getCloseReason()]);
			return;
		}
		if($packet instanceof InventoryTransactionPacket){
			$data = $packet->trData;
			if($data instanceof ReleaseItemTransactionData && $data->getActionType() === ReleaseItemTransactionData::ACTION_RELEASE){
				$this->stopUsing($player, false);
			}
		}
	}

	private function onJoin(PlayerJoinEvent $event) : void{
		$player = $event->getPlayer();
		if($this->loader->wants("after.playerJoin")){
			$this->loader->queueEvent("playerJoin", ["playerId" => (string) $player->getId(), "playerName" => $player->getName()]);
		}
		if($this->loader->wants("after.playerSpawn")){
			$this->loader->queueEvent("playerSpawn", ["player" => $this->values->entityRef($player), "initialSpawn" => true]);
		}
		$player->getInventory()->getListeners()->add(new CallbackInventoryListener(function(Inventory $inventory, int $slot, Item $oldItem) use ($player) : void{
			$this->onInventoryChange($player, $inventory, $slot, $oldItem);
		}, function(Inventory $inventory, array $oldContents) use ($player) : void{
			foreach($oldContents as $slot => $oldItem){
				if(!$oldItem->equalsExact($inventory->getItem($slot))){
					$this->onInventoryChange($player, $inventory, $slot, $oldItem);
				}
			}
			for($slot = 0, $size = $inventory->getSize(); $slot < $size; ++$slot){
				if(!isset($oldContents[$slot]) && !$inventory->isSlotEmpty($slot)){
					$this->onInventoryChange($player, $inventory, $slot, \pocketmine\block\VanillaBlocks::AIR()->asItem());
				}
			}
		}));
		$this->extended->sendShapes($player);
	}

	private function onInventoryChange(Player $player, Inventory $inventory, int $slot, Item $oldItem) : void{
		if(!$this->loader->wants("after.playerInventoryItemChange") || !$player->isConnected()){
			return;
		}
		$this->loader->queueEvent("playerInventoryItemChange", [
			"player" => $this->values->entityRef($player),
			"beforeItemStack" => $this->values->item($oldItem),
			"itemStack" => $this->values->item($inventory->getItem($slot)),
			"inventoryType" => $slot < 9 ? "Hotbar" : "Inventory",
			"slot" => $slot
		]);
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
		$this->loader->queueEvent("__ddui", ["p" => $player->getId(), "quit" => true]);
		$this->state->forget($player->getId());
		unset($this->openContainers[$player->getId()]);
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
		$this->itemHook("onMineBlock", $event->getItem(), [
			"block" => $this->values->blockRef($block),
			"minedBlockPermutation" => $this->values->permutation($block),
			"itemStack" => $this->values->item($event->getItem()),
			"source" => $this->values->entityRef($event->getPlayer())
		]);
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

	private function beforePlace(BlockPlaceEvent $event) : void{
		$wants = $this->loader->wants("before.playerPlaceBlock");
		$against = $event->getBlockAgainst();
		$world = $against->getPosition()->getWorld();
		foreach($event->getTransaction()->getBlocks() as [$x, $y, $z, $placed]){
			$typeId = $this->values->blockTypeId($placed);
			if(!$this->loader->hasBlockHook($typeId)){
				continue;
			}
			$cancelled = $this->loader->dispatchComponentSync(["k" => "b", "h" => "beforeOnPlayerPlace", "t" => $typeId, "d" => [
				"block" => ["\$b" => [$x, $y, $z], "d" => $this->values->dimensionId($world)],
				"dimension" => $this->dimension($world),
				"player" => $this->values->entityRef($event->getPlayer()),
				"permutationToPlace" => $this->values->permutation($placed),
				"face" => 1
			]]);
			if($cancelled){
				$event->cancel();
				return;
			}
		}
		if(!$wants){
			return;
		}
		$world = $against->getPosition()->getWorld();
		$dimension = $this->dimension($world);
		foreach($event->getTransaction()->getBlocks() as [$x, $y, $z, $placed]){
			$face = 1;
			$delta = (new Vector3($x, $y, $z))->subtractVector($against->getPosition());
			foreach([[0, -1, 0, 0], [0, 1, 0, 1], [0, 0, -1, 2], [0, 0, 1, 3], [-1, 0, 0, 4], [1, 0, 0, 5]] as [$dx, $dy, $dz, $candidate]){
				if((int) $delta->x === $dx && (int) $delta->y === $dy && (int) $delta->z === $dz){
					$face = $candidate;
					break;
				}
			}
			$done = $this->loader->dispatchSync("playerPlaceBlock", [
				"player" => $this->values->entityRef($event->getPlayer()),
				"block" => ["\$b" => [$x, $y, $z], "d" => $dimension["\$d"]],
				"dimension" => $dimension,
				"face" => $face,
				"faceLocation" => ["x" => 0.5, "y" => 0.5, "z" => 0.5],
				"permutationBeingPlaced" => $this->values->permutation($placed)
			]);
			if(($done["c"] ?? false) === true){
				$event->cancel();
				return;
			}
		}
	}

	private function afterPlace(BlockPlaceEvent $event) : void{
		$wantsEvent = $this->loader->wants("after.playerPlaceBlock");
		$world = $event->getBlockAgainst()->getPosition()->getWorld();
		$dimension = $this->dimension($world);
		$player = $this->values->entityRef($event->getPlayer());
		$this->swing($event->getPlayer(), "Build");
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
		if($event->getAction() === PlayerInteractEvent::LEFT_CLICK_BLOCK){
			$this->swing($event->getPlayer(), "Mine");
			return;
		}
		if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){
			return;
		}
		$this->swing($event->getPlayer(), "Interact");
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

	private function beforeEntityInteract(PlayerEntityInteractEvent $event) : void{
		if(!$this->loader->wants("before.playerInteractWithEntity")){
			return;
		}
		$player = $event->getPlayer();
		$done = $this->loader->dispatchSync("playerInteractWithEntity", [
			"player" => $this->values->entityRef($player),
			"target" => $this->values->entityRef($event->getEntity()),
			"itemStack" => $this->values->item($player->getInventory()->getItemInHand())
		]);
		if(($done["c"] ?? false) === true){
			$event->cancel();
		}
	}

	private function afterEntityInteract(PlayerEntityInteractEvent $event) : void{
		$this->swing($event->getPlayer(), "Interact");
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
		$this->swing($event->getPlayer(), "Use");
		$this->startUsing($event->getPlayer(), $item);
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

	/**
	 * Returns how long an item takes to use, in ticks, or null when it is
	 * used instantly.
	 */
	private function useDuration(Item $item) : ?int{
		$custom = $this->loader->useDuration($this->values->itemTypeId($item));
		if($custom !== null){
			return $custom;
		}
		if($item instanceof ConsumableItem){
			return 32;
		}
		if($item instanceof Releasable){
			return 72000;
		}
		return null;
	}

	private function startUsing(Player $player, Item $item) : void{
		$duration = $this->useDuration($item);
		if($duration === null || $this->state->isUsing($player->getId())){
			return;
		}
		$data = $this->values->item($item);
		$this->state->startUsing($player->getId(), $data, $player->getServer()->getTick(), $duration);
		if($this->loader->wants("after.itemStartUse")){
			$this->loader->queueEvent("itemStartUse", [
				"source" => $this->values->entityRef($player),
				"itemStack" => $data,
				"useDuration" => $duration
			]);
		}
	}

	private function stopUsing(Player $player, bool $completed) : void{
		$using = $this->state->stopUsing($player->getId());
		if($using === null){
			return;
		}
		$remaining = max(0, $using["duration"] - ($player->getServer()->getTick() - $using["tick"]));
		$data = [
			"source" => $this->values->entityRef($player),
			"itemStack" => $using["item"],
			"useDuration" => $remaining
		];
		if($completed){
			if(is_array($using["item"])){
				try{
					$this->itemHook("onCompleteUse", $this->values->decodeItem($using["item"]), ["itemStack" => $using["item"], "source" => $data["source"]]);
				}catch(ScriptException){
				}
			}
			if($this->loader->wants("after.itemCompleteUse")){
				$this->loader->queueEvent("itemCompleteUse", $data);
			}
			return;
		}
		if($this->loader->wants("after.itemStopUse")){
			$this->loader->queueEvent("itemStopUse", $data);
		}
	}

	private function afterConsume(PlayerItemConsumeEvent $event) : void{
		if($event->isCancelled()){
			return;
		}
		$player = $event->getPlayer();
		$this->itemHook("onConsume", $event->getItem(), ["itemStack" => $this->values->item($event->getItem()), "source" => $this->values->entityRef($player)]);
		if(!$this->state->isUsing($player->getId())){
			$this->state->startUsing($player->getId(), $this->values->item($event->getItem()), $player->getServer()->getTick(), 0);
		}
		$this->stopUsing($player, true);
	}

	private function beforeHurt(EntityDamageEvent $event) : void{
		if(!$this->loader->wants("before.entityHurt")){
			return;
		}
		$final = $event->getFinalDamage();
		$done = $this->loader->dispatchSync("entityHurt", [
			"hurtEntity" => $this->values->entityRef($event->getEntity()),
			"damage" => $final,
			"damageSource" => $this->damageSource($event)
		]);
		if($done === null){
			return;
		}
		if(($done["c"] ?? false) === true){
			$event->cancel();
			return;
		}
		$damage = $done["m"]["damage"] ?? null;
		if(is_int($damage) || \is_float($damage)){
			$event->setBaseDamage(max(0.0, $event->getBaseDamage() + ((float) $damage - $final)));
		}
	}

	private function afterHurt(EntityDamageEvent $event) : void{
		if($event instanceof EntityDamageByEntityEvent && !$event instanceof EntityDamageByChildEntityEvent && $event->getCause() === EntityDamageEvent::CAUSE_ENTITY_ATTACK){
			$damager = $event->getDamager();
			if($damager !== null){
				if($damager instanceof Player){
					$this->swing($damager, "Attack");
					$held = $damager->getInventory()->getItemInHand();
					$this->itemHook("onHitEntity", $held, [
						"attackingEntity" => $this->values->entityRef($damager),
						"hitEntity" => $this->values->entityRef($event->getEntity()),
						"hadEffect" => true,
						"itemStack" => $this->values->item($held)
					]);
				}
				if($this->loader->wants("after.entityHitEntity")){
					$this->loader->queueEvent("entityHitEntity", [
						"damagingEntity" => $this->values->entityRef($damager),
						"hitEntity" => $this->values->entityRef($event->getEntity())
					]);
				}
			}
		}
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

	private function onSpawn(EntitySpawnEvent $event) : void{
		$entity = $event->getEntity();
		if($entity instanceof Player || !$this->loader->wants("after.entitySpawn")){
			return;
		}
		$this->pendingSpawns[$entity->getId()] = $entity;
	}

	private function onChunkLoad(ChunkLoadEvent $event) : void{
		$wantsLoad = $this->loader->wants("after.entityLoad");
		foreach($event->getWorld()->getChunkEntities($event->getChunkX(), $event->getChunkZ()) as $entity){
			if($entity instanceof Player){
				continue;
			}
			$this->loadedEntities[$entity->getId()] = true;
			if($wantsLoad){
				$this->loader->queueEvent("entityLoad", ["entity" => $this->values->entityRef($entity)]);
			}
		}
	}

	private function onDespawn(EntityDespawnEvent $event) : void{
		$entity = $event->getEntity();
		unset($this->pendingSpawns[$entity->getId()]);
		if($entity instanceof Player){
			return;
		}
		$ref = $this->values->entityRef($entity);
		if($this->loader->wants("before.entityRemove")){
			$this->loader->dispatchSync("entityRemove", ["removedEntity" => $ref]);
		}
		if($this->loader->wants("after.entityRemove")){
			$this->loader->queueEvent("entityRemove", ["removedEntityId" => (string) $entity->getId(), "typeId" => $ref["t"]]);
		}
		$this->storage->forget("e:" . $entity->getId());
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
	private function projectileData(Projectile $projectile, Vector3 $hit) : array{
		$data = [
			"projectile" => $this->values->entityRef($projectile),
			"dimension" => $this->dimension($projectile->getWorld()),
			"location" => $this->values->vectorOut($hit),
			"hitVector" => $this->values->vectorOut($projectile->getMotion()->lengthSquared() > 0 ? $projectile->getMotion()->normalize() : new Vector3(0, 0, 0))
		];
		$owner = $projectile->getOwningEntity();
		if($owner !== null){
			$data["source"] = $this->values->entityRef($owner);
		}
		return $data;
	}

	private function afterProjectileHitBlock(ProjectileHitBlockEvent $event) : void{
		if(!$this->loader->wants("after.projectileHitBlock")){
			return;
		}
		$projectile = $event->getEntity();
		$result = $event->getRayTraceResult();
		$block = $event->getBlockHit();
		$position = $block->getPosition();
		$hit = $result->getHitVector();
		$data = $this->projectileData($projectile, $hit);
		$info = [
			"block" => $this->values->blockRef($block),
			"face" => ServerModule::DIRECTIONS[$result->getHitFace()] ?? "Up",
			"faceLocation" => ["x" => $hit->x - $position->x, "y" => $hit->y - $position->y, "z" => $hit->z - $position->z]
		];
		$data["getBlockHit"] = static fn() : array => $info;
		$this->loader->queueEvent("projectileHitBlock", $data);
	}

	private function afterProjectileHitEntity(ProjectileHitEntityEvent $event) : void{
		if(!$this->loader->wants("after.projectileHitEntity")){
			return;
		}
		$data = $this->projectileData($event->getEntity(), $event->getRayTraceResult()->getHitVector());
		$info = ["entity" => $this->values->entityRef($event->getEntityHit())];
		$data["getEntityHit"] = static fn() : array => $info;
		$this->loader->queueEvent("projectileHitEntity", $data);
	}

	private function beforeItemPickup(EntityItemPickupEvent $event) : void{
		if(!$this->loader->wants("before.entityItemPickup")){
			return;
		}
		$done = $this->loader->dispatchSync("entityItemPickup", [
			"entity" => $this->values->entityRef($event->getEntity()),
			"item" => $this->values->entityRef($event->getOrigin())
		]);
		if(($done["c"] ?? false) === true){
			$event->cancel();
		}
	}

	/**
	 * Dispatches the explosion before-event with the impacted blocks, which
	 * scripts can read and replace.
	 *
	 * @param list<Block> $blocks
	 * @return list<Block>|null the new block list, or null when cancelled
	 */
	private function explosion(World $world, ?Entity $source, array $blocks) : ?array{
		$dimension = $this->dimension($world);
		$impacted = $blocks;
		$data = [
			"dimension" => $dimension,
			"getImpactedBlocks" => function() use (&$impacted) : array{
				$refs = [];
				foreach($impacted as $block){
					$refs[] = $this->values->blockRef($block);
				}
				return $refs;
			},
			"setImpactedBlocks" => function(mixed $list) use (&$impacted, $world) : null{
				$result = [];
				foreach(is_array($list) ? $list : [] as $ref){
					if(is_array($ref) && is_array($ref["\$b"] ?? null) && count($ref["\$b"]) === 3){
						$result[] = $world->getBlockAt((int) $ref["\$b"][0], (int) $ref["\$b"][1], (int) $ref["\$b"][2]);
					}
				}
				$impacted = $result;
				return null;
			}
		];
		if($source !== null){
			$data["source"] = $this->values->entityRef($source);
		}
		$done = $this->loader->dispatchSync("explosion", $data);
		if(($done["c"] ?? false) === true){
			return null;
		}
		return $impacted;
	}

	private function beforeEntityExplosion(EntityExplodeEvent $event) : void{
		if(!$this->loader->wants("before.explosion")){
			return;
		}
		$blocks = $this->explosion($event->getPosition()->getWorld(), $event->getEntity(), $event->getBlockList());
		if($blocks === null){
			$event->cancel();
			return;
		}
		$event->setBlockList($blocks);
	}

	private function beforeBlockExplosion(BlockExplodeEvent $event) : void{
		if(!$this->loader->wants("before.explosion")){
			return;
		}
		$blocks = $this->explosion($event->getPosition()->getWorld(), null, $event->getAffectedBlocks());
		if($blocks === null){
			$event->cancel();
			return;
		}
		$event->setAffectedBlocks($blocks);
	}

	private function afterTeleport(EntityTeleportEvent $event) : void{
		$entity = $event->getEntity();
		if(!$entity instanceof Player){
			return;
		}
		$from = $event->getFrom();
		$to = $event->getTo();
		if($from->getWorld() === $to->getWorld()){
			return;
		}
		$this->extended->sendShapes($entity);
		if(!$this->loader->wants("after.playerDimensionChange")){
			return;
		}
		$this->loader->queueEvent("playerDimensionChange", [
			"player" => $this->values->entityRef($entity),
			"fromDimension" => $this->dimension($from->getWorld()),
			"fromLocation" => $this->values->vectorOut($from),
			"toDimension" => $this->dimension($to->getWorld()),
			"toLocation" => $this->values->vectorOut($to)
		]);
	}

	private function afterItemHeld(PlayerItemHeldEvent $event) : void{
		$player = $event->getPlayer();
		$previous = $player->getInventory()->getHeldItemIndex();
		if($previous !== $event->getSlot()){
			$this->stopUsing($player, false);
		}
		if(!$this->loader->wants("after.playerHotbarSelectedSlotChange") || $previous === $event->getSlot()){
			return;
		}
		$this->loader->queueEvent("playerHotbarSelectedSlotChange", [
			"player" => $this->values->entityRef($player),
			"previousSlotSelected" => $previous,
			"newSlotSelected" => $event->getSlot(),
			"itemStack" => $this->values->item($event->getItem())
		]);
	}

	private function afterToggleSneak(PlayerToggleSneakEvent $event) : void{
		if(!$event->isSneaking() || !$this->loader->wants("after.entityStartSneaking")){
			return;
		}
		$this->loader->queueEvent("entityStartSneaking", ["entity" => $this->values->entityRef($event->getPlayer())]);
	}

	private function swing(Player $player, string $source) : void{
		if(!$this->loader->wants("after.playerSwingStart")){
			return;
		}
		$this->loader->queueEvent("playerSwingStart", [
			"player" => $this->values->entityRef($player),
			"heldItemStack" => $this->values->item($player->getInventory()->getItemInHand()),
			"swingSource" => $source
		]);
	}

	private function onContainerOpen(InventoryOpenEvent $event) : void{
		$player = $event->getPlayer();
		$inventory = $event->getInventory();
		if($inventory === $player->getInventory() || $inventory === $player->getOffHandInventory() || $inventory === $player->getArmorInventory() || $inventory === $player->getCursorInventory()){
			return;
		}
		$source = ["entity" => $this->values->entityRef($player)];
		if($inventory instanceof BlockInventory){
			$holder = $inventory->getHolder();
			$block = $holder->getWorld()->getBlock($holder);
			$this->openContainers[$player->getId()] = ["inventory" => $inventory, "type" => "block", "block" => $block];
			if($this->loader->wants("after.blockContainerOpened")){
				$this->loader->queueEvent("blockContainerOpened", [
					"block" => $this->values->blockRef($block),
					"dimension" => $this->dimension($holder->getWorld()),
					"openSource" => $source
				]);
			}
			return;
		}
		$this->openContainers[$player->getId()] = ["inventory" => $inventory, "type" => "entity", "block" => null];
		if($this->loader->wants("after.entityContainerOpened")){
			$this->loader->queueEvent("entityContainerOpened", [
				"entity" => $this->values->entityRef($player),
				"openSource" => $source
			]);
		}
	}

	private function onContainerClose(InventoryCloseEvent $event) : void{
		$player = $event->getPlayer();
		$open = $this->openContainers[$player->getId()] ?? null;
		if($open === null || $open["inventory"] !== $event->getInventory()){
			return;
		}
		unset($this->openContainers[$player->getId()]);
		$source = ["entity" => $this->values->entityRef($player)];
		if($open["type"] === "block" && $open["block"] !== null){
			if($this->loader->wants("after.blockContainerClosed")){
				$this->loader->queueEvent("blockContainerClosed", [
					"block" => $this->values->blockRef($open["block"]),
					"dimension" => $this->dimension($open["block"]->getPosition()->getWorld()),
					"closeSource" => $source
				]);
			}
			return;
		}
		if($this->loader->wants("after.entityContainerClosed")){
			$this->loader->queueEvent("entityContainerClosed", [
				"entity" => $this->values->entityRef($player),
				"closeSource" => $source
			]);
		}
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
