<?php

declare(strict_types=1);

namespace redstone\vanilla;

use InvalidArgumentException;
use pocketmine\block\Block;
use pocketmine\block\Door;
use pocketmine\block\RedstoneLamp;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\utils\LeverFacing;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\WoodenButton;
use pocketmine\data\bedrock\block\BlockStateNames as StateNames;
use pocketmine\data\bedrock\block\BlockStateSerializeException;
use pocketmine\data\bedrock\block\BlockStateStringValues as StringValues;
use pocketmine\data\bedrock\block\BlockTypeNames as Ids;
use pocketmine\data\bedrock\block\convert\BlockStateDeserializerHelper as Helper;
use pocketmine\data\bedrock\block\convert\BlockStateReader as Reader;
use pocketmine\data\bedrock\block\convert\BlockStateSerializerHelper;
use pocketmine\data\bedrock\block\convert\BlockStateWriter as Writer;
use pocketmine\data\bedrock\block\convert\BlockSerializerDeserializerRegistrar;
use pocketmine\data\bedrock\block\convert\FlattenedIdModel;
use pocketmine\data\bedrock\block\convert\Model;
use pocketmine\data\bedrock\block\convert\property\CommonProperties;
use pocketmine\data\bedrock\block\convert\property\IntFromRawStateMap;
use pocketmine\data\bedrock\item\ItemTypeNames;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\scheduler\AsyncPool;
use pocketmine\scheduler\AsyncTask;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use redstone\block\CopperTrapdoor;
use redstone\block\Dispenser;
use redstone\block\FenceGate;
use redstone\block\Hopper;
use redstone\block\Lever;
use redstone\block\Observer;
use redstone\block\Piston;
use redstone\block\PistonArmBlock;
use redstone\block\RedstoneComparator;
use redstone\block\RedstoneRepeater;
use redstone\block\RedstoneTorch;
use redstone\block\RedstoneWire;
use redstone\block\StoneButton;
use redstone\block\StonePressurePlate;
use redstone\block\TNT;
use redstone\block\Trapdoor;
use redstone\block\TrappedChest;
use redstone\block\WeightedPressurePlateHeavy;
use redstone\block\WeightedPressurePlateLight;
use redstone\block\WoodenPressurePlate;
use redstone\block\WoodenTrapdoor;
use ReflectionProperty;
use function mb_strtoupper;

final class ExtraVanillaData{

	public static function registerOnAllThreads(AsyncPool $pool) : void{
		self::registerOnCurrentThread();
		$pool->addWorkerStartHook(function(int $worker) use($pool) : void{
			$pool->submitTaskToWorker(new class extends AsyncTask{
				public function onRun() : void{
					ExtraVanillaData::registerOnCurrentThread();
				}
			}, $worker);
		});
	}

	public static function registerOnCurrentThread() : void{
		self::registerBlocks();
		self::registerItems();
	}

