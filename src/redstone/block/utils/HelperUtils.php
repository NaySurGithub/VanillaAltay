<?php

declare(strict_types=1);

namespace redstone\block\utils;

use Generator;
use pocketmine\block\Block;
use pocketmine\block\tile\Tile;
use pocketmine\block\tile\TileFactory;
use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\BaseInventory;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use pocketmine\world\World;
use function count;

final class HelperUtils{

	/** @var array<Facing::DOWN|Facing::UP|Facing::NORTH|Facing::SOUTH|Facing::WEST|Facing::EAST, Vector3> */
	public static array $side_offsets;

	public static function init() : void{
		self::$side_offsets = [];
		foreach(Facing::ALL as $side){
			self::$side_offsets[$side] = Vector3::zero()->getSide($side);
		}
	}

	public static function getBlockAtSide(Position $position, int $side, int $step = 1) : Block{
		$offset = self::$side_offsets[$side];
		return $position->world->getBlockAt(
			$position->x + ($offset->x * $step),
			$position->y + ($offset->y * $step),
			$position->z + ($offset->z * $step)
		);
	}

	/**
	 * @template T
	 * @param list<T> $array
	 * @return Generator<T>
	 */
	public static function reverse(array $array) : Generator{
		for($i = count($array) - 1; $i >= 0; $i--){
			yield $array[$i];
		}
	}

	/**
	 * @param Inventory $inventory
	 * @param bool $include_empty
	 * @return array<int, Item>
	 */
	public static function fastReadOnlyInventoryContents(Inventory $inventory, bool $include_empty = false) : array{
		static $_inventory_slots = null;
		if($_inventory_slots === null){
			$air = VanillaItems::AIR();
			$_inventory_slots = (static function($inventory, bool $include_empty) use($air){
				$contents = [];
				foreach($inventory->slots as $index => $slot){
					if($slot !== null){
						$contents[$index] = $slot;
					}elseif($include_empty){
						$contents[$index] = $air;
					}
				}
				return $contents;
			})->bindTo(null, BaseInventory::class);
		}
		return $_inventory_slots($inventory, $include_empty);
	}

	public static function moveBlockAndTile(World $world, Vector3 $source, Vector3 $destination, bool $update = true) : void{
		$block = $world->getBlockAt($source->x, $source->y, $source->z);
		$tile_nbt = $world->getTileAt($source->x, $source->y, $source->z)?->saveNBT();
		$world->setBlockAt($source->x, $source->y, $source->z, VanillaBlocks::AIR(), $update);
		if($tile_nbt !== null){
			$world->setBlockAt($destination->x, $destination->y, $destination->z, $block, $update);
			$existing_tile = $world->getTileAt($destination->x, $destination->y, $destination->z);
			if($existing_tile !== null){
				$world->removeTile($existing_tile);
			}
			$tile_nbt->setInt(Tile::TAG_X, $destination->x);
			$tile_nbt->setInt(Tile::TAG_Y, $destination->y);
			$tile_nbt->setInt(Tile::TAG_Z, $destination->z);
			$world->addTile(TileFactory::getInstance()->createFromData($world, $tile_nbt));
		}else{
			$world->useBreakOn($destination);
			$world->setBlockAt($destination->x, $destination->y, $destination->z, $block, $update);
		}
	}
}