<?php

declare(strict_types=1);

namespace dimension\portal;

use dimension\portal\block\EndPortal;
use dimension\portal\item\EnderEye;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\crafting\CraftingManager;
use pocketmine\crafting\ExactRecipeIngredient;
use pocketmine\crafting\ShapelessRecipe;
use pocketmine\crafting\ShapelessRecipeType;
use pocketmine\data\bedrock\block\BlockTypeNames;
use pocketmine\data\bedrock\item\ItemTypeNames;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\inventory\CreativeInventory;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\scheduler\AsyncPool;
use pocketmine\scheduler\AsyncTask;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use InvalidArgumentException;

/**
 * Registers the end portal block and the eye of ender item, which the server
 * does not provide. The block is registered on every thread with the type id
 * chosen on the main thread so runtime state ids match across threads.
 */
final class PortalContent{

	private static ?EndPortal $endPortal = null;
	private static ?EnderEye $enderEye = null;

	private function __construct(){
	}

	public static function endPortal() : EndPortal{
		if(self::$endPortal === null){
			throw new \LogicException("Portal content is not registered");
		}
		return clone self::$endPortal;
	}

	public static function enderEye() : EnderEye{
		if(self::$enderEye === null){
			throw new \LogicException("Portal content is not registered");
		}
		return clone self::$enderEye;
	}

	public static function register(AsyncPool $pool, CraftingManager $craftingManager) : void{
		if(self::$endPortal !== null){
			return;
		}
		$typeId = BlockTypeIds::newId();
		self::registerBlock($typeId);
		self::registerItem();
		self::registerRecipe($craftingManager);

		$pool->addWorkerStartHook(function(int $worker) use($pool, $typeId) : void{
			$pool->submitTaskToWorker(new class($typeId) extends AsyncTask{
				public function __construct(
					private int $typeId
				){
				}

				public function onRun() : void{
					PortalContent::registerBlock($this->typeId);
				}
			}, $worker);
		});
	}

	/**
	 * @internal
	 */
	public static function registerBlock(int $typeId) : void{
		if(self::$endPortal !== null){
			return;
		}
		$block = new EndPortal(new BlockIdentifier($typeId), "End Portal", new BlockTypeInfo(BlockBreakInfo::indestructible()));
		RuntimeBlockStateRegistry::getInstance()->register($block);
		GlobalBlockStateHandlers::getDeserializer()->mapSimple(BlockTypeNames::END_PORTAL, fn() => clone $block);
		GlobalBlockStateHandlers::getSerializer()->mapSimple($block, BlockTypeNames::END_PORTAL);
		self::$endPortal = $block;
	}

	private static function registerItem() : void{
		$item = new EnderEye(new ItemIdentifier(ItemTypeIds::newId()), "Eye of Ender");
		GlobalItemDataHandlers::getDeserializer()->map(ItemTypeNames::ENDER_EYE, fn() => clone $item);
		GlobalItemDataHandlers::getSerializer()->map($item, fn() => new SavedItemData(ItemTypeNames::ENDER_EYE));
		foreach(["ender_eye", "eye_of_ender"] as $name){
			try{
				StringToItemParser::getInstance()->register($name, fn() => clone $item);
			}catch(InvalidArgumentException){
				StringToItemParser::getInstance()->override($name, fn() => clone $item);
			}
		}
		CreativeInventory::getInstance()->add($item);
		self::$enderEye = $item;
	}

	private static function registerRecipe(CraftingManager $craftingManager) : void{
		$craftingManager->registerShapelessRecipe(new ShapelessRecipe(
			[
				new ExactRecipeIngredient(VanillaItems::ENDER_PEARL()),
				new ExactRecipeIngredient(VanillaItems::BLAZE_POWDER())
			],
			[self::enderEye()],
			ShapelessRecipeType::CRAFTING
		));
	}
}