	private static function registerBlocks() : void{
		$serializer = GlobalBlockStateHandlers::getSerializer();
		$deserializer = GlobalBlockStateHandlers::getDeserializer();

		self::registerSimpleBlock(Ids::ACACIA_BUTTON, ExtraVanillaBlocks::ACACIA_BUTTON(), ["acacia_button"], false);
		self::registerSimpleBlock(Ids::ACACIA_PRESSURE_PLATE, ExtraVanillaBlocks::ACACIA_PRESSURE_PLATE(), ["acacia_pressure_plate"], false);
		self::registerSimpleBlock(Ids::BIRCH_BUTTON, ExtraVanillaBlocks::BIRCH_BUTTON(), ["birch_button"], false);
		self::registerSimpleBlock(Ids::BIRCH_PRESSURE_PLATE, ExtraVanillaBlocks::BIRCH_PRESSURE_PLATE(), ["birch_pressure_plate"], false);
		self::registerSimpleBlock(Ids::CHERRY_BUTTON, ExtraVanillaBlocks::CHERRY_BUTTON(), ["cherry_button"], false);
		self::registerSimpleBlock(Ids::CHERRY_PRESSURE_PLATE, ExtraVanillaBlocks::CHERRY_PRESSURE_PLATE(), ["cherry_pressure_plate"], false);
		self::registerSimpleBlock(Ids::CRIMSON_BUTTON, ExtraVanillaBlocks::CRIMSON_BUTTON(), ["crimson_button"], false);
		self::registerSimpleBlock(Ids::CRIMSON_PRESSURE_PLATE, ExtraVanillaBlocks::CRIMSON_PRESSURE_PLATE(), ["crimson_pressure_plate"], false);
		self::registerSimpleBlock(Ids::DARK_OAK_BUTTON, ExtraVanillaBlocks::DARK_OAK_BUTTON(), ["dark_oak_button"], false);
		self::registerSimpleBlock(Ids::DARK_OAK_PRESSURE_PLATE, ExtraVanillaBlocks::DARK_OAK_PRESSURE_PLATE(), ["dark_oak_pressure_plate"], false);
		self::registerSimpleBlock(Ids::DISPENSER, ExtraVanillaBlocks::DISPENSER(), ["dispenser"], false);
		self::registerSimpleBlock(Ids::DROPPER, ExtraVanillaBlocks::DROPPER(), ["dropper"], false);
		self::registerSimpleBlock(Ids::HOPPER, ExtraVanillaBlocks::HOPPER(), [], false);
		self::registerSimpleBlock(Ids::TRAPPED_CHEST, ExtraVanillaBlocks::TRAPPED_CHEST(), [], false);
		self::registerSimpleBlock(Ids::HEAVY_WEIGHTED_PRESSURE_PLATE, ExtraVanillaBlocks::WEIGHTED_PRESSURE_PLATE_HEAVY(), ["heavy_weighted_pressure_plate"], false);
		self::registerSimpleBlock(Ids::JUNGLE_BUTTON, ExtraVanillaBlocks::JUNGLE_BUTTON(), ["jungle_button"], false);
		self::registerSimpleBlock(Ids::JUNGLE_PRESSURE_PLATE, ExtraVanillaBlocks::JUNGLE_PRESSURE_PLATE(), ["jungle_pressure_plate"], false);
		self::registerSimpleBlock(Ids::LEVER, ExtraVanillaBlocks::LEVER(), ["lever"], false);
		self::registerSimpleBlock(Ids::LIGHT_WEIGHTED_PRESSURE_PLATE, ExtraVanillaBlocks::WEIGHTED_PRESSURE_PLATE_LIGHT(), ["light_weighted_pressure_plate"], false);
		self::registerSimpleBlock(Ids::MANGROVE_BUTTON, ExtraVanillaBlocks::MANGROVE_BUTTON(), ["mangrove_button"], false);
		self::registerSimpleBlock(Ids::MANGROVE_PRESSURE_PLATE, ExtraVanillaBlocks::MANGROVE_PRESSURE_PLATE(), ["mangrove_pressure_plate"], false);
		self::registerSimpleBlock(Ids::OBSERVER, ExtraVanillaBlocks::OBSERVER(), ["observer"], false);
		self::registerSimpleBlock(Ids::WOODEN_BUTTON, ExtraVanillaBlocks::OAK_BUTTON(), ["wooden_button"], false);
		self::registerSimpleBlock(Ids::WOODEN_PRESSURE_PLATE, ExtraVanillaBlocks::OAK_PRESSURE_PLATE(), ["wooden_pressure_plate"], false);
		self::registerSimpleBlock(Ids::PISTON, ExtraVanillaBlocks::PISTON(), ["piston"], false);
		self::registerSimpleBlock(Ids::PISTON_ARM_COLLISION, ExtraVanillaBlocks::PISTON_ARM_BLOCK(), ["piston_arm_collision"], false);
		self::registerSimpleBlock(Ids::POLISHED_BLACKSTONE_BUTTON, ExtraVanillaBlocks::POLISHED_BLACKSTONE_BUTTON(), ["polished_blackstone_button"], false);
		self::registerSimpleBlock(Ids::POLISHED_BLACKSTONE_PRESSURE_PLATE, ExtraVanillaBlocks::POLISHED_BLACKSTONE_PRESSURE_PLATE(), ["polished_blackstone_pressure_plate"], false);
		self::registerSimpleBlock(Ids::REDSTONE_BLOCK, ExtraVanillaBlocks::REDSTONE(), ["redstone_block"]);
		self::registerSimpleBlock(Ids::REDSTONE_ORE, ExtraVanillaBlocks::REDSTONE_ORE(), ["redstone_ore"]);
		self::registerSimpleBlock(Ids::UNPOWERED_COMPARATOR, ExtraVanillaBlocks::REDSTONE_COMPARATOR(), ["powered_comparator", "unpowered_comparator"]);
		self::registerSimpleBlock(Ids::REDSTONE_LAMP, ExtraVanillaBlocks::REDSTONE_LAMP(), ["redstone_lamp", "lit_redstone_lamp"], false);
		self::registerSimpleBlock(Ids::POWERED_REPEATER, ExtraVanillaBlocks::REDSTONE_REPEATER(), ["powered_repeater", "unpowered_repeater"], false);
		self::registerSimpleBlock(Ids::REDSTONE_TORCH, ExtraVanillaBlocks::REDSTONE_TORCH(), ["redstone_torch"], false);
		self::registerSimpleBlock(Ids::REDSTONE_WIRE, ExtraVanillaBlocks::REDSTONE_WIRE(), ["redstone_wire"], false);
		self::registerSimpleBlock(Ids::SPRUCE_BUTTON, ExtraVanillaBlocks::SPRUCE_BUTTON(), ["spruce_button"], false);
		self::registerSimpleBlock(Ids::SPRUCE_PRESSURE_PLATE, ExtraVanillaBlocks::SPRUCE_PRESSURE_PLATE(), ["spruce_pressure_plate"], false);
		self::registerSimpleBlock(Ids::STICKY_PISTON, ExtraVanillaBlocks::STICKY_PISTON(), ["sticky_piston"], false);
		self::registerSimpleBlock(Ids::STONE_BUTTON, ExtraVanillaBlocks::STONE_BUTTON(), ["stone_button"], false);
		self::registerSimpleBlock(Ids::STONE_PRESSURE_PLATE, ExtraVanillaBlocks::STONE_PRESSURE_PLATE(), ["stone_pressure_plate"], false);
		self::registerSimpleBlock(Ids::TNT, ExtraVanillaBlocks::TNT(), ["tnt"], false);
		self::registerSimpleBlock(Ids::WARPED_BUTTON, ExtraVanillaBlocks::WARPED_BUTTON(), ["warped_button"], false);
		self::registerSimpleBlock(Ids::WARPED_PRESSURE_PLATE, ExtraVanillaBlocks::WARPED_PRESSURE_PLATE(), ["warped_pressure_plate"], false);

		self::registerSimpleBlock(Ids::IRON_DOOR, ExtraVanillaBlocks::IRON_DOOR(), [], false);
		self::registerSimpleBlock(Ids::ACACIA_DOOR, ExtraVanillaBlocks::ACACIA_DOOR(), [], false);
		self::registerSimpleBlock(Ids::BIRCH_DOOR, ExtraVanillaBlocks::BIRCH_DOOR(), [], false);
		self::registerSimpleBlock(Ids::CHERRY_DOOR, ExtraVanillaBlocks::CHERRY_DOOR(), [], false);
		self::registerSimpleBlock(Ids::CRIMSON_DOOR, ExtraVanillaBlocks::CRIMSON_DOOR(), [], false);
		self::registerSimpleBlock(Ids::DARK_OAK_DOOR, ExtraVanillaBlocks::DARK_OAK_DOOR(), [], false);
		self::registerSimpleBlock(Ids::JUNGLE_DOOR, ExtraVanillaBlocks::JUNGLE_DOOR(), [], false);
		self::registerSimpleBlock(Ids::MANGROVE_DOOR, ExtraVanillaBlocks::MANGROVE_DOOR(), [], false);
		self::registerSimpleBlock(Ids::WOODEN_DOOR, ExtraVanillaBlocks::OAK_DOOR(), [], false);
		self::registerSimpleBlock(Ids::PALE_OAK_DOOR, ExtraVanillaBlocks::PALE_OAK_DOOR(), [], false);
		self::registerSimpleBlock(Ids::SPRUCE_DOOR, ExtraVanillaBlocks::SPRUCE_DOOR(), [], false);
		self::registerSimpleBlock(Ids::WARPED_DOOR, ExtraVanillaBlocks::WARPED_DOOR(), [], false);
		self::registerSimpleBlock(Ids::COPPER_DOOR, ExtraVanillaBlocks::COPPER_DOOR(), [], false);

		$deserializer->map(Ids::IRON_DOOR, fn(Reader $in) => Helper::decodeDoor(ExtraVanillaBlocks::IRON_DOOR(), $in));
		$deserializer->map(Ids::ACACIA_DOOR, fn(Reader $in) => Helper::decodeDoor(ExtraVanillaBlocks::ACACIA_DOOR(), $in));
		$deserializer->map(Ids::BIRCH_DOOR, fn(Reader $in) => Helper::decodeDoor(ExtraVanillaBlocks::BIRCH_DOOR(), $in));
		$deserializer->map(Ids::CHERRY_DOOR, fn(Reader $in) => Helper::decodeDoor(ExtraVanillaBlocks::CHERRY_DOOR(), $in));
		$deserializer->map(Ids::CRIMSON_DOOR, fn(Reader $in) => Helper::decodeDoor(ExtraVanillaBlocks::CRIMSON_DOOR(), $in));
		$deserializer->map(Ids::DARK_OAK_DOOR, fn(Reader $in) => Helper::decodeDoor(ExtraVanillaBlocks::DARK_OAK_DOOR(), $in));
		$deserializer->map(Ids::JUNGLE_DOOR, fn(Reader $in) => Helper::decodeDoor(ExtraVanillaBlocks::JUNGLE_DOOR(), $in));
		$deserializer->map(Ids::MANGROVE_DOOR, fn(Reader $in) => Helper::decodeDoor(ExtraVanillaBlocks::MANGROVE_DOOR(), $in));
		$deserializer->map(Ids::WOODEN_DOOR, fn(Reader $in) => Helper::decodeDoor(ExtraVanillaBlocks::OAK_DOOR(), $in));
		$deserializer->map(Ids::PALE_OAK_DOOR, fn(Reader $in) => Helper::decodeDoor(ExtraVanillaBlocks::PALE_OAK_DOOR(), $in));
		$deserializer->map(Ids::SPRUCE_DOOR, fn(Reader $in) => Helper::decodeDoor(ExtraVanillaBlocks::SPRUCE_DOOR(), $in));
		$deserializer->map(Ids::WARPED_DOOR, fn(Reader $in) => Helper::decodeDoor(ExtraVanillaBlocks::WARPED_DOOR(), $in));

		$serializer->map(ExtraVanillaBlocks::IRON_DOOR(), fn(Door $block) => BlockStateSerializerHelper::encodeDoor($block, new Writer(Ids::IRON_DOOR)));
		$serializer->map(ExtraVanillaBlocks::ACACIA_DOOR(), fn(Door $block) => BlockStateSerializerHelper::encodeDoor($block, new Writer(Ids::ACACIA_DOOR)));
		$serializer->map(ExtraVanillaBlocks::BIRCH_DOOR(), fn(Door $block) => BlockStateSerializerHelper::encodeDoor($block, new Writer(Ids::BIRCH_DOOR)));
		$serializer->map(ExtraVanillaBlocks::CHERRY_DOOR(), fn(Door $block) => BlockStateSerializerHelper::encodeDoor($block, new Writer(Ids::CHERRY_DOOR)));
		$serializer->map(ExtraVanillaBlocks::CRIMSON_DOOR(), fn(Door $block) => BlockStateSerializerHelper::encodeDoor($block, new Writer(Ids::CRIMSON_DOOR)));
		$serializer->map(ExtraVanillaBlocks::DARK_OAK_DOOR(), fn(Door $block) => BlockStateSerializerHelper::encodeDoor($block, new Writer(Ids::DARK_OAK_DOOR)));
		$serializer->map(ExtraVanillaBlocks::JUNGLE_DOOR(), fn(Door $block) => BlockStateSerializerHelper::encodeDoor($block, new Writer(Ids::JUNGLE_DOOR)));
		$serializer->map(ExtraVanillaBlocks::MANGROVE_DOOR(), fn(Door $block) => BlockStateSerializerHelper::encodeDoor($block, new Writer(Ids::MANGROVE_DOOR)));
		$serializer->map(ExtraVanillaBlocks::OAK_DOOR(), fn(Door $block) => BlockStateSerializerHelper::encodeDoor($block, new Writer(Ids::WOODEN_DOOR)));
		$serializer->map(ExtraVanillaBlocks::PALE_OAK_DOOR(), fn(Door $block) => BlockStateSerializerHelper::encodeDoor($block, new Writer(Ids::PALE_OAK_DOOR)));
		$serializer->map(ExtraVanillaBlocks::SPRUCE_DOOR(), fn(Door $block) => BlockStateSerializerHelper::encodeDoor($block, new Writer(Ids::SPRUCE_DOOR)));
		$serializer->map(ExtraVanillaBlocks::WARPED_DOOR(), fn(Door $block) => BlockStateSerializerHelper::encodeDoor($block, new Writer(Ids::WARPED_DOOR)));

		$deserializer->map(Ids::DISPENSER, function(Reader $in) : Block{
			return ExtraVanillaBlocks::DISPENSER()
				->setFacing($in->readFacingDirection())
				->setPowered($in->readBool(StateNames::TRIGGERED_BIT));
		});
		$serializer->map(ExtraVanillaBlocks::DISPENSER(), function(Dispenser $block) : Writer{
			return Writer::create(Ids::DISPENSER)
				->writeBool(StateNames::TRIGGERED_BIT, $block->isPowered())
				->writeFacingDirection($block->getFacing());
		});

		$deserializer->map(Ids::DROPPER, function(Reader $in) : Block{
			return ExtraVanillaBlocks::DROPPER()
				->setFacing($in->readFacingDirection())
				->setPowered($in->readBool(StateNames::TRIGGERED_BIT));
		});
		$serializer->map(ExtraVanillaBlocks::DROPPER(), function(Dispenser $block) : Writer{
			return Writer::create(Ids::DROPPER)
				->writeBool(StateNames::TRIGGERED_BIT, $block->isPowered())
				->writeFacingDirection($block->getFacing());
		});

		$deserializer->map(Ids::HOPPER, function(Reader $in) : Block{
			return ExtraVanillaBlocks::HOPPER()
				->setFacing($in->readFacingWithoutUp())
				->setPowered($in->readBool(StateNames::TOGGLE_BIT));
		});
		$serializer->map(ExtraVanillaBlocks::HOPPER(), function(Hopper $block) : Writer{
			return Writer::create(Ids::HOPPER)
				->writeBool(StateNames::TOGGLE_BIT, $block->isPowered())
				->writeFacingWithoutUp($block->getFacing());
		});
		self::unregisterSimpleItem(ItemTypeNames::HOPPER, VanillaBlocks::HOPPER()->asItem());

		$deserializer->map(Ids::TRAPPED_CHEST, function(Reader $in) : Block{
			return ExtraVanillaBlocks::TRAPPED_CHEST()
				->setFacing($in->readCardinalHorizontalFacing());
		});
		$serializer->map(ExtraVanillaBlocks::TRAPPED_CHEST(), function(TrappedChest $block) : Writer{
			return Writer::create(Ids::TRAPPED_CHEST)
				->writeCardinalHorizontalFacing($block->getFacing());
		});

		self::registerOpenableBlocks();

		$deserializer->map(Ids::OBSERVER, function(Reader $in) : Block{
			return ExtraVanillaBlocks::OBSERVER()
				->setFacing($in->mapIntFromString(StateNames::MC_FACING_DIRECTION, self::observerFacingMap()))
				->setPowered($in->readBool(StateNames::POWERED_BIT));
		});
		$serializer->map(ExtraVanillaBlocks::OBSERVER(), function(Observer $block) : Writer{
			return Writer::create(Ids::OBSERVER)
				->mapIntToString(StateNames::MC_FACING_DIRECTION, self::observerFacingMap(), $block->getFacing())
				->writeBool(StateNames::POWERED_BIT, $block->isPowered());
		});

		self::unmapBlockSerializers(Ids::PISTON, ExtraVanillaBlocks::PISTON());
		self::unmapBlockSerializers(Ids::STICKY_PISTON, ExtraVanillaBlocks::STICKY_PISTON());
		$deserializer->map(Ids::PISTON, function(Reader $in) : Block{
			return ExtraVanillaBlocks::PISTON()
				->setFacing($in->readFacingDirection());
		});
		$deserializer->map(Ids::STICKY_PISTON, function(Reader $in) : Block{
			return ExtraVanillaBlocks::STICKY_PISTON()
				->setFacing($in->readFacingDirection());
		});
		$serializer->map(ExtraVanillaBlocks::PISTON(), function(Piston $block) : Writer{
			return Writer::create(Ids::PISTON)
				->writeFacingDirection($block->getFacing());
		});
		$serializer->map(ExtraVanillaBlocks::STICKY_PISTON(), function(Piston $block) : Writer{
			return Writer::create(Ids::STICKY_PISTON)
				->writeFacingDirection($block->getFacing());
		});

		$deserializer->map(Ids::PISTON_ARM_COLLISION, function(Reader $in) : Block{
			return ExtraVanillaBlocks::PISTON_ARM_BLOCK()
				->setFacing($in->readFacingDirection());
		});
		$serializer->map(ExtraVanillaBlocks::PISTON_ARM_BLOCK(), function(PistonArmBlock $block) : Writer{
			return Writer::create(Ids::PISTON_ARM_COLLISION)
				->writeFacingDirection($block->getFacing());
		});

		self::unmapBlockSerializers(Ids::REDSTONE_TORCH, ExtraVanillaBlocks::REDSTONE_TORCH());
		self::unmapBlockSerializers(Ids::UNLIT_REDSTONE_TORCH, ExtraVanillaBlocks::REDSTONE_TORCH());
		$deserializer->map(Ids::REDSTONE_TORCH, function(Reader $in) : Block{
			return ExtraVanillaBlocks::REDSTONE_TORCH()
				->setFacing($in->readTorchFacing())
				->setLit();
		});
		$deserializer->map(Ids::UNLIT_REDSTONE_TORCH, function(Reader $in) : Block{
			return ExtraVanillaBlocks::REDSTONE_TORCH()
				->setFacing($in->readTorchFacing())
				->setLit(false);
		});
		$serializer->map(ExtraVanillaBlocks::REDSTONE_TORCH(), function(RedstoneTorch $block) : Writer{
			return Writer::create($block->isLit() ? Ids::REDSTONE_TORCH : Ids::UNLIT_REDSTONE_TORCH)
				->writeTorchFacing($block->getFacing());
		});

		self::unmapBlockSerializers(Ids::REDSTONE_WIRE, ExtraVanillaBlocks::REDSTONE_WIRE());
		$deserializer->map(Ids::REDSTONE_WIRE, function(Reader $in) : Block{
			return ExtraVanillaBlocks::REDSTONE_WIRE()
				->setOutputSignalStrength($in->readBoundedInt(StateNames::REDSTONE_SIGNAL, 0, 15));
		});
		$serializer->map(ExtraVanillaBlocks::REDSTONE_WIRE(), function(RedstoneWire $block) : Writer{
			return Writer::create(Ids::REDSTONE_WIRE)
				->writeInt(StateNames::REDSTONE_SIGNAL, $block->getOutputSignalStrength());
		});

		self::unmapBlockSerializers(Ids::LIT_REDSTONE_LAMP, ExtraVanillaBlocks::REDSTONE_LAMP());
		self::unmapBlockSerializers(Ids::REDSTONE_LAMP, ExtraVanillaBlocks::REDSTONE_LAMP());
		$deserializer->map(Ids::REDSTONE_LAMP, fn() => ExtraVanillaBlocks::REDSTONE_LAMP()->setPowered(false));
		$deserializer->map(Ids::LIT_REDSTONE_LAMP, fn() => ExtraVanillaBlocks::REDSTONE_LAMP()->setPowered(true));
		$serializer->map(ExtraVanillaBlocks::REDSTONE_LAMP(), fn(RedstoneLamp $block) => new Writer($block->isPowered() ? Ids::LIT_REDSTONE_LAMP : Ids::REDSTONE_LAMP));

		self::unmapBlockSerializers(Ids::POWERED_COMPARATOR, ExtraVanillaBlocks::REDSTONE_COMPARATOR());
		self::unmapBlockSerializers(Ids::UNPOWERED_COMPARATOR, ExtraVanillaBlocks::REDSTONE_COMPARATOR());
		$deserializer->map(Ids::POWERED_COMPARATOR, fn(Reader $in) => Helper::decodeComparator(ExtraVanillaBlocks::REDSTONE_COMPARATOR(), $in));
		$deserializer->map(Ids::UNPOWERED_COMPARATOR, fn(Reader $in) => Helper::decodeComparator(ExtraVanillaBlocks::REDSTONE_COMPARATOR(), $in));
		$serializer->map(ExtraVanillaBlocks::REDSTONE_COMPARATOR(), function(RedstoneComparator $block) : Writer{
			return Writer::create($block->isPowered() ? Ids::POWERED_COMPARATOR : Ids::UNPOWERED_COMPARATOR)
				->writeBool(StateNames::OUTPUT_LIT_BIT, $block->isPowered())
				->writeBool(StateNames::OUTPUT_SUBTRACT_BIT, $block->isSubtractMode())
				->writeCardinalHorizontalFacing($block->getFacing());
		});
		self::unregisterSimpleItem(ItemTypeNames::COMPARATOR, VanillaBlocks::REDSTONE_COMPARATOR()->asItem());

		self::unmapBlockSerializers(Ids::POWERED_REPEATER, ExtraVanillaBlocks::REDSTONE_REPEATER());
		self::unmapBlockSerializers(Ids::UNPOWERED_REPEATER, ExtraVanillaBlocks::REDSTONE_REPEATER());
		$deserializer->map(Ids::POWERED_REPEATER, fn(Reader $in) => Helper::decodeRepeater(ExtraVanillaBlocks::REDSTONE_REPEATER(), $in)->setPowered(true));
		$deserializer->map(Ids::UNPOWERED_REPEATER, fn(Reader $in) => Helper::decodeRepeater(ExtraVanillaBlocks::REDSTONE_REPEATER(), $in)->setPowered(false));
		$serializer->map(ExtraVanillaBlocks::REDSTONE_REPEATER(), function(RedstoneRepeater $block) : Writer{
			return Writer::create($block->isPowered() ? Ids::POWERED_REPEATER : Ids::UNPOWERED_REPEATER)
				->writeCardinalHorizontalFacing($block->getFacing())
				->writeInt(StateNames::REPEATER_DELAY, $block->getDelay() - 1);
		});
		self::unregisterSimpleItem(ItemTypeNames::REPEATER, VanillaBlocks::REDSTONE_REPEATER()->asItem());

		self::unmapBlockSerializers(Ids::LEVER, ExtraVanillaBlocks::LEVER());
		$deserializer->map(Ids::LEVER, function(Reader $in) : Block{
			return ExtraVanillaBlocks::LEVER()
				->setActivated($in->readBool(StateNames::OPEN_BIT))
				->setFacing(match($value = $in->readString(StateNames::LEVER_DIRECTION)){
					StringValues::LEVER_DIRECTION_DOWN_NORTH_SOUTH => LeverFacing::DOWN_AXIS_Z,
					StringValues::LEVER_DIRECTION_DOWN_EAST_WEST => LeverFacing::DOWN_AXIS_X,
					StringValues::LEVER_DIRECTION_UP_NORTH_SOUTH => LeverFacing::UP_AXIS_Z,
					StringValues::LEVER_DIRECTION_UP_EAST_WEST => LeverFacing::UP_AXIS_X,
					StringValues::LEVER_DIRECTION_NORTH => LeverFacing::NORTH,
					StringValues::LEVER_DIRECTION_SOUTH => LeverFacing::SOUTH,
					StringValues::LEVER_DIRECTION_WEST => LeverFacing::WEST,
					StringValues::LEVER_DIRECTION_EAST => LeverFacing::EAST,
					default => throw $in->badValueException(StateNames::LEVER_DIRECTION, $value),
				});
		});

		$serializer->map(ExtraVanillaBlocks::LEVER(), function(Lever $block) : Writer{
			return Writer::create(Ids::LEVER)
				->writeBool(StateNames::OPEN_BIT, $block->isActivated())
				->writeString(StateNames::LEVER_DIRECTION, match($block->getFacing()){
					LeverFacing::DOWN_AXIS_Z => StringValues::LEVER_DIRECTION_DOWN_NORTH_SOUTH,
					LeverFacing::DOWN_AXIS_X => StringValues::LEVER_DIRECTION_DOWN_EAST_WEST,
					LeverFacing::UP_AXIS_Z => StringValues::LEVER_DIRECTION_UP_NORTH_SOUTH,
					LeverFacing::UP_AXIS_X => StringValues::LEVER_DIRECTION_UP_EAST_WEST,
					LeverFacing::NORTH => StringValues::LEVER_DIRECTION_NORTH,
					LeverFacing::SOUTH => StringValues::LEVER_DIRECTION_SOUTH,
					LeverFacing::WEST => StringValues::LEVER_DIRECTION_WEST,
					LeverFacing::EAST => StringValues::LEVER_DIRECTION_EAST,
					default => throw new BlockStateSerializeException("Invalid Lever facing " . $block->getFacing()->name()),
				});
		});

		$deserializer->map(Ids::TNT, function(Reader $in) : Block{
			return ExtraVanillaBlocks::TNT()
				->setUnstable($in->readBool(StateNames::EXPLODE_BIT))
				->setWorksUnderwater(false);
		});
		$serializer->map(ExtraVanillaBlocks::TNT(), fn(TNT $block) => Writer::create($block->worksUnderwater() ? Ids::UNDERWATER_TNT : Ids::TNT)
			->writeBool(StateNames::EXPLODE_BIT, $block->isUnstable())
		);

		$serializer->map(ExtraVanillaBlocks::ACACIA_BUTTON(), fn(WoodenButton $block) => BlockStateSerializerHelper::encodeButton($block, new Writer(Ids::ACACIA_BUTTON)));
		$serializer->map(ExtraVanillaBlocks::BIRCH_BUTTON(), fn(WoodenButton $block) => BlockStateSerializerHelper::encodeButton($block, new Writer(Ids::BIRCH_BUTTON)));
		$serializer->map(ExtraVanillaBlocks::CRIMSON_BUTTON(), fn(WoodenButton $block) => BlockStateSerializerHelper::encodeButton($block, new Writer(Ids::CRIMSON_BUTTON)));
		$serializer->map(ExtraVanillaBlocks::DARK_OAK_BUTTON(), fn(WoodenButton $block) => BlockStateSerializerHelper::encodeButton($block, new Writer(Ids::DARK_OAK_BUTTON)));
		$serializer->map(ExtraVanillaBlocks::JUNGLE_BUTTON(), fn(WoodenButton $block) => BlockStateSerializerHelper::encodeButton($block, new Writer(Ids::JUNGLE_BUTTON)));
		$serializer->map(ExtraVanillaBlocks::MANGROVE_BUTTON(), fn(WoodenButton $block) => BlockStateSerializerHelper::encodeButton($block, new Writer(Ids::MANGROVE_BUTTON)));
		$serializer->map(ExtraVanillaBlocks::OAK_BUTTON(), fn(WoodenButton $block) => BlockStateSerializerHelper::encodeButton($block, new Writer(Ids::WOODEN_BUTTON)));
		$serializer->map(ExtraVanillaBlocks::POLISHED_BLACKSTONE_BUTTON(), fn(StoneButton $block) => BlockStateSerializerHelper::encodeButton($block, new Writer(Ids::POLISHED_BLACKSTONE_BUTTON)));
		$serializer->map(ExtraVanillaBlocks::SPRUCE_BUTTON(), fn(WoodenButton $block) => BlockStateSerializerHelper::encodeButton($block, new Writer(Ids::SPRUCE_BUTTON)));
		$serializer->map(ExtraVanillaBlocks::STONE_BUTTON(), fn(StoneButton $block) => BlockStateSerializerHelper::encodeButton($block, new Writer(Ids::STONE_BUTTON)));
		$serializer->map(ExtraVanillaBlocks::WARPED_BUTTON(), fn(WoodenButton $block) => BlockStateSerializerHelper::encodeButton($block, new Writer(Ids::WARPED_BUTTON)));

		$deserializer->map(Ids::ACACIA_BUTTON, fn(Reader $in) => Helper::decodeButton(ExtraVanillaBlocks::ACACIA_BUTTON(), $in));
		$deserializer->map(Ids::BIRCH_BUTTON, fn(Reader $in) => Helper::decodeButton(ExtraVanillaBlocks::BIRCH_BUTTON(), $in));
		$deserializer->map(Ids::CRIMSON_BUTTON, fn(Reader $in) => Helper::decodeButton(ExtraVanillaBlocks::CRIMSON_BUTTON(), $in));
		$deserializer->map(Ids::DARK_OAK_BUTTON, fn(Reader $in) => Helper::decodeButton(ExtraVanillaBlocks::DARK_OAK_BUTTON(), $in));
		$deserializer->map(Ids::JUNGLE_BUTTON, fn(Reader $in) => Helper::decodeButton(ExtraVanillaBlocks::JUNGLE_BUTTON(), $in));
		$deserializer->map(Ids::MANGROVE_BUTTON, fn(Reader $in) => Helper::decodeButton(ExtraVanillaBlocks::MANGROVE_BUTTON(), $in));
		$deserializer->map(Ids::WOODEN_BUTTON, fn(Reader $in) => Helper::decodeButton(ExtraVanillaBlocks::OAK_BUTTON(), $in));
		$deserializer->map(Ids::POLISHED_BLACKSTONE_BUTTON, fn(Reader $in) => Helper::decodeButton(ExtraVanillaBlocks::POLISHED_BLACKSTONE_BUTTON(), $in));
		$deserializer->map(Ids::SPRUCE_BUTTON, fn(Reader $in) => Helper::decodeButton(ExtraVanillaBlocks::SPRUCE_BUTTON(), $in));
		$deserializer->map(Ids::STONE_BUTTON, fn(Reader $in) => Helper::decodeButton(ExtraVanillaBlocks::STONE_BUTTON(), $in));
		$deserializer->map(Ids::WARPED_BUTTON, fn(Reader $in) => Helper::decodeButton(ExtraVanillaBlocks::WARPED_BUTTON(), $in));

		$serializer->map(ExtraVanillaBlocks::ACACIA_PRESSURE_PLATE(), fn(WoodenPressurePlate $block) => BlockStateSerializerHelper::encodeSimplePressurePlate($block, new Writer(Ids::ACACIA_PRESSURE_PLATE)));
		$serializer->map(ExtraVanillaBlocks::BIRCH_PRESSURE_PLATE(), fn(WoodenPressurePlate $block) => BlockStateSerializerHelper::encodeSimplePressurePlate($block, new Writer(Ids::BIRCH_PRESSURE_PLATE)));
		$serializer->map(ExtraVanillaBlocks::CRIMSON_PRESSURE_PLATE(), fn(WoodenPressurePlate $block) => BlockStateSerializerHelper::encodeSimplePressurePlate($block, new Writer(Ids::CRIMSON_PRESSURE_PLATE)));
		$serializer->map(ExtraVanillaBlocks::DARK_OAK_PRESSURE_PLATE(), fn(WoodenPressurePlate $block) => BlockStateSerializerHelper::encodeSimplePressurePlate($block, new Writer(Ids::DARK_OAK_PRESSURE_PLATE)));
		$serializer->map(ExtraVanillaBlocks::WEIGHTED_PRESSURE_PLATE_HEAVY(), fn(WeightedPressurePlateHeavy $block) => Writer::create(Ids::HEAVY_WEIGHTED_PRESSURE_PLATE)->writeInt(StateNames::REDSTONE_SIGNAL, $block->getOutputSignalStrength()));
		$serializer->map(ExtraVanillaBlocks::JUNGLE_PRESSURE_PLATE(), fn(WoodenPressurePlate $block) => BlockStateSerializerHelper::encodeSimplePressurePlate($block, new Writer(Ids::JUNGLE_PRESSURE_PLATE)));
		$serializer->map(ExtraVanillaBlocks::WEIGHTED_PRESSURE_PLATE_LIGHT(), fn(WeightedPressurePlateLight $block) => Writer::create(Ids::LIGHT_WEIGHTED_PRESSURE_PLATE)->writeInt(StateNames::REDSTONE_SIGNAL, $block->getOutputSignalStrength()));
		$serializer->map(ExtraVanillaBlocks::MANGROVE_PRESSURE_PLATE(), fn(WoodenPressurePlate $block) => BlockStateSerializerHelper::encodeSimplePressurePlate($block, new Writer(Ids::MANGROVE_PRESSURE_PLATE)));
		$serializer->map(ExtraVanillaBlocks::OAK_PRESSURE_PLATE(), fn(WoodenPressurePlate $block) => BlockStateSerializerHelper::encodeSimplePressurePlate($block, new Writer(Ids::WOODEN_PRESSURE_PLATE)));
		$serializer->map(ExtraVanillaBlocks::POLISHED_BLACKSTONE_PRESSURE_PLATE(), fn(StonePressurePlate $block) => BlockStateSerializerHelper::encodeSimplePressurePlate($block, new Writer(Ids::POLISHED_BLACKSTONE_PRESSURE_PLATE)));
		$serializer->map(ExtraVanillaBlocks::SPRUCE_PRESSURE_PLATE(), fn(WoodenPressurePlate $block) => BlockStateSerializerHelper::encodeSimplePressurePlate($block, new Writer(Ids::SPRUCE_PRESSURE_PLATE)));
		$serializer->map(ExtraVanillaBlocks::STONE_PRESSURE_PLATE(), fn(StonePressurePlate $block) => BlockStateSerializerHelper::encodeSimplePressurePlate($block, new Writer(Ids::STONE_PRESSURE_PLATE)));
		$serializer->map(ExtraVanillaBlocks::WARPED_PRESSURE_PLATE(), fn(WoodenPressurePlate $block) => BlockStateSerializerHelper::encodeSimplePressurePlate($block, new Writer(Ids::WARPED_PRESSURE_PLATE)));

		$deserializer->map(Ids::ACACIA_PRESSURE_PLATE, fn(Reader $in) => Helper::decodeSimplePressurePlate(ExtraVanillaBlocks::ACACIA_PRESSURE_PLATE(), $in));
		$deserializer->map(Ids::BIRCH_PRESSURE_PLATE, fn(Reader $in) => Helper::decodeSimplePressurePlate(ExtraVanillaBlocks::BIRCH_PRESSURE_PLATE(), $in));
		$deserializer->map(Ids::CRIMSON_PRESSURE_PLATE, fn(Reader $in) => Helper::decodeSimplePressurePlate(ExtraVanillaBlocks::CRIMSON_PRESSURE_PLATE(), $in));
		$deserializer->map(Ids::DARK_OAK_PRESSURE_PLATE, fn(Reader $in) => Helper::decodeSimplePressurePlate(ExtraVanillaBlocks::DARK_OAK_PRESSURE_PLATE(), $in));
		$deserializer->map(Ids::HEAVY_WEIGHTED_PRESSURE_PLATE, fn(Reader $in) => Helper::decodeWeightedPressurePlate(ExtraVanillaBlocks::WEIGHTED_PRESSURE_PLATE_HEAVY(), $in));
		$deserializer->map(Ids::JUNGLE_PRESSURE_PLATE, fn(Reader $in) => Helper::decodeSimplePressurePlate(ExtraVanillaBlocks::JUNGLE_PRESSURE_PLATE(), $in));
		$deserializer->map(Ids::LIGHT_WEIGHTED_PRESSURE_PLATE, fn(Reader $in) => Helper::decodeWeightedPressurePlate(ExtraVanillaBlocks::WEIGHTED_PRESSURE_PLATE_LIGHT(), $in));
		$deserializer->map(Ids::MANGROVE_PRESSURE_PLATE, fn(Reader $in) => Helper::decodeSimplePressurePlate(ExtraVanillaBlocks::MANGROVE_PRESSURE_PLATE(), $in));
		$deserializer->map(Ids::WOODEN_PRESSURE_PLATE, fn(Reader $in) => Helper::decodeSimplePressurePlate(ExtraVanillaBlocks::OAK_PRESSURE_PLATE(), $in));
		$deserializer->map(Ids::POLISHED_BLACKSTONE_PRESSURE_PLATE, fn(Reader $in) => Helper::decodeSimplePressurePlate(ExtraVanillaBlocks::POLISHED_BLACKSTONE_PRESSURE_PLATE(), $in));
		$deserializer->map(Ids::SPRUCE_PRESSURE_PLATE, fn(Reader $in) => Helper::decodeSimplePressurePlate(ExtraVanillaBlocks::SPRUCE_PRESSURE_PLATE(), $in));
		$deserializer->map(Ids::STONE_PRESSURE_PLATE, fn(Reader $in) => Helper::decodeSimplePressurePlate(ExtraVanillaBlocks::STONE_PRESSURE_PLATE(), $in));
		$deserializer->map(Ids::WARPED_PRESSURE_PLATE, fn(Reader $in) => Helper::decodeSimplePressurePlate(ExtraVanillaBlocks::WARPED_PRESSURE_PLATE(), $in));
	}

