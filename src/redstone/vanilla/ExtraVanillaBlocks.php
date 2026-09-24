<?php

declare(strict_types=1);

namespace redstone\vanilla;

use pocketmine\block\Block;
use pocketmine\block\BlockBreakInfo as BreakInfo;
use pocketmine\block\BlockIdentifier as BID;
use pocketmine\block\BlockToolType;
use pocketmine\block\BlockTypeIds as Ids;
use pocketmine\block\BlockTypeInfo as Info;
use pocketmine\block\PressurePlate;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\WeightedPressurePlate;
use pocketmine\item\ToolTier;
use pocketmine\utils\CloningRegistryTrait;
use redstone\block\CopperDoor;
use redstone\block\CopperTrapdoor;
use redstone\block\Dispenser;
use redstone\block\Door;
use redstone\block\FenceGate;
use redstone\block\Hopper;
use redstone\block\Lever;
use redstone\block\Observer;
use redstone\block\Piston;
use redstone\block\PistonArmBlock;
use redstone\block\Redstone;
use redstone\block\RedstoneComparator;
use redstone\block\RedstoneLamp;
use redstone\block\RedstoneOre;
use redstone\block\RedstoneRepeater;
use redstone\block\RedstoneTorch;
use redstone\block\RedstoneWire;
use redstone\block\StoneButton;
use redstone\block\StonePressurePlate;
use redstone\block\tile\dispenser\Dispenser as DispenserTile;
use redstone\block\tile\dispenser\Dropper as DropperTile;
use redstone\block\tile\piston\PistonArm;
use redstone\block\TNT;
use redstone\block\Trapdoor;
use redstone\block\TrappedChest;
use redstone\block\WeightedPressurePlateHeavy;
use redstone\block\WeightedPressurePlateLight;
use redstone\block\WoodenButton;
use redstone\block\WoodenDoor;
use redstone\block\WoodenPressurePlate;
use redstone\block\WoodenTrapdoor;
use ReflectionProperty;

/**
 * * @method static Dispenser DISPENSER()
 * * @method static Dispenser DROPPER()
 * * @method static Hopper HOPPER()
 * * @method static TrappedChest TRAPPED_CHEST()
 * * @method static Lever LEVER()
 * * @method static Observer OBSERVER()
 * * @method static Redstone REDSTONE()
 * * @method static RedstoneOre REDSTONE_ORE()
 * * @method static RedstoneWire REDSTONE_WIRE()
 * * @method static PistonArmBlock PISTON_ARM_BLOCK()
 * * @method static Piston PISTON()
 * * @method static Piston STICKY_PISTON()
 * * @method static Door IRON_DOOR()
 * * @method static RedstoneComparator REDSTONE_COMPARATOR()
 * * @method static RedstoneLamp REDSTONE_LAMP()
 * * @method static RedstoneRepeater REDSTONE_REPEATER()
 * * @method static RedstoneTorch REDSTONE_TORCH()
 * * @method static StoneButton STONE_BUTTON()
 * * @method static StonePressurePlate STONE_PRESSURE_PLATE()
 * * @method static TNT TNT()
 * * @method static WeightedPressurePlateHeavy WEIGHTED_PRESSURE_PLATE_HEAVY()
 * * @method static WeightedPressurePlateLight WEIGHTED_PRESSURE_PLATE_LIGHT()
 * * @method static StoneButton POLISHED_BLACKSTONE_BUTTON()
 * * @method static StonePressurePlate POLISHED_BLACKSTONE_PRESSURE_PLATE()
 * * @method static CopperDoor COPPER_DOOR()
 * * @method static WoodenDoor OAK_DOOR()
 * * @method static WoodenButton OAK_BUTTON()
 * * @method static WoodenPressurePlate OAK_PRESSURE_PLATE()
 * * @method static WoodenDoor SPRUCE_DOOR()
 * * @method static WoodenButton SPRUCE_BUTTON()
 * * @method static WoodenPressurePlate SPRUCE_PRESSURE_PLATE()
 * * @method static WoodenDoor BIRCH_DOOR()
 * * @method static WoodenButton BIRCH_BUTTON()
 * * @method static WoodenPressurePlate BIRCH_PRESSURE_PLATE()
 * * @method static WoodenDoor JUNGLE_DOOR()
 * * @method static WoodenButton JUNGLE_BUTTON()
 * * @method static WoodenPressurePlate JUNGLE_PRESSURE_PLATE()
 * * @method static WoodenDoor ACACIA_DOOR()
 * * @method static WoodenButton ACACIA_BUTTON()
 * * @method static WoodenPressurePlate ACACIA_PRESSURE_PLATE()
 * * @method static WoodenDoor DARK_OAK_DOOR()
 * * @method static WoodenButton DARK_OAK_BUTTON()
 * * @method static WoodenPressurePlate DARK_OAK_PRESSURE_PLATE()
 * * @method static WoodenDoor MANGROVE_DOOR()
 * * @method static WoodenButton MANGROVE_BUTTON()
 * * @method static WoodenPressurePlate MANGROVE_PRESSURE_PLATE()
 * * @method static WoodenDoor CRIMSON_DOOR()
 * * @method static WoodenButton CRIMSON_BUTTON()
 * * @method static WoodenPressurePlate CRIMSON_PRESSURE_PLATE()
 * * @method static WoodenDoor WARPED_DOOR()
 * * @method static WoodenButton WARPED_BUTTON()
 * * @method static WoodenPressurePlate WARPED_PRESSURE_PLATE()
 * * @method static WoodenDoor CHERRY_DOOR()
 * * @method static WoodenButton CHERRY_BUTTON()
 * * @method static WoodenPressurePlate CHERRY_PRESSURE_PLATE()
 * * @method static WoodenDoor PALE_OAK_DOOR()
 * * @method static WoodenButton PALE_OAK_BUTTON()
 * * @method static WoodenPressurePlate PALE_OAK_PRESSURE_PLATE()
 */
