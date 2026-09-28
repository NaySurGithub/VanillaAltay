<?php

declare(strict_types=1);

namespace behaviorpack\custom\item;

use pocketmine\block\Block;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\IntTag;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use Throwable;
use function method_exists;
use function mt_rand;

/**
 * Makes the composter work: items with a composting chance (the vanilla
 * ones and those with minecraft:compostable) raise its fill level, level 7
 * turns into 8 after a second, and level 8 gives bone meal.
 */
final class ComposterListener implements Listener{

	private const BLOCK = "minecraft:composter";
	private const STATE = "composter_fill_level";

	private const VANILLA_CHANCES = [
		"minecraft:wheat_seeds" => 30, "minecraft:beetroot_seeds" => 30, "minecraft:melon_seeds" => 30, "minecraft:pumpkin_seeds" => 30,
		"minecraft:torchflower_seeds" => 30, "minecraft:pitcher_pod" => 30, "minecraft:glow_berries" => 30, "minecraft:sweet_berries" => 30,
		"minecraft:kelp" => 30, "minecraft:dried_kelp" => 30, "minecraft:short_grass" => 30, "minecraft:tall_grass" => 50,
		"minecraft:oak_leaves" => 30, "minecraft:spruce_leaves" => 30, "minecraft:birch_leaves" => 30, "minecraft:jungle_leaves" => 30,
		"minecraft:acacia_leaves" => 30, "minecraft:dark_oak_leaves" => 30, "minecraft:mangrove_leaves" => 30, "minecraft:cherry_leaves" => 30,
		"minecraft:azalea_leaves" => 30, "minecraft:oak_sapling" => 30, "minecraft:spruce_sapling" => 30, "minecraft:birch_sapling" => 30,
		"minecraft:jungle_sapling" => 30, "minecraft:acacia_sapling" => 30, "minecraft:dark_oak_sapling" => 30, "minecraft:cherry_sapling" => 30,
		"minecraft:seagrass" => 30, "minecraft:moss_carpet" => 30, "minecraft:hanging_roots" => 30, "minecraft:small_dripleaf_block" => 30,
		"minecraft:cactus" => 50, "minecraft:sugar_cane" => 50, "minecraft:vine" => 50, "minecraft:melon_slice" => 50,
		"minecraft:nether_sprouts" => 50, "minecraft:glow_lichen" => 50, "minecraft:twisting_vines" => 50, "minecraft:weeping_vines" => 50,
		"minecraft:apple" => 65, "minecraft:beetroot" => 65, "minecraft:carrot" => 65, "minecraft:cocoa_beans" => 65, "minecraft:potato" => 65,
		"minecraft:wheat" => 65, "minecraft:melon_block" => 65, "minecraft:pumpkin" => 65, "minecraft:carved_pumpkin" => 65,
		"minecraft:brown_mushroom" => 65, "minecraft:red_mushroom" => 65, "minecraft:lily_pad" => 65, "minecraft:waterlily" => 65,
		"minecraft:fern" => 65, "minecraft:large_fern" => 65, "minecraft:dandelion" => 65, "minecraft:poppy" => 65, "minecraft:big_dripleaf" => 65,
		"minecraft:moss_block" => 65, "minecraft:spore_blossom" => 65, "minecraft:crimson_fungus" => 65, "minecraft:warped_fungus" => 65,
		"minecraft:baked_potato" => 85, "minecraft:bread" => 85, "minecraft:cookie" => 85, "minecraft:hay_block" => 85,
		"minecraft:brown_mushroom_block" => 85, "minecraft:red_mushroom_block" => 85, "minecraft:nether_wart_block" => 85,
		"minecraft:warped_wart_block" => 85, "minecraft:flowering_azalea" => 85, "minecraft:cake" => 100, "minecraft:pumpkin_pie" => 100
	];

	public function __construct(
		private PluginBase $plugin
	){}

	private function blockName(Block $block) : ?string{
		try{
			return GlobalBlockStateHandlers::getSerializer()->serializeBlock($block)->getName();
		}catch(Throwable){
			return null;
		}
	}

	private function fillLevel(Block $block) : int{
		try{
			$tag = GlobalBlockStateHandlers::getSerializer()->serializeBlock($block)->getState(self::STATE);
		}catch(Throwable){
			return 0;
		}
		return $tag instanceof IntTag ? $tag->getValue() : 0;
	}

	private function setFillLevel(Block $block, int $level) : void{
		$world = $block->getPosition()->getWorld();
		try{
			$data = GlobalBlockStateHandlers::getSerializer()->serializeBlock($block);
			$states = $data->getStates();
			$states[self::STATE] = new IntTag($level);
			$updated = GlobalBlockStateHandlers::getDeserializer()->deserializeBlock(new BlockStateData($data->getName(), $states, $data->getVersion()));
		}catch(Throwable){
			return;
		}
		$world->setBlock($block->getPosition(), $updated);
	}

	/**
	 * Returns the composting chance of an item in percent.
	 */
	public static function chanceOf(Item $item) : int{
		if(method_exists($item, "getCompostingChance")){
			$chance = $item->getCompostingChance();
			if($chance > 0){
				return $chance;
			}
		}
		try{
			$name = GlobalItemDataHandlers::getSerializer()->serializeType($item)->getName();
		}catch(Throwable){
			return 0;
		}
		return self::VANILLA_CHANCES[$name] ?? 0;
	}

	/**
	 * @priority HIGH
	 */
	public function onInteract(PlayerInteractEvent $event) : void{
		if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){
			return;
		}
		$block = $event->getBlock();
		if($this->blockName($block) !== self::BLOCK){
			return;
		}
		$player = $event->getPlayer();
		$level = $this->fillLevel($block);
		$position = $block->getPosition();
		if($level >= 8){
			$event->cancel();
			$this->setFillLevel($block, 0);
			$position->getWorld()->dropItem($position->add(0.5, 1.0, 0.5), VanillaItems::BONE_MEAL(), new Vector3(0, 0.1, 0));
			return;
		}
		if($level === 7){
			$event->cancel();
			return;
		}
		$item = $event->getItem();
		$chance = self::chanceOf($item);
		if($chance <= 0){
			return;
		}
		$event->cancel();
		if(!$player->isCreative()){
			$item->pop();
			$player->getInventory()->setItemInHand($item);
		}
		if($level > 0 && mt_rand(1, 100) > $chance){
			return;
		}
		$next = $level + 1;
		$this->setFillLevel($block, $next);
		if($next === 7){
			$this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($position) : void{
				if(!$position->isValid()){
					return;
				}
				$current = $position->getWorld()->getBlock($position);
				if($this->blockName($current) === self::BLOCK && $this->fillLevel($current) === 7){
					$this->setFillLevel($current, 8);
				}
			}), 20);
		}
	}
}
