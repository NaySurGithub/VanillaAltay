<?php

declare(strict_types=1);

namespace behaviorpack\custom;

use behaviorpack\BehaviorPack;
use behaviorpack\ContentLoader;
use Closure;
use customiesdevs\customies\item\CreativeInventoryInfo;
use pocketmine\plugin\PluginBase;
use Throwable;
use function in_array;
use function is_array;
use function is_string;
use function str_starts_with;
use function substr;

/**
 * Registers the custom items (items/) and blocks (blocks/) of the behavior
 * packs through Customies. Blocks are registered first so that items can
 * reference them, and an item sharing the identifier of a block of the same
 * pack is skipped since the block already has its item.
 */
final class CustomContentLoader implements ContentLoader{

	private const CATEGORIES = [
		"construction",
		"equipment",
		"items",
		"nature"
	];

	public function __construct(
		private PluginBase $plugin
	){
	}

	public function getName() : string{
		return "custom items and blocks";
	}

	public function requiresCustomies() : bool{
		return true;
	}

	public function load(array $packs) : void{
		$blockRegistrar = new CustomBlockRegistrar($this->plugin);
		$itemRegistrar = new CustomItemRegistrar($this->plugin);
		$blockCount = 0;
		$itemCount = 0;

		/** @var array<string, array<string, true>> $packBlocks */
		$packBlocks = [];
		foreach($packs as $pack){
			$packBlocks[$pack->getPath()] = [];
			foreach($pack->listFiles("blocks") as $file){
				$identifier = $blockRegistrar->readIdentifier($file);
				if($identifier !== null){
					$packBlocks[$pack->getPath()][$identifier] = true;
				}
				if($this->guard($pack, $file, "block", static fn() : bool => $blockRegistrar->register($pack, $file))){
					$blockCount++;
				}
			}
		}

		foreach($packs as $pack){
			foreach($pack->listFiles("items") as $file){
				$identifier = $itemRegistrar->readIdentifier($file);
				if($identifier !== null && isset($packBlocks[$pack->getPath()][$identifier])){
					continue;
				}
				if($this->guard($pack, $file, "item", static fn() : bool => $itemRegistrar->register($pack, $file))){
					$itemCount++;
				}
			}
		}

		if($itemCount > 0 || $blockCount > 0){
			$this->plugin->getLogger()->info("Behavior packs: $itemCount custom items, $blockCount custom blocks");
		}
	}

	public function close() : void{
	}

	/**
	 * Returns the Customies creative inventory info of a description, or null
	 * when the content is not shown in the creative inventory.
	 *
	 * @param array<mixed> $description
	 */
	public static function creativeInfo(array $description) : ?CreativeInventoryInfo{
		$menu = $description["menu_category"] ?? null;
		$category = is_array($menu) ? ($menu["category"] ?? null) : ($description["category"] ?? null);
		if(!is_string($category) || !in_array($category, self::CATEGORIES, true)){
			return null;
		}
		$group = is_array($menu) ? ($menu["group"] ?? null) : null;
		if(!is_string($group) || $group === ""){
			return new CreativeInventoryInfo($category, CreativeInventoryInfo::NONE);
		}
		if(str_starts_with($group, "minecraft:")){
			$group = substr($group, 10);
		}
		return new CreativeInventoryInfo($category, $group);
	}

	/**
	 * @param Closure() : bool $register
	 */
	private function guard(BehaviorPack $pack, string $file, string $kind, Closure $register) : bool{
		try{
			return $register();
		}catch(Throwable $e){
			$this->plugin->getLogger()->warning("Behavior packs: skipped $kind " . $pack->getName() . "/" . $pack->relativePath($file) . ": " . $e->getMessage());
			return false;
		}
	}
}
