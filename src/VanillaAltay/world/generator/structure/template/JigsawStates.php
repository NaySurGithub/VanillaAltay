<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\template;

use pocketmine\math\Facing;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\TreeRoot;

use function ord;
use function strlen;

/**
 * A jigsaw block never becomes a real block, so it is missing from the state table the templates are read
 * against. Its twenty four states are hashed here instead, which is what turns a marker back into the direction
 * it points at and the direction it considers up.
 */
final class JigsawStates
{
	/**
	 * @var int[][]|null
	 * @phpstan-var array<int, array{int, int}>|null facing and top, by state hash
	 */
	private static ?array $orientations = null;

	private function __construct()
	{
		//NOOP
	}

	/**
	 * @return int[]|null
	 * @phpstan-return array{int, int}|null
	 */
	public static function getOrientation(int $hash) : ?array
	{
		self::load();

		return self::$orientations[$hash] ?? null;
	}

	private static function load() : void
	{
		if (self::$orientations !== null) {
			return;
		}

		self::$orientations = [];

		$serializer = new LittleEndianNbtSerializer();

		//the property order is the alphabetical one the game writes, which the hash depends on
		foreach ([0, 1, 2, 3, 4, 5] as $facing) {
			for ($rotation = 0; $rotation < 4; ++$rotation) {
				$tag = CompoundTag::create()
					->setString("name", "minecraft:jigsaw")
					->setTag("states", CompoundTag::create()
						->setInt("facing_direction", $facing)
						->setInt("rotation", $rotation));

				self::$orientations[self::fnv1a32($serializer->write(new TreeRoot($tag)))] = [$facing, self::getTop($facing, $rotation)];
			}
		}
	}

	/**
	 * Only a jigsaw pointing up or down carries a meaningful rotation; a horizontal one always stands upright.
	 */
	private static function getTop(int $facing, int $rotation) : int
	{
		if ($facing !== Facing::UP && $facing !== Facing::DOWN) {
			return Facing::UP;
		}

		if ($facing === Facing::DOWN) {
			return match ($rotation) {
				1 => Facing::WEST,
				2 => Facing::SOUTH,
				3 => Facing::EAST,
				default => Facing::NORTH,
			};
		}

		return match ($rotation) {
			1 => Facing::EAST,
			2 => Facing::SOUTH,
			3 => Facing::WEST,
			default => Facing::NORTH,
		};
	}

	private static function fnv1a32(string $data) : int
	{
		$hash = 0x811c9dc5;
		for ($i = 0, $length = strlen($data); $i < $length; ++$i) {
			$hash ^= ord($data[$i]);
			$hash = ($hash * 0x01000193) & 0xffffffff;
		}

		return $hash > 0x7fffffff ? $hash - 0x100000000 : $hash;
	}
}
