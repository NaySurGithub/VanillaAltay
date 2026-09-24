<?php

declare(strict_types=1);

namespace redstone\world;

use Generator;
use InvalidArgumentException;
use pocketmine\block\Block;
use pocketmine\block\tile\Hopper as HopperTile;
use pocketmine\block\Opaque;
use pocketmine\math\Facing;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use RangeException;
use redstone\block\power\PowerSource;
use redstone\block\power\Waitable;
use redstone\block\RedstoneWire;
use redstone\block\utils\HelperUtils;
use function array_key_exists;

final class RedstoneWorld{

	public const WAITABLE_UPDATE_NORMAL_PRIORITY = 0;
	public const WAITABLE_UPDATE_LOW_PRIORITY = 1;

	public static function redstoneTicks(int $redstone_ticks) : int{
		return $redstone_ticks * 2;
	}

	readonly private Vector3TickMap $waitable_low_priority;
	readonly private Vector3TickMap $waitable_normal_priority;
	readonly private ObserverChunkListener $observer_listener;

	private int $tick = 0;

	/** @var array<int, RedstoneChunk|null> */
	private array $chunks = [];

	public function __construct(
		readonly public World $world
	){
		$this->waitable_low_priority = new Vector3TickMap();
		$this->waitable_normal_priority = new Vector3TickMap();
		$this->observer_listener = new ObserverChunkListener($world);
	}

	public function getTick() : int{
		return $this->tick;
	}

	public function tick() : void{
		$tick = ++$this->tick;
		foreach($this->getWaitableUpdateAt($tick) as [$x, $y, $z]){
			$block = $this->world->getBlockAt($x, $y, $z);
			if($block instanceof Waitable){
				$block->onRedstoneTickReceive();
			}
		}
	}

	public function scheduleWaitableUpdate(Block&Waitable $waitable, int $game_ticks, int $priority = self::WAITABLE_UPDATE_NORMAL_PRIORITY, bool $override = false) : void{
		$game_ticks >= 1 || throw new RangeException("game_ticks must be >= 1, got " . $game_ticks);
		$pos = $waitable->getPosition();
		$this->scheduleWaitableUpdateAt($pos->x, $pos->y, $pos->z, $this->tick + $game_ticks, $priority, $override);
	}

	public function scheduleWaitableUpdateAt(int $x, int $y, int $z, int $full_tick, int $priority, bool $override) : void{
		if($priority === self::WAITABLE_UPDATE_NORMAL_PRIORITY){
			$this->waitable_normal_priority->push($x, $y, $z, $full_tick, $override);
		}elseif($priority === self::WAITABLE_UPDATE_LOW_PRIORITY){
			$this->waitable_low_priority->push($x, $y, $z, $full_tick, $override);
		}else{
			throw new InvalidArgumentException("Unexpected priority: {$priority}");
		}
	}

	/**
	 * @param int $full_tick
	 * @return Generator<array{int, int, int}>
	 */
	private function getWaitableUpdateAt(int $full_tick) : Generator{
		yield from $this->waitable_normal_priority->pop($full_tick);
		yield from $this->waitable_low_priority->pop($full_tick);
	}

	public function loadChunk(int $chunkX, int $chunkZ) : void{
		$this->chunks[World::chunkHash($chunkX, $chunkZ)] = null;
		$this->world->registerChunkListener($this->observer_listener, $chunkX, $chunkZ);
		foreach($this->world->getChunk($chunkX, $chunkZ)?->getTiles() ?? [] as $tile){
			if($tile instanceof HopperTile){
				$this->world->scheduleDelayedBlockUpdate($tile->getPosition(), 1);
			}
		}
	}

	public function unloadChunk(int $chunkX, int $chunkZ) : void{
		unset($this->chunks[World::chunkHash($chunkX, $chunkZ)]);
		$this->world->unregisterChunkListener($this->observer_listener, $chunkX, $chunkZ);
	}

	public function getChunk(int $chunkX, int $chunkZ) : ?RedstoneChunk{
		$index = World::chunkHash($chunkX, $chunkZ);
		return array_key_exists($index, $this->chunks) ? ($this->chunks[$index] ??= new RedstoneChunk()) : null;
	}

	public function setExtraDataAt(int $x, int $y, int $z, BlockData $data) : void{
		$this->getChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE)?->setBlockData($x & Chunk::COORD_MASK, $y, $z & Chunk::COORD_MASK, $data);
	}

	public function removeExtraDataAt(int $x, int $y, int $z) : void{
		$this->getChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE)?->removeBlockData($x & Chunk::COORD_MASK, $y, $z & Chunk::COORD_MASK);
	}

	public function getExtraDataAt(int $x, int $y, int $z) : ?BlockData{
		return $this->getChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE)?->getBlockData($x & Chunk::COORD_MASK, $y, $z & Chunk::COORD_MASK);
	}

	/**
	 * Returns all power sources that have the potential to strongly power
	 * the opaque block.
	 *
	 * @param Block $block
	 * @param int $facing
	 * @param int|null $ignored_side
	 * @return Generator<PowerSource>
	 */
	public function getPotentiallyStronglyPoweringSources(Block $block, int $facing, ?int $ignored_side = null) : Generator{
		if($block instanceof PowerSource && !($block instanceof RedstoneWire) && $block->canPower($facing)){
			yield -1 => $block;
		}
		if($block instanceof Opaque){
			$block_pos = $block->getPosition();
			foreach(Facing::ALL as $side){
				if($side === $ignored_side){
					continue;
				}
				$source = HelperUtils::getBlockAtSide($block_pos, $side);
				if($source instanceof PowerSource && $source->canStronglyPower(Facing::opposite($side))){
					yield $side => $source;
				}
			}
		}
	}


	/**
	 * Returns all power sources that are strongly powering the opaque
	 * block.
	 *
	 * @param Block $block
	 * @param int $facing
	 * @param int|null $ignored_side
	 * @return Generator<PowerSource>
	 */
	public function getStronglyPoweringSources(Block $block, int $facing, ?int $ignored_side = null) : Generator{
		foreach($this->getPotentiallyStronglyPoweringSources($block, $facing, $ignored_side) as $side => $source){
			if($source->getPowerLevel() > 0){
				yield $side => $source;
			}
		}
	}

	/**
	 * Returns whether the opaque block is strongly powered.
	 *
	 * @param Block $block
	 * @param int $facing
	 * @param int|null $ignored_side
	 * @return bool
	 */
	public function isStronglyPowered(Block $block, int $facing, ?int $ignored_side = null) : bool{
		return $this->getStronglyPoweringSources($block, $facing, $ignored_side)->current() !== null;
	}
}