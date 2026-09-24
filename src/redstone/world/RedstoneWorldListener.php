<?php

declare(strict_types=1);

namespace redstone\world;

use pocketmine\event\Listener;
use pocketmine\event\world\ChunkLoadEvent;
use pocketmine\event\world\ChunkUnloadEvent;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\event\world\WorldUnloadEvent;

final class RedstoneWorldListener implements Listener{

	public function __construct(
		readonly private RedstoneWorldManager $manager
	){}

	/**
	 * @param WorldLoadEvent $event
	 * @priority LOWEST
	 */
	public function onWorldLoad(WorldLoadEvent $event) : void{
		$this->manager->load($event->getWorld());
	}

	/**
	 * @param WorldUnloadEvent $event
	 * @priority MONITOR
	 */
	public function onWorldUnload(WorldUnloadEvent $event) : void{
		$this->manager->unload($event->getWorld());
	}

	/**
	 * @param ChunkLoadEvent $event
	 * @priority LOWEST
	 */
	public function onChunkLoad(ChunkLoadEvent $event) : void{
		$this->manager->get($event->getWorld())->loadChunk($event->getChunkX(), $event->getChunkZ());
	}

	/**
	 * @param ChunkUnloadEvent $event
	 * @priority MONITOR
	 */
	public function onChunkUnload(ChunkUnloadEvent $event) : void{
		$this->manager->getNullable($event->getWorld())?->unloadChunk($event->getChunkX(), $event->getChunkZ());
	}
}