<?php

declare(strict_types=1);

namespace dimension\generator\structure\jigsaw;

/**
 * A template name with its weight in a structure pool.
 */
final class PoolEntry{

	public function __construct(
		public readonly string $structureName,
		public readonly int $weight,
		public readonly string $projection = "rigid"
	){}
}
