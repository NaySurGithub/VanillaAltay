<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\template;

/**
 * One connection point of a template: where it sits, which way it points, what it expects on the other side and
 * which pool the piece answering it is drawn from.
 */
final class JigsawBlock{

	public function __construct(
		public int $x,
		public int $y,
		public int $z,
		public string $name,
		public string $target,
		public string $pool,
		public bool $rollable,
		public int $facing,
		public int $top
	){}
}
