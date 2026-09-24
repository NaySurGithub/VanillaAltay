<?php

declare(strict_types=1);

namespace dimension\generator;

/**
 * 64-bit key of a chunk: the X coordinate in the high 32 bits, the Z
 * coordinate (as unsigned) in the low 32 bits.
 */
final class ChunkHash{

	private function __construct(){
	}

	public static function hash(int $chunkX, int $chunkZ) : int{
		return ($chunkX << 32) | ($chunkZ & 0xFFFFFFFF);
	}
}