	private static function registerOpenableBlocks() : void{
		$serializer = GlobalBlockStateHandlers::getSerializer();
		$registrar = new BlockSerializerDeserializerRegistrar(GlobalBlockStateHandlers::getDeserializer(), $serializer);
		$common_properties = CommonProperties::getInstance();
		$extra_blocks = ExtraVanillaBlocks::getAll();

		foreach(VanillaBlocks::getAll() as $name => $vanilla){
			$block = $extra_blocks[mb_strtoupper($name)] ?? null;
			if($block instanceof CopperTrapdoor){
				RuntimeBlockStateRegistry::getInstance()->register($block);
				$registrar->mapFlattenedId(FlattenedIdModel::create($block)
					->idComponents([...$common_properties->copperIdPrefixes, "copper_trapdoor"])
					->properties($common_properties->trapdoorProperties)
				);
			}elseif($block instanceof Trapdoor || $block instanceof WoodenTrapdoor){
				RuntimeBlockStateRegistry::getInstance()->register($block);
				$registrar->mapModel(Model::create($block, $serializer->serializeBlock($vanilla)->getName())->properties($common_properties->trapdoorProperties));
			}elseif($block instanceof FenceGate){
				RuntimeBlockStateRegistry::getInstance()->register($block);
				$registrar->mapModel(Model::create($block, $serializer->serializeBlock($vanilla)->getName())->properties($common_properties->fenceGateProperties));
			}else{
				continue;
			}
			self::mapStringToItem($block, StringToItemParser::getInstance()->lookupBlockAliases($vanilla));
		}
	}

