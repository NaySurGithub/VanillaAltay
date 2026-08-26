<?php

declare(strict_types=1);

namespace VanillaAltay\entity\spawn;

use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\world\World;

final class SpawnRule
{
	/**
	 * @param \Closure(World, Vector3) : Living $factory
	 * @param \Closure(World, Vector3) : bool $condition
	 */
	public function __construct(
		public readonly string $identifier,
		public readonly SpawnCategory $category,
		public readonly int $weight,
		public readonly int $herdMin,
		public readonly int $herdMax,
		private \Closure $factory,
		private \Closure $condition,
	) {
	}

	public function canSpawn(World $world, Vector3 $position) : bool
	{
		return ($this->condition)($world, $position);
	}

	public function create(World $world, Vector3 $position) : Living
	{
		return ($this->factory)($world, $position);
	}
}
