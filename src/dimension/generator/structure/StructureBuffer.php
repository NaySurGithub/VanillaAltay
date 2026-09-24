<?php

declare(strict_types=1);

namespace dimension\generator\structure;

use dimension\generator\object\BlockManager;
use dimension\generator\structure\template\StateBlocks;
use pocketmine\data\bedrock\block\BlockStateData;

/**
 * The blocks of a whole structure at world coordinates, planned without
 * touching the world. Once planned, a structure is written chunk by chunk:
 * each populated chunk takes only the blocks inside it, so a structure
 * larger than the population area comes out whole and seamless.
 */
final class StructureBuffer{

	/** @var array<int, array{int, int, int, BlockStateData}> */
	private array $entries = [];
	/** @var array<int, list<array{int, int, int, BlockStateData}>>|null */
	private ?array $byChunk = null;
	private BoundingBox $bounds;

	public function __construct(){
		$this->bounds = BoundingBox::unknown();
	}

	private static function hash(int $x, int $y, int $z) : int{
		return ((($x + 30000000) & 0x3FFFFFF) << 37)
			| ((($z + 30000000) & 0x3FFFFFF) << 11)
			| ((($y + 400) & 0x3FF) << 1);
	}

	private static function chunkKey(int $chunkX, int $chunkZ) : int{
		return (($chunkX & 0xFFFFFFFF) << 32) | ($chunkZ & 0xFFFFFFFF);
	}

	public function set(int $x, int $y, int $z, BlockStateData $state) : void{
		$this->entries[self::hash($x, $y, $z)] = [$x, $y, $z, $state];
		$this->byChunk = null;
		$bounds = $this->bounds;
		if($x < $bounds->x0){
			$bounds->x0 = $x;
		}
		if($y < $bounds->y0){
			$bounds->y0 = $y;
		}
		if($z < $bounds->z0){
			$bounds->z0 = $z;
		}
		if($x > $bounds->x1){
			$bounds->x1 = $x;
		}
		if($y > $bounds->y1){
			$bounds->y1 = $y;
		}
		if($z > $bounds->z1){
			$bounds->z1 = $z;
		}
	}

	public function remove(int $x, int $y, int $z) : void{
		unset($this->entries[self::hash($x, $y, $z)]);
		$this->byChunk = null;
	}

	public function has(int $x, int $y, int $z) : bool{
		return isset($this->entries[self::hash($x, $y, $z)]);
	}

	public function get(int $x, int $y, int $z) : ?BlockStateData{
		return ($this->entries[self::hash($x, $y, $z)] ?? null)[3] ?? null;
	}

	/**
	 * @return array<int, array{int, int, int, BlockStateData}>
	 */
	public function all() : array{
		return $this->entries;
	}

	/**
	 * Bounds of every block ever set, including removed ones.
	 */
	public function getBounds() : BoundingBox{
		return $this->bounds->copy();
	}

	public function isEmpty() : bool{
		return $this->entries === [];
	}

	/**
	 * Queues into $root the planned blocks lying in the given chunk. States
	 * the server does not know are skipped.
	 */
	public function emit(BlockManager $root, int $chunkX, int $chunkZ) : void{
		if($this->byChunk === null){
			$byChunk = [];
			foreach($this->entries as $entry){
				$byChunk[self::chunkKey($entry[0] >> 4, $entry[2] >> 4)][] = $entry;
			}
			$this->byChunk = $byChunk;
		}
		foreach($this->byChunk[self::chunkKey($chunkX, $chunkZ)] ?? [] as $entry){
			$block = StateBlocks::toBlock($entry[3]);
			if($block !== null){
				$root->setBlockStateAt($entry[0], $entry[1], $entry[2], $block);
			}
		}
	}
}