final class ExtraVanillaBlocks{
	use CloningRegistryTrait;

	private function __construct(){
		//NOOP
	}

	protected static function register(string $name, Block $block) : void{
		self::_registryRegister($name, $block);
	}

	/**
	 * @return Block[]
	 * @phpstan-return array<string, Block>
	 */
	public static function getAll() : array{
		//phpstan doesn't support generic traits yet :(
		/** @var Block[] $result */
		$result = self::_registryGetAll();
		return $result;
	}

	protected static function setup() : void{
		self::register("dispenser", new Dispenser(new BID(Ids::newId(), DispenserTile::class), "Dispenser", new Info(new BreakInfo(3.5, BlockToolType::PICKAXE))));
		self::register("dropper", new Dispenser(new BID(Ids::newId(), DropperTile::class), "Dropper", new Info(new BreakInfo(3.5, BlockToolType::PICKAXE))));

		self::register("observer", new Observer(new BID(Ids::newId()), "Observer", new Info(BreakInfo::pickaxe(3.0, ToolTier::WOOD))));

		$block = VanillaBlocks::LEVER();
		self::register("lever", new Lever(new BID(Ids::newId(), $block->getIdInfo()->getTileClass()), $block->getName(), new Info($block->getBreakInfo(), $block->getTypeTags())));

		$block = VanillaBlocks::REDSTONE();
		self::register("redstone", new Redstone(new BID(Ids::newId(), $block->getIdInfo()->getTileClass()), $block->getName(), new Info($block->getBreakInfo(), $block->getTypeTags())));

		$block = VanillaBlocks::REDSTONE_ORE();
		self::register("redstone_ore", new RedstoneOre(new BID(Ids::newId(), $block->getIdInfo()->getTileClass()), $block->getName(), new Info($block->getBreakInfo(), $block->getTypeTags())));

		$block = VanillaBlocks::REDSTONE_WIRE();
		self::register("redstone_wire", new RedstoneWire(new BID(Ids::newId(), $block->getIdInfo()->getTileClass()), $block->getName(), new Info($block->getBreakInfo(), $block->getTypeTags())));

		self::register("piston_arm_block", new PistonArmBlock(new BID(Ids::newId()), "Piston Arm", new Info(new BreakInfo(0.5))));
		self::register("piston", new Piston(new BID(Ids::newId(), PistonArm::class), "Piston", new Info(new BreakInfo(0.5)), false));
		self::register("sticky_piston", new Piston(new BID(Ids::newId(), PistonArm::class), "Sticky Piston", new Info(new BreakInfo(0.5)), true));

		foreach(VanillaBlocks::getAll() as $identifier => $block){
			if($block instanceof \pocketmine\block\RedstoneComparator){
				self::register($identifier, new RedstoneComparator(new BID(Ids::newId(), $block->getIdInfo()->getTileClass()), $block->getName(), new Info($block->getBreakInfo(), $block->getTypeTags())));
			}elseif($block instanceof \pocketmine\block\RedstoneLamp){
				self::register($identifier, new RedstoneLamp(new BID(Ids::newId(), $block->getIdInfo()->getTileClass()), $block->getName(), new Info($block->getBreakInfo(), $block->getTypeTags())));
			}elseif($block instanceof \pocketmine\block\RedstoneRepeater){
				self::register($identifier, new RedstoneRepeater(new BID(Ids::newId(), $block->getIdInfo()->getTileClass()), $block->getName(), new Info($block->getBreakInfo(), $block->getTypeTags())));
			}elseif($block instanceof \pocketmine\block\RedstoneTorch){
				self::register($identifier, new RedstoneTorch(new BID(Ids::newId(), $block->getIdInfo()->getTileClass()), $block->getName(), new Info($block->getBreakInfo(), $block->getTypeTags())));
			}elseif($block instanceof \pocketmine\block\StoneButton){
				self::register($identifier, new StoneButton(new BID(Ids::newId(), $block->getIdInfo()->getTileClass()), $block->getName(), new Info($block->getBreakInfo(), $block->getTypeTags())));
			}elseif($block instanceof \pocketmine\block\StonePressurePlate){
				$deactivationDelayTicks = (new ReflectionProperty(PressurePlate::class, "deactivationDelayTicks"))->getValue($block);
				self::register($identifier, new StonePressurePlate(new BID(Ids::newId(), $block->getIdInfo()->getTileClass()), $block->getName(), new Info($block->getBreakInfo(), $block->getTypeTags()), $deactivationDelayTicks));
			}elseif($block instanceof \pocketmine\block\TNT){
				self::register($identifier, new TNT(new BID(Ids::newId(), $block->getIdInfo()->getTileClass()), $block->getName(), new Info($block->getBreakInfo(), $block->getTypeTags())));
			}elseif($block instanceof \pocketmine\block\WeightedPressurePlateHeavy){
				$deactivationDelayTicks = (new ReflectionProperty(PressurePlate::class, "deactivationDelayTicks"))->getValue($block);
				$signalStrengthFactor = (new ReflectionProperty(WeightedPressurePlate::class, "signalStrengthFactor"))->getValue($block);
				self::register($identifier, new WeightedPressurePlateHeavy(new BID(Ids::newId(), $block->getIdInfo()->getTileClass()), $block->getName(), new Info($block->getBreakInfo(), $block->getTypeTags()), $deactivationDelayTicks, $signalStrengthFactor));
			}elseif($block instanceof \pocketmine\block\WeightedPressurePlateLight){
				$deactivationDelayTicks = (new ReflectionProperty(PressurePlate::class, "deactivationDelayTicks"))->getValue($block);
				$signalStrengthFactor = (new ReflectionProperty(WeightedPressurePlate::class, "signalStrengthFactor"))->getValue($block);
				self::register($identifier, new WeightedPressurePlateLight(new BID(Ids::newId(), $block->getIdInfo()->getTileClass()), $block->getName(), new Info($block->getBreakInfo(), $block->getTypeTags()), $deactivationDelayTicks, $signalStrengthFactor));
			}elseif($block instanceof \pocketmine\block\WoodenButton){
				self::register($identifier, new WoodenButton(new BID(Ids::newId(), $block->getIdInfo()->getTileClass()), $block->getName(), new Info($block->getBreakInfo(), $block->getTypeTags()), $block->getWoodType()));
			}elseif($block instanceof \pocketmine\block\WoodenPressurePlate){
				self::register($identifier, new WoodenPressurePlate(new BID(Ids::newId(), $block->getIdInfo()->getTileClass()), $block->getName(), new Info($block->getBreakInfo(), $block->getTypeTags()), $block->getWoodType()));
			}elseif($block instanceof \pocketmine\block\Hopper){
				self::register($identifier, new Hopper(new BID(Ids::newId(), $block->getIdInfo()->getTileClass()), $block->getName(), new Info($block->getBreakInfo(), $block->getTypeTags())));
			}elseif($block instanceof \pocketmine\block\TrappedChest){
				self::register($identifier, new TrappedChest(new BID(Ids::newId(), $block->getIdInfo()->getTileClass()), $block->getName(), new Info($block->getBreakInfo(), $block->getTypeTags())));
			}elseif($block instanceof \pocketmine\block\CopperTrapdoor){
				self::register($identifier, new CopperTrapdoor(new BID(Ids::newId(), $block->getIdInfo()->getTileClass()), $block->getName(), new Info($block->getBreakInfo(), $block->getTypeTags())));
			}elseif($block instanceof \pocketmine\block\WoodenTrapdoor){
				self::register($identifier, new WoodenTrapdoor(new BID(Ids::newId(), $block->getIdInfo()->getTileClass()), $block->getName(), new Info($block->getBreakInfo(), $block->getTypeTags()), $block->getWoodType()));
			}elseif($block instanceof \pocketmine\block\Trapdoor){
				self::register($identifier, new Trapdoor(new BID(Ids::newId(), $block->getIdInfo()->getTileClass()), $block->getName(), new Info($block->getBreakInfo(), $block->getTypeTags())));
			}elseif($block instanceof \pocketmine\block\FenceGate){
				self::register($identifier, new FenceGate(new BID(Ids::newId(), $block->getIdInfo()->getTileClass()), $block->getName(), new Info($block->getBreakInfo(), $block->getTypeTags()), $block->getWoodType()));
			}elseif($block instanceof \pocketmine\block\CopperDoor){
				self::register($identifier, new CopperDoor(new BID(Ids::newId(), $block->getIdInfo()->getTileClass()), $block->getName(), new Info($block->getBreakInfo(), $block->getTypeTags())));
			}elseif($block instanceof \pocketmine\block\WoodenDoor){
				self::register($identifier, new WoodenDoor(new BID(Ids::newId(), $block->getIdInfo()->getTileClass()), $block->getName(), new Info($block->getBreakInfo(), $block->getTypeTags()), $block->getWoodType()));
			}elseif($block instanceof \pocketmine\block\Door){
				self::register($identifier, new Door(new BID(Ids::newId(), $block->getIdInfo()->getTileClass()), $block->getName(), new Info($block->getBreakInfo(), $block->getTypeTags())));
			}
		}
	}
}