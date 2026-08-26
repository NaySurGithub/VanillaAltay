<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\template;

use pocketmine\block\Block;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\data\bedrock\block\BlockStateSerializeException;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\world\format\io\GlobalBlockStateHandlers;

use function count;
use function ksort;
use function ord;
use function strlen;

/**
 * Resolves the block state hashes a structure template stores.
 *
 * Bedrock identifies a state by the FNV1a-32 hash of its little endian NBT. The table is built by asking the
 * server to serialize every state it knows and hashing the result, so it needs no data file and follows
 * whatever version of the game the server speaks.
 */
final class BlockStateHashes
{
	/**
	 * @var int[]|null
	 * @phpstan-var array<int, int>|null
	 */
	private static ?array $stateIds = null;

	private function __construct()
	{
		//NOOP
	}

	public static function toStateId(int $hash) : ?int
	{
		self::load();

		return self::$stateIds[$hash] ?? null;
	}

	public static function count() : int
	{
		self::load();

		return count(self::$stateIds);
	}

	private static function load() : void
	{
		if (self::$stateIds !== null) {
			return;
		}

		self::$stateIds = [];

		$serializer = new LittleEndianNbtSerializer();
		$blockSerializer = GlobalBlockStateHandlers::getSerializer();

		foreach (RuntimeBlockStateRegistry::getInstance()->getAllKnownStates() as $stateId => $block) {
			try {
				$data = $blockSerializer->serializeBlock($block);
			} catch (BlockStateSerializeException) {
				continue;
			}

			//the hash covers the raw bytes, so the keys have to be written in the same order the game uses,
			//which is alphabetical; only the name and the states are hashed, not the version tag in between
			$pairs = [];
			foreach ($data->getStates() as $key => $value) {
				$pairs[$key] = $value;
			}
			ksort($pairs);

			$states = CompoundTag::create();
			foreach ($pairs as $key => $value) {
				$states->setTag($key, $value);
			}

			$tag = CompoundTag::create()
				->setString("name", $data->getName())
				->setTag("states", $states);

			self::$stateIds[self::fnv1a32($serializer->write(new TreeRoot($tag)))] = $stateId;
		}
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
