<?php

declare(strict_types=1);

namespace dimension\portal;

use Closure;
use dimension\Dimensions;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\NetherPortal;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;
use pocketmine\world\World;
use function count;
use function floor;
use function max;
use function min;

/**
 * Moves entities standing in portal blocks between dimensions.
 *
 * Nether portals send an entity after it spent the portal delay inside them
 * (a single tick in creative) and do not send it again until it has left
 * every portal block. End portals send an entity as soon as it enters them.
 */
final class PortalTravel{

	public const NETHER_PORTAL_DELAY = 80;
	public const NETHER_SCALE = 8;
	public const WORLD_LIMIT = 29999872;

	public const END_SPAWN_X = 100;
	public const END_SPAWN_Y = 49;
	public const END_SPAWN_Z = 0;

	/** @var array<int, int> */
	private array $netherPortalTicks = [];
	/** @var array<int, true> */
	private array $inEndPortal = [];
	/** @var array<int, true> */
	private array $pending = [];

	private int $endPortalTypeId;

	public function __construct(
		private Dimensions $dimensions
	){
		$this->endPortalTypeId = PortalContent::endPortal()->getTypeId();
	}

	public function tick(Server $server) : void{
		foreach($server->getWorldManager()->getWorlds() as $world){
			foreach($world->getEntities() as $entity){
				$this->tickEntity($entity);
			}
		}
	}

	public function forget(int $entityId) : void{
		unset($this->netherPortalTicks[$entityId], $this->inEndPortal[$entityId], $this->pending[$entityId]);
	}

	private function tickEntity(Entity $entity) : void{
		$id = $entity->getId();
		if(!$this->canTravel($entity)){
			$this->forget($id);
			return;
		}

		[$inNetherPortal, $inEndPortal] = $this->findPortals($entity);

		if($inEndPortal){
			if(!isset($this->inEndPortal[$id])){
				$this->inEndPortal[$id] = true;
				if(!isset($this->pending[$id]) && $entity->getVehicle() === null && count($entity->getPassengers()) === 0){
					$this->travelThroughEndPortal($entity);
				}
			}
		}else{
			unset($this->inEndPortal[$id]);
		}

		if($inNetherPortal){
			$ticks = $this->netherPortalTicks[$id] ?? 0;
			if($entity instanceof Player && $entity->isCreative() && $ticks < self::NETHER_PORTAL_DELAY){
				$ticks = self::NETHER_PORTAL_DELAY;
			}else{
				$ticks = min($ticks + 1, self::NETHER_PORTAL_DELAY + 1);
			}
			$this->netherPortalTicks[$id] = $ticks;
			if($ticks === self::NETHER_PORTAL_DELAY && !isset($this->pending[$id])){
				$this->travelThroughNetherPortal($entity);
			}
		}else{
			unset($this->netherPortalTicks[$id]);
		}
	}

	private function canTravel(Entity $entity) : bool{
		if($entity->isClosed() || $entity->isFlaggedForDespawn() || !$entity->isAlive()){
			return false;
		}
		if($entity instanceof Player){
			return $entity->isConnected() && $entity->spawned && !$entity->isSpectator();
		}
		return true;
	}

	/**
	 * @return bool[] whether the entity touches a nether portal and an end portal
	 * @phpstan-return array{bool, bool}
	 */
	private function findPortals(Entity $entity) : array{
		$world = $entity->getWorld();
		$box = $entity->getBoundingBox();
		$nether = false;
		$end = false;
		$minY = max((int) floor($box->minY + 0.001), $world->getMinY());
		$maxY = min((int) floor($box->maxY - 0.001), $world->getMaxY() - 1);
		for($x = (int) floor($box->minX + 0.001); $x <= (int) floor($box->maxX - 0.001); $x++){
			for($z = (int) floor($box->minZ + 0.001); $z <= (int) floor($box->maxZ - 0.001); $z++){
				for($y = $minY; $y <= $maxY; $y++){
					$block = $world->getBlockAt($x, $y, $z);
					if($block instanceof NetherPortal){
						$nether = true;
					}elseif($block->getTypeId() === $this->endPortalTypeId){
						$end = true;
					}
				}
			}
		}
		return [$nether, $end];
	}

