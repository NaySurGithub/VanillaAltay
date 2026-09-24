<?php

declare(strict_types=1);

namespace redstone\world;

use pocketmine\scheduler\ClosureTask;
use pocketmine\plugin\PluginBase;
use pocketmine\world\World;

final class RedstoneWorldManager{

	public static self $any;
	
	/** @var array<int, RedstoneWorld> */
	private array $worlds = [];

	public function __construct(PluginBase $plugin){
		self::$any = $this;
		$plugin->getServer()->getPluginManager()->registerEvents(new RedstoneWorldListener($this), $plugin);
		foreach($plugin->getServer()->getWorldManager()->getWorlds() as $world){
			$this->load($world);
		}
		$plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
			foreach($this->worlds as $world){
				$world->tick();
			}
		}), 1);
	}

	public function load(World $world) : void{
		if(isset($this->worlds[$world->getId()])){
			return;
		}
		$redstone_world = $this->worlds[$world->getId()] = new RedstoneWorld($world);
		foreach($world->getLoadedChunks() as $chunk_hash => $_){
			World::getXZ($chunk_hash, $chunkX, $chunkZ);
			$redstone_world->loadChunk($chunkX, $chunkZ);
		}
	}

	public function unload(World $world) : void{
		unset($this->worlds[$world->getId()]);
	}

	public function get(World $world) : RedstoneWorld{
		return $this->worlds[$world->getId()];
	}

	public function getNullable(World $world) : ?RedstoneWorld{
		return $this->worlds[$world->getId()] ?? null;
	}
}