	private static function observerFacingMap() : IntFromRawStateMap{
		static $map = null;
		return $map ??= IntFromRawStateMap::string([
			Facing::DOWN => StringValues::MC_FACING_DIRECTION_DOWN,
			Facing::UP => StringValues::MC_FACING_DIRECTION_UP,
			Facing::NORTH => StringValues::MC_FACING_DIRECTION_NORTH,
			Facing::SOUTH => StringValues::MC_FACING_DIRECTION_SOUTH,
			Facing::WEST => StringValues::MC_FACING_DIRECTION_WEST,
			Facing::EAST => StringValues::MC_FACING_DIRECTION_EAST,
		]);
	}

	private static function registerItems() : void{
		$parser = StringToItemParser::getInstance();
		self::registerSimpleItem(ItemTypeNames::REDSTONE, ExtraVanillaItems::REDSTONE_DUST(), $parser->lookupAliases(VanillaItems::REDSTONE_DUST()));
		self::map1to1Block(ItemTypeNames::COMPARATOR, ExtraVanillaBlocks::REDSTONE_COMPARATOR(), $parser->lookupBlockAliases(VanillaBlocks::REDSTONE_COMPARATOR()));
		self::map1to1Block(ItemTypeNames::REPEATER, ExtraVanillaBlocks::REDSTONE_REPEATER(), $parser->lookupBlockAliases(VanillaBlocks::REDSTONE_REPEATER()));
		self::map1to1Block(ItemTypeNames::HOPPER, ExtraVanillaBlocks::HOPPER(), $parser->lookupBlockAliases(VanillaBlocks::HOPPER()));
		self::mapStringToItem(ExtraVanillaBlocks::TRAPPED_CHEST(), $parser->lookupBlockAliases(VanillaBlocks::TRAPPED_CHEST()));
		self::map1to1Block(Ids::PISTON, ExtraVanillaBlocks::PISTON(), []);
		self::map1to1Block(Ids::STICKY_PISTON, ExtraVanillaBlocks::STICKY_PISTON(), []);

		foreach([
			[ItemTypeNames::IRON_DOOR, VanillaBlocks::IRON_DOOR(), ExtraVanillaBlocks::IRON_DOOR()],
			[ItemTypeNames::JUNGLE_DOOR, VanillaBlocks::JUNGLE_DOOR(), ExtraVanillaBlocks::JUNGLE_DOOR()],
			[ItemTypeNames::MANGROVE_DOOR, VanillaBlocks::MANGROVE_DOOR(), ExtraVanillaBlocks::MANGROVE_DOOR()],
			[ItemTypeNames::PALE_OAK_DOOR, VanillaBlocks::PALE_OAK_DOOR(), ExtraVanillaBlocks::PALE_OAK_DOOR()],
			[ItemTypeNames::SPRUCE_DOOR, VanillaBlocks::SPRUCE_DOOR(), ExtraVanillaBlocks::SPRUCE_DOOR()],
			[ItemTypeNames::WARPED_DOOR, VanillaBlocks::WARPED_DOOR(), ExtraVanillaBlocks::WARPED_DOOR()],
			[ItemTypeNames::WOODEN_DOOR, VanillaBlocks::OAK_DOOR(), ExtraVanillaBlocks::OAK_DOOR()],
		] as [$id, $vanilla, $new]){
			self::unregisterSimpleItem($id, $vanilla->asItem());
			self::map1to1Block($id, $new, $parser->lookupBlockAliases($vanilla));
		}
	}