	private function travelThroughNetherPortal(Entity $entity) : void{
		$from = $this->dimensions->getDimension($entity->getWorld());
		if($from === DimensionIds::OVERWORLD){
			$to = DimensionIds::NETHER;
		}elseif($from === DimensionIds::NETHER){
			$to = DimensionIds::OVERWORLD;
		}else{
			return;
		}
		if(!$this->dimensions->isEnabled($to)){
			return;
		}
		$target = $this->dimensions->getWorld($to);
		if($target === null || $target === $entity->getWorld()){
			return;
		}

		$position = $entity->getPosition();
		if($to === DimensionIds::NETHER){
			$x = (int) floor($position->x / self::NETHER_SCALE);
			$z = (int) floor($position->z / self::NETHER_SCALE);
		}else{
			$x = (int) floor($position->x) * self::NETHER_SCALE;
			$z = (int) floor($position->z) * self::NETHER_SCALE;
		}
		$x = max(-self::WORLD_LIMIT, min(self::WORLD_LIMIT, $x));
		$z = max(-self::WORLD_LIMIT, min(self::WORLD_LIMIT, $z));
		$this->populate($entity, $target, $x, $z, function() use($entity, $target, $to, $x, $z) : void{
			$y = NetherPortalLocator::findBaseY($target, $to, $x, $z);
			$portal = NetherPortalLocator::findNearest($target, $to, $x, $y, $z);
			if($portal !== null){
				$destination = $portal->add(0.5, 0, 0.5);
			}else{
				$destination = NetherPortalLocator::build($target, $x, $y, $z);
			}
			$this->netherPortalTicks[$entity->getId()] = self::NETHER_PORTAL_DELAY + 1;
			$entity->teleport(Position::fromObject($destination, $target));
		});
	}

	private function travelThroughEndPortal(Entity $entity) : void{
		$from = $this->dimensions->getDimension($entity->getWorld());
		if(!$this->dimensions->isEnabled(DimensionIds::THE_END)){
			return;
		}
		if($from === DimensionIds::OVERWORLD){
			$target = $this->dimensions->getWorld(DimensionIds::THE_END);
			if($target === null || $target === $entity->getWorld()){
				return;
			}
			$this->populate($entity, $target, self::END_SPAWN_X, self::END_SPAWN_Z, function() use($entity, $target) : void{
				$this->buildEndPlatform($target);
				$entity->teleport(new Location(self::END_SPAWN_X + 0.5, self::END_SPAWN_Y, self::END_SPAWN_Z + 0.5, $target, 90.0, 0.0));
			});
		}elseif($from === DimensionIds::THE_END){
			$overworld = $this->dimensions->getWorld(DimensionIds::OVERWORLD);
			if($overworld === null){
				return;
			}
			$spawn = $entity instanceof Player ? $entity->getSpawn() : $overworld->getSpawnLocation();
			if(!$spawn instanceof Position || !$spawn->isValid() || $this->dimensions->getDimension($spawn->getWorld()) !== DimensionIds::OVERWORLD){
				$spawn = $overworld->getSpawnLocation();
			}
			$spawnWorld = $spawn->getWorld();
			$this->populate($entity, $spawnWorld, $spawn->getFloorX(), $spawn->getFloorZ(), function() use($entity, $spawnWorld, $spawn) : void{
				$entity->teleport($spawnWorld->getSafeSpawn($spawn));
			});
		}
	}

	/**
	 * The obsidian platform entities arrive on in the end, with room to
	 * stand above it.
	 */
	private function buildEndPlatform(World $world) : void{
		$obsidian = VanillaBlocks::OBSIDIAN();
		$air = VanillaBlocks::AIR();
		for($x = self::END_SPAWN_X - 2; $x <= self::END_SPAWN_X + 2; $x++){
			for($z = self::END_SPAWN_Z - 2; $z <= self::END_SPAWN_Z + 2; $z++){
				if($world->getBlockAt($x, self::END_SPAWN_Y - 1, $z)->getTypeId() !== BlockTypeIds::OBSIDIAN){
					$world->setBlockAt($x, self::END_SPAWN_Y - 1, $z, $obsidian);
				}
				for($y = self::END_SPAWN_Y; $y <= self::END_SPAWN_Y + 2; $y++){
					if($world->getBlockAt($x, $y, $z)->getTypeId() !== BlockTypeIds::AIR){
						$world->setBlockAt($x, $y, $z, $air);
					}
				}
			}
		}
	}

	/**
	 * Runs the callback once the chunk holding the destination column is
	 * generated and populated, if the entity can still travel by then.
	 */
	private function populate(Entity $entity, World $world, int $x, int $z, Closure $callback) : void{
		$id = $entity->getId();
		$this->pending[$id] = true;
		$world->orderChunkPopulation($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE, null)->onCompletion(
			function() use($id, $entity, $world, $callback) : void{
				unset($this->pending[$id]);
				if(!$world->isLoaded() || !$this->canTravel($entity)){
					return;
				}
				$callback();
			},
			function() use($id) : void{
				unset($this->pending[$id]);
			}
		);
	}
}
