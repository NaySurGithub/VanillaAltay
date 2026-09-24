<?php

declare(strict_types=1);

namespace dimension\portal;

use dimension\Dimensions;
use dimension\portal\item\EnderEye;
use pocketmine\block\BaseFire;
use pocketmine\block\EndPortalFrame;
use pocketmine\block\NetherPortal;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\block\BlockUpdateEvent;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\World;

/**
 * Nether and end portals: lighting nether portal frames with fire, keeping
 * lit portals standing only inside a complete frame, filling end portal
 * frames with eyes of ender and sending entities through portals.
 */
final class PortalListener implements Listener{

	private PortalTravel $travel;

	public function __construct(
		private PluginBase $plugin,
		private Dimensions $dimensions
	){
		$server = $plugin->getServer();
		PortalContent::register($server->getAsyncPool(), $server->getCraftingManager());
		$this->travel = new PortalTravel($dimensions);
		$plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
			$this->travel->tick($this->plugin->getServer());
		}), 1);
	}

	/**
	 * @param BlockUpdateEvent $event
	 * @priority LOW
	 */
	public function handleBlockUpdate(BlockUpdateEvent $event) : void{
		$block = $event->getBlock();
		$position = $block->getPosition();
		$world = $position->getWorld();
		$x = $position->getFloorX();
		$y = $position->getFloorY();
		$z = $position->getFloorZ();

		if($block instanceof BaseFire){
			if(!$this->canLightNetherPortal($world)){
				return;
			}
			$frame = NetherPortalFrame::find($world, $x, $y, $z);
			if($frame !== null){
				$frame->light();
				$event->cancel();
			}
		}elseif($block instanceof NetherPortal){
			if(!NetherPortalFrame::isPortalBlockSupported($world, $block, $x, $y, $z)){
				$world->setBlockAt($x, $y, $z, VanillaBlocks::AIR());
				$event->cancel();
			}
		}
	}

	/**
	 * @param PlayerInteractEvent $event
	 * @priority NORMAL
	 */
	public function handleInteract(PlayerInteractEvent $event) : void{
		if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){
			return;
		}
		$item = $event->getItem();
		$block = $event->getBlock();
		if(!$item instanceof EnderEye || !$block instanceof EndPortalFrame || $block->hasEye()){
			return;
		}
		$event->cancel();

		$player = $event->getPlayer();
		$position = $block->getPosition();
		$world = $position->getWorld();
		$world->setBlock($position, $block->setEye(true));
		$world->addSound($position->add(0.5, 0.5, 0.5), new EndPortalFrameFillSound());
		if(!$player->isCreative()){
			$item->pop();
			$player->getInventory()->setItemInHand($item);
		}

		if($this->dimensions->getWorld(DimensionIds::THE_END) !== null){
			EndPortalActivator::tryActivate($world, $position->getFloorX(), $position->getFloorY(), $position->getFloorZ(), $block->getFacing());
		}
	}

	/**
	 * @param EntityDespawnEvent $event
	 * @priority MONITOR
	 */
	public function handleEntityDespawn(EntityDespawnEvent $event) : void{
		$this->travel->forget($event->getEntity()->getId());
	}

	private function canLightNetherPortal(World $world) : bool{
		$dimension = $this->dimensions->getDimension($world);
		if($dimension !== DimensionIds::OVERWORLD && $dimension !== DimensionIds::NETHER){
			return false;
		}
		return $this->dimensions->getWorld(DimensionIds::NETHER) !== null && $this->dimensions->getWorld(DimensionIds::OVERWORLD) !== null;
	}
}