	/**
	 * @param string $id
	 * @param Block $block
	 * @param string[] $stringToItemParserNames
	 */
	private static function map1to1Block(string $id, Block $block, array $stringToItemParserNames) : void{
		GlobalItemDataHandlers::getDeserializer()->mapBlock($id, fn() => $block);
		GlobalItemDataHandlers::getSerializer()->mapBlock($block, fn() => new SavedItemData($id));
		self::mapStringToItem($block, $stringToItemParserNames);
	}

	/**
	 * @param string[] $stringToItemParserNames
	 */
	private static function registerSimpleBlock(string $id, Block $block, array $stringToItemParserNames, bool $map_serializer = true) : void{
		RuntimeBlockStateRegistry::getInstance()->register($block);

		self::unmapBlockSerializers($id, $block);
		if($map_serializer){
			self::mapBlockSerializers($id, $block);
		}

		self::mapStringToItem($block, $stringToItemParserNames);
	}

	/**
	 * @param string $name
	 * @param Block $block
	 * @param string[] $stringToItemParserNames
	 */
	private static function mapStringToItem(Block $block, array $stringToItemParserNames) : void{
		$parser = StringToItemParser::getInstance();
		foreach($stringToItemParserNames as $name){
			try{
				$parser->registerBlock($name, fn() => clone $block);
			}catch(InvalidArgumentException){
				$existing = $parser->parse($name);
				$parser->override($name, fn() => (clone $block)->asItem());
				$_reverseMap = new ReflectionProperty($parser, "reverseMap");
				$reverseMap = $_reverseMap->getValue($parser);
				$reverseMap[$existing->getStateId()][$name] = true;
				$_reverseMap->setValue($parser, $reverseMap);
			}
		}
	}

