<?php

declare(strict_types=1);

namespace dimension\generator\end;

use dimension\Dimensions;
use dimension\generator\end\populator\ObsidianPillarPopulator;
use pocketmine\entity\Location;
use pocketmine\entity\object\EndCrystal;
use pocketmine\event\Listener;
use pocketmine\event\world\ChunkPopulateEvent;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\utils\Random;

/**
 * Places the end crystal on top of each obsidian spike. Spikes are built on
 * the generation threads, which cannot create entities, so the crystal of a
 * spike is spawned on the main thread once the chunk that builds it has been
 * populated.
 */
final class EndCrystalSpawner implements Listener{

	private ?int $seed = null;

	/** @var list<array{chunkX: int, chunkZ: int, position: \pocketmine\math\Vector3}> */
	private array $spawns = [];

	public function __construct(
		private Dimensions $dimensions
	){}

	/**
	 * @priority MONITOR
	 */
	public function onChunkPopulate(ChunkPopulateEvent $event) : void{
		$world = $event->getWorld();
		if($this->dimensions->getDimension($world) !== DimensionIds::THE_END){
			return;
		}
		if($this->seed !== $world->getSeed()){
			$this->seed = $world->getSeed();
			$this->spawns = ObsidianPillarPopulator::crystalSpawns($this->seed);
		}
		foreach($this->spawns as $spawn){
			if($spawn["chunkX"] !== $event->getChunkX() || $spawn["chunkZ"] !== $event->getChunkZ()){
				continue;
			}
			$yaw = (new Random($this->seed ^ ($spawn["chunkX"] * 31 + $spawn["chunkZ"])))->nextFloat() * 360;
			$crystal = new EndCrystal(Location::fromObject($spawn["position"], $world, $yaw, 0.0));
			$crystal->setShowBase(true);
			$crystal->spawnToAll();
		}
	}
}
