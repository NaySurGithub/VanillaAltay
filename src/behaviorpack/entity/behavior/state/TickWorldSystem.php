<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\state;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\world\ChunkLoader;
use pocketmine\world\ChunkTicker;
use pocketmine\world\World;
use function floor;
use function max;
use function min;

/**
 * minecraft:tick_world: keeps the chunks around the entity loaded and ticking.
 * Unless "never_despawn" is set, the entity despawns when no player is
 * within "distance_to_players".
 */
final class TickWorldSystem extends EntitySystem{

	private ?ChunkLoader $loader = null;
	private ?ChunkTicker $ticker = null;
	private ?World $world = null;

	/** @var array<int, array{int, int}> */
	private array $chunks = [];

	private ?int $centerX = null;
	private ?int $centerZ = null;

	private int $checkTicks = 0;

	public function getRadius() : int{
		return max(2, min(6, (int) BehaviorEntity::toFloat($this->config["radius"] ?? null, 2.0)));
	}

	public function neverDespawns() : bool{
		return ($this->config["never_despawn"] ?? true) !== false;
	}

	public function getDistanceToPlayers() : float{
		return max(128.0, BehaviorEntity::toFloat($this->config["distance_to_players"] ?? null, 128.0));
	}

	public function onAdd() : void{
		$this->loader ??= new class implements ChunkLoader{
		};
		$this->ticker ??= new ChunkTicker();
		$this->centerX = null;
		$this->centerZ = null;
		$this->entity->setData("tick_world", [
			"radius" => $this->getRadius(),
			"never_despawn" => $this->neverDespawns(),
			"distance_to_players" => $this->getDistanceToPlayers()
		]);
		$this->updateChunks();
	}

	public function onRemove() : void{
		$this->releaseChunks();
		$this->entity->setData("tick_world", null);
	}

	public function onDeath() : void{
		$this->releaseChunks();
	}

	public function tick(int $tickDiff) : void{
		$this->updateChunks();
		if($this->neverDespawns()){
			return;
		}
		$this->checkTicks += $tickDiff;
		if($this->checkTicks < 20){
			return;
		}
		$this->checkTicks = 0;
		$limit = $this->getDistanceToPlayers() ** 2;
		$position = $this->entity->getPosition();
		foreach($this->entity->getWorld()->getPlayers() as $player){
			if($player->getPosition()->distanceSquared($position) <= $limit){
				return;
			}
		}
		$this->releaseChunks();
		$this->entity->flagForDespawn();
	}

	private function updateChunks() : void{
		if($this->loader === null || $this->ticker === null || $this->entity->isClosed()){
			return;
		}
		$world = $this->entity->getWorld();
		$position = $this->entity->getPosition();
		$chunkX = ((int) floor($position->x)) >> 4;
		$chunkZ = ((int) floor($position->z)) >> 4;
		if($world === $this->world && $chunkX === $this->centerX && $chunkZ === $this->centerZ){
			return;
		}
		$this->releaseChunks();
		$this->world = $world;
		$this->centerX = $chunkX;
		$this->centerZ = $chunkZ;
		$radius = $this->getRadius();
		for($x = $chunkX - $radius; $x <= $chunkX + $radius; ++$x){
			for($z = $chunkZ - $radius; $z <= $chunkZ + $radius; ++$z){
				$world->registerChunkLoader($this->loader, $x, $z, true);
				$world->registerTickingChunk($this->ticker, $x, $z);
				$this->chunks[World::chunkHash($x, $z)] = [$x, $z];
			}
		}
	}

	private function releaseChunks() : void{
		if($this->world !== null && $this->world->isLoaded() && $this->loader !== null && $this->ticker !== null){
			foreach($this->chunks as [$x, $z]){
				$this->world->unregisterTickingChunk($this->ticker, $x, $z);
				$this->world->unregisterChunkLoader($this->loader, $x, $z);
			}
		}
		$this->chunks = [];
		$this->world = null;
		$this->centerX = null;
		$this->centerZ = null;
	}
}
