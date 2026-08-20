<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\template;

use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\tag\ByteArrayTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntArrayTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use function array_keys;
use function dirname;
use function file_get_contents;
use function is_file;
use function ord;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;
use function zlib_decode;

/**
 * Reads the structure archive: a tree of folders whose leaves carry an "object" tag holding one template.
 * Identifiers are the path through that tree, such as "igloo/top" or "woodland_mansion/1x1_a1".
 */
final class TemplateArchive{

	/**
	 * @var CompoundTag[]
	 * @phpstan-var array<string, CompoundTag>
	 */
	private array $raw = [];

	/**
	 * @var StructureTemplate[]
	 * @phpstan-var array<string, StructureTemplate>
	 */
	private array $parsed = [];

	private static ?self $instance = null;

	private static ?string $archive = null;

	private function __construct(string $contents){
		$data = zlib_decode($contents);
		if($data === false){
			return;
		}

		$this->collect((new BigEndianNbtSerializer())->read($data)->mustGetCompoundTag(), "");
	}

	/**
	 * Lets a caller supply the archive itself, for a test or for a plugin that ships its own.
	 */
	public static function setArchive(string $contents) : void{
		self::$archive = $contents;
		self::$instance = null;
	}

	public static function getInstance() : self{
		return self::$instance ??= new self(self::$archive ?? self::read());
	}

	/**
	 * Chunks are generated on worker threads, which never run the plugin's startup code, so the archive is
	 * found from this file's own location. That resolves inside a phar as well as in a plugin folder.
	 */
	private static function read() : string{
		$path = dirname(__DIR__, 6) . "/resources/structures.nbt";

		return is_file($path) ? (file_get_contents($path) ?: "") : "";
	}

	public function get(string $identifier) : ?StructureTemplate{
		if(isset($this->parsed[$identifier])){
			return $this->parsed[$identifier];
		}

		$object = $this->raw[$identifier] ?? null;
		if($object === null){
			return null;
		}

		$size = $object->getTag("size");
		$palette = $object->getTag("palette");
		$blocks = $object->getTag("blocks");

		if(!$size instanceof IntArrayTag || !$palette instanceof ListTag || !$blocks instanceof ByteArrayTag){
			return null;
		}

		$states = [];
		foreach($palette->getValue() as $entry){
			$states[] = $entry instanceof IntTag ? BlockStateHashes::toStateId($entry->getValue()) : null;
		}

		[$sizeX, $sizeY, $sizeZ] = $size->getValue();

		$hashes = [];
		foreach($palette->getValue() as $entry){
			$hashes[] = $entry instanceof IntTag ? $entry->getValue() : null;
		}

		$jigsaws = $this->readJigsaws($object, $sizeX, $sizeY, $hashes, $blocks->getValue());

		return $this->parsed[$identifier] = new StructureTemplate($identifier, $sizeX, $sizeY, $sizeZ, $states, $blocks->getValue(), $jigsaws);
	}

	/**
	 * The connection points are stored beside the blocks, but the direction they point at is only in the state
	 * the marker itself holds, so it is read back out of the palette.
	 *
	 * @param int[] $hashes
	 * @phpstan-param array<int, int|null> $hashes
	 *
	 * @return JigsawBlock[]
	 */
	private function readJigsaws(CompoundTag $object, int $sizeX, int $sizeY, array $hashes, string $blocks) : array{
		$list = $object->getTag("jigsaw");
		if(!$list instanceof ListTag){
			return [];
		}

		$jigsaws = [];
		foreach($list->getValue() as $entry){
			if(!$entry instanceof CompoundTag){
				continue;
			}

			$pos = $entry->getTag("pos");
			if(!$pos instanceof IntArrayTag){
				continue;
			}

			[$x, $y, $z] = $pos->getValue();

			$index = $x + ($sizeX * ($y + ($sizeY * $z)));
			if($index < 0 || $index >= strlen($blocks)){
				continue;
			}

			$hash = $hashes[ord($blocks[$index]) - 1] ?? null;
			$orientation = $hash === null ? null : JigsawStates::getOrientation($hash);
			if($orientation === null){
				continue;
			}

			$jigsaws[] = new JigsawBlock(
				$x, $y, $z,
				self::stripNamespace($entry->getString("name", "")),
				self::stripNamespace($entry->getString("target", "")),
				self::stripNamespace($entry->getString("pool", "")),
				$entry->getString("joint", "rollable") === "rollable",
				$orientation[0],
				$orientation[1]
			);
		}

		return $jigsaws;
	}

	private static function stripNamespace(string $key) : string{
		$separator = strpos($key, ":");

		return $separator === false ? $key : substr($key, $separator + 1);
	}

	/**
	 * @return string[]
	 */
	public function getIdentifiers(string $prefix = "") : array{
		if($prefix === ""){
			return array_keys($this->raw);
		}

		$matches = [];
		foreach(array_keys($this->raw) as $identifier){
			if(str_starts_with($identifier, $prefix)){
				$matches[] = $identifier;
			}
		}

		return $matches;
	}

	private function collect(CompoundTag $tag, string $prefix) : void{
		foreach($tag as $name => $child){
			if(!$child instanceof CompoundTag){
				continue;
			}

			$identifier = $prefix === "" ? $name : $prefix . "/" . $name;

			$object = $child->getCompoundTag("object");
			if($object !== null){
				$this->raw[$identifier] = $object;
				continue;
			}

			$this->collect($child, $identifier);
		}
	}
}
