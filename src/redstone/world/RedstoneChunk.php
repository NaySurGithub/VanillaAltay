<?php

declare(strict_types=1);

namespace redstone\world;

use pocketmine\world\World;

final class RedstoneChunk{

	/** @var array<int, BlockData> */
	private array $extra_data = [];

	public function __construct(){
	}

	public function setBlockData(int $x, int $y, int $z, BlockData $data) : void{
		$this->extra_data[World::chunkBlockHash($x, $y, $z)] = $data;
	}

	public function getBlockData(int $x, int $y, int $z) : ?BlockData{
		return $this->extra_data[World::chunkBlockHash($x, $y, $z)] ?? null;
	}

	public function removeBlockData(int $x, int $y, int $z) : void{
		unset($this->extra_data[World::chunkBlockHash($x, $y, $z)]);
	}
}