<?php

declare(strict_types=1);

namespace dimension\rule;

use dimension\Dimensions;
use pocketmine\block\BedBase;
use pocketmine\block\Sponge;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\Water;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockPreExplodeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerRespawnAnchorUseEvent;
use pocketmine\item\LiquidBucket;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\world\Explosion;
use pocketmine\world\Position;
use pocketmine\world\sound\FizzSound;
use pocketmine\world\World;

/**
 * Gameplay rules that depend on the dimension a world stands for:
 *
 * - beds explode when used in the nether or the end;
 * - respawn anchors set the spawn in the nether and explode elsewhere;
 * - water poured from a bucket in the nether evaporates;
 * - wet sponges placed in the nether dry at once;
 * - ice leaves no water in the nether, and lava flows faster and farther there.
 */
final class DimensionRulesListener implements Listener{

	private const BED_EXPLOSION_RADIUS = 5.0;

	public function __construct(
		private PluginBase $plugin,
		private Dimensions $dimensions
	){
		DimensionLookup::setDimensions($dimensions);
		BlockOverrides::register();
	}

	/**
	 * @priority HIGH
	 * @handleCancelled false
	 */
	public function onInteract(PlayerInteractEvent $event) : void{
		if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK || !$event->useBlock()){
			return;
		}
		$block = $event->getBlock();
		if(!$block instanceof BedBase){
			return;
		}
		$dimension = $this->dimensions->getDimension($block->getPosition()->getWorld());
		if($dimension !== DimensionIds::NETHER && $dimension !== DimensionIds::THE_END){
			return;
		}
		$event->cancel();
		$this->explodeBed($block, $event->getPlayer());
	}

	/**
	 * @priority HIGH
	 * @handleCancelled false
	 */
	public function onRespawnAnchorUse(PlayerRespawnAnchorUseEvent $event) : void{
		$world = $event->getBlock()->getPosition()->getWorld();
		if($this->dimensions->getDimension($world) === DimensionIds::NETHER){
			$event->setAction(PlayerRespawnAnchorUseEvent::ACTION_SET_SPAWN);
		}else{
			$event->setAction(PlayerRespawnAnchorUseEvent::ACTION_EXPLODE);
		}
	}

	/**
	 * @priority HIGH
	 * @handleCancelled false
	 */
	public function onBucketEmpty(PlayerBucketEmptyEvent $event) : void{
		$bucket = $event->getBucket();
		if(!$bucket instanceof LiquidBucket || !$bucket->getLiquid() instanceof Water){
			return;
		}
		$target = $event->getBlockClicked()->getPosition();
		$world = $target->getWorld();
		if(!$this->isNether($world)){
			return;
		}
		$event->cancel();

		$player = $event->getPlayer();
		if($player->hasFiniteResources()){
			$player->getInventory()->setItemInHand($event->getItem());
		}
		$this->playEvaporation($world, $target);
	}

	/**
	 * @priority HIGHEST
	 * @handleCancelled false
	 */
	public function onBlockPlace(BlockPlaceEvent $event) : void{
		$world = $event->getPlayer()->getWorld();
		if(!$this->isNether($world)){
			return;
		}
		$transaction = $event->getTransaction();
		foreach($transaction->getBlocks() as [$x, $y, $z, $block]){
			if($block instanceof Sponge && $block->isWet()){
				$dry = clone $block;
				$transaction->addBlockAt($x, $y, $z, $dry->setWet(false));
				$this->playEvaporation($world, new Vector3($x, $y, $z));
			}
		}
	}

	private function isNether(World $world) : bool{
		return $this->dimensions->getDimension($world) === DimensionIds::NETHER;
	}

	private function playEvaporation(World $world, Vector3 $block) : void{
		$center = $block->floor()->add(0.5, 0.5, 0.5);
		$world->addSound($center, new FizzSound());
		$world->addParticle($center, new EvaporateParticle());
	}

	private function explodeBed(BedBase $bed, Player $player) : void{
		$event = new BlockPreExplodeEvent($bed, self::BED_EXPLOSION_RADIUS, $player);
		$event->setIncendiary(true);
		$event->call();
		if($event->isCancelled()){
			return;
		}

		$world = $bed->getPosition()->getWorld();
		$other = $bed->getOtherHalf();
		$head = ($other === null || $bed->isHeadPart()) ? $bed : $other;
		$center = Position::fromObject($head->getPosition()->add(0.5, 0.5, 0.5), $world);

		foreach([$bed, $other] as $half){
			if($half === null){
				continue;
			}
			$world->getTile($half->getPosition())?->onBlockDestroyed();
			$world->setBlock($half->getPosition(), VanillaBlocks::AIR());
		}

		$explosion = new Explosion($center, $event->getRadius(), $bed);
		$explosion->setFireChance($event->getFireChance());
		if($event->isBlockBreaking()){
			$explosion->explodeA();
		}
		$explosion->explodeB();
	}
}
