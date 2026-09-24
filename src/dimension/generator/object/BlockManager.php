<?php

declare(strict_types=1);

namespace dimension\generator\object;

use dimension\generator\Blocks;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use function array_values;
use function count;
use function max;
use function min;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

/**
 * Queues block changes over a ChunkManager during population. Reads return
 * the block queued at that position if any, else the world block (world
 * reads are cached). Nothing reaches the world until apply().
 *
 * Blocks can be given as Block objects or as Bedrock identifiers such as
 * "minecraft:glowstone".
 */
class BlockManager{

	/** @var array<int, Block> */
	private array $caches = [];
	/** @var array<int, PlacedBlock> */
	private array $places = [];
	/** @var list<\Closure> */
	protected array $hooks = [];

	public function __construct(
		private ChunkManager $world
	){}

	private static function hashXYZ(int $x, int $y, int $z) : int{
		return ((($x + 30000000) & 0x3FFFFFF) << 37)
			| ((($z + 30000000) & 0x3FFFFFF) << 11)
			| ((($y + 400) & 0x3FF) << 1);
	}

	/**
	 * Registers a callback run by apply() once the blocks are written. The
	 * callback receives the ChunkManager.
	 */
	public function addHook(\Closure $hook) : void{
		$this->hooks[] = $hook;
	}

	/**
	 * @return list<\Closure>
	 */
	public function getHooks() : array{
		return $this->hooks;
	}

	public function getBlockIdIfCachedOrLoaded(int $x, int $y, int $z) : string{
		return Blocks::id($this->getBlockIfCachedOrLoaded($x, $y, $z));
	}

	/**
	 * Bedrock identifier of the block at this position, like "minecraft:air".
	 */
	public function getBlockIdAt(int $x, int $y, int $z) : string{
		$cached = $this->caches[self::hashXYZ($x, $y, $z)] ?? null;
		if($cached !== null){
			return Blocks::id($cached);
		}
		return Blocks::id($this->world->getBlockAt($x, $y, $z));
	}

	/**
	 * The queued or cached block, else the world block when its chunk is
	 * available, else $fallback (air by default).
	 */
	public function getBlockIfCachedOrLoaded(int $x, int $y, int $z, ?Block $fallback = null) : Block{
		$cached = $this->caches[self::hashXYZ($x, $y, $z)] ?? null;
		if($cached !== null){
			return $cached;
		}
		if($this->world->getChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE) !== null){
			return $this->getBlockAt($x, $y, $z);
		}
		return $fallback ?? VanillaBlocks::AIR();
	}

	public function getBlockAt(int $x, int $y, int $z) : Block{
		return $this->caches[self::hashXYZ($x, $y, $z)] ??= $this->world->getBlockAt($x, $y, $z);
	}

	public function getBlockAtVector(Vector3 $pos) : Block{
		return $this->getBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
	}

	/**
	 * Same as getBlockAt(): the block holds its full state.
	 */
	public function getBlockStateAt(int $x, int $y, int $z) : Block{
		return $this->getBlockAt($x, $y, $z);
	}

	/**
	 * The queued or cached block, else $fallback (air by default). Never reads the world.
	 */
	public function getCachedBlock(int $x, int $y, int $z, ?Block $fallback = null) : Block{
		return $this->caches[self::hashXYZ($x, $y, $z)] ?? $fallback ?? VanillaBlocks::AIR();
	}

	public function setBlockStateAt(int $x, int $y, int $z, Block|string $state) : void{
		$block = $state instanceof Block ? $state : Blocks::get($state);
		$hash = self::hashXYZ($x, $y, $z);
		$this->places[$hash] = new PlacedBlock($x, $y, $z, $block);
		$this->caches[$hash] = $block;
	}

	public function setBlockStateAtVector(Vector3 $pos, Block|string $state) : void{
		$this->setBlockStateAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ(), $state);
	}

	/**
	 * Queues the block only when nothing was queued or read at this position yet.
	 */
	public function setBlockStateAtIfCacheAbsent(int $x, int $y, int $z, Block|string $state) : bool{
		if(!isset($this->caches[self::hashXYZ($x, $y, $z)])){
			$this->setBlockStateAt($x, $y, $z, $state);
			return true;
		}
		return false;
	}

	public function unsetBlockStateAt(int $x, int $y, int $z) : void{
		$hash = self::hashXYZ($x, $y, $z);
		unset($this->places[$hash], $this->caches[$hash]);
	}

	/**
	 * Whether a block was queued or read at this position.
	 */
	public function isCached(int $x, int $y, int $z) : bool{
		return isset($this->caches[self::hashXYZ($x, $y, $z)]);
	}

	/**
	 * Queues every block and hook of $manager into this manager, overriding
	 * blocks queued here at the same positions.
	 */
	public function merge(BlockManager $manager) : void{
		foreach($manager->places as $hash => $placed){
			$this->places[$hash] = $placed;
			$this->caches[$hash] = $placed->block;
		}
		foreach($manager->hooks as $hook){
			$this->hooks[] = $hook;
		}
	}

	public function getWorld() : ChunkManager{
		return $this->world;
	}

	public function getChunk(int $chunkX, int $chunkZ) : ?Chunk{
		return $this->world->getChunk($chunkX, $chunkZ);
	}

	public function getMinHeight() : int{
		return $this->world->getMinY();
	}

	public function getMaxHeight() : int{
		return $this->world->getMaxY();
	}

	/**
	 * @return list<PlacedBlock>
	 */
	public function getBlocks() : array{
		return array_values($this->places);
	}

	/**
	 * Bounding box of the queued blocks (inclusive block coordinates).
	 */
	public function getBounds() : AxisAlignedBB{
		$minX = PHP_INT_MAX;
		$minY = PHP_INT_MAX;
		$minZ = PHP_INT_MAX;
		$maxX = PHP_INT_MIN;
		$maxY = PHP_INT_MIN;
		$maxZ = PHP_INT_MIN;
		foreach($this->places as $placed){
			$minX = min($minX, $placed->x);
			$minY = min($minY, $placed->y);
			$minZ = min($minZ, $placed->z);
			$maxX = max($maxX, $placed->x);
			$maxY = max($maxY, $placed->y);
			$maxZ = max($maxZ, $placed->z);
		}
		if(count($this->places) === 0){
			return new AxisAlignedBB(0, 0, 0, 0, 0, 0);
		}
		return new AxisAlignedBB($minX, $minY, $minZ, $maxX, $maxY, $maxZ);
	}

	/**
	 * Writes the queued blocks into their chunks, runs the hooks, then
	 * clears this manager. Blocks outside the world height or in chunks the
	 * ChunkManager does not hold are dropped.
	 */
	public function apply() : void{
		$minY = $this->world->getMinY();
		$maxY = $this->world->getMaxY();
		foreach($this->places as $placed){
			if($placed->y < $minY || $placed->y >= $maxY){
				continue;
			}
			$chunk = $this->world->getChunk($placed->x >> Chunk::COORD_BIT_SIZE, $placed->z >> Chunk::COORD_BIT_SIZE);
			if($chunk === null){
				continue;
			}
			$chunk->setBlockStateId($placed->x & Chunk::COORD_MASK, $placed->y, $placed->z & Chunk::COORD_MASK, $placed->block->getStateId());
		}
		$hooks = $this->hooks;
		$this->hooks = [];
		foreach($hooks as $hook){
			$hook($this->world);
		}
		$this->places = [];
		$this->caches = [];
	}
}