	private static function unmapBlockSerializers(string $id, Block $block) : void{
		$deserializer = GlobalBlockStateHandlers::getDeserializer();

		$_property = new ReflectionProperty($deserializer, "deserializeFuncs");
		$value = $_property->getValue($deserializer);
		unset($value[$id]);
		$_property->setValue($deserializer, $value);

		$_property = new ReflectionProperty($deserializer, "simpleCache");
		$value = $_property->getValue($deserializer);
		unset($value[$id]);
		$_property->setValue($deserializer, $value);

		$serializer = GlobalBlockStateHandlers::getSerializer();

		$_property = new ReflectionProperty($serializer, "serializers");
		$value = $_property->getValue($serializer);
		unset($value[$block->getTypeId()]);
		$_property->setValue($serializer, $value);

		$_property = new ReflectionProperty($serializer, "cache");
		$value = $_property->getValue($serializer);
		unset($value[$block->getTypeId()]);
		$_property->setValue($serializer, $value);
	}

	private static function mapBlockSerializers(string $id, Block $block) : void{
		GlobalBlockStateHandlers::getDeserializer()->mapSimple($id, fn() => clone $block);
		GlobalBlockStateHandlers::getSerializer()->mapSimple($block, $id);
	}

	private static function unregisterSimpleItem(string $id, Item $item) : void{
		$deserializer = GlobalItemDataHandlers::getDeserializer();

		$_property = new ReflectionProperty($deserializer, "deserializers");
		$value = $_property->getValue($deserializer);
		unset($value[$id]);
		$_property->setValue($deserializer, $value);

		$serializer = GlobalItemDataHandlers::getSerializer();

		$_property = new ReflectionProperty($serializer, "itemSerializers");
		$value = $_property->getValue($serializer);
		unset($value[$item->getTypeId()]);
		$_property->setValue($serializer, $value);

		$_property = new ReflectionProperty($serializer, "blockItemSerializers");
		$value = $_property->getValue($serializer);
		unset($value[$item->getTypeId()]);
		$_property->setValue($serializer, $value);
	}

	/**
	 * @param string[] $stringToItemParserNames
	 */
	private static function registerSimpleItem(string $id, Item $item, array $stringToItemParserNames) : void{
		self::unregisterSimpleItem($id, $item);
		GlobalItemDataHandlers::getDeserializer()->map($id, fn() => clone $item);
		GlobalItemDataHandlers::getSerializer()->map($item, fn() => new SavedItemData($id));

		foreach($stringToItemParserNames as $name){
			try{
				StringToItemParser::getInstance()->register($name, fn() => clone $item);
			}catch(InvalidArgumentException){
				StringToItemParser::getInstance()->override($name, fn() => clone $item);
			}
		}
	}
}