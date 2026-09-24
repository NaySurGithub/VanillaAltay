<?php

declare(strict_types=1);

namespace behaviorpack\custom;

use behaviorpack\BehaviorPack;
use behaviorpack\BehaviorPackException;
use customiesdevs\customies\item\component\AllowOffHandComponent;
use customiesdevs\customies\item\component\BlockPlacerComponent;
use customiesdevs\customies\item\component\BundleInteractionComponent;
use customiesdevs\customies\item\component\CanDestroyInCreativeComponent;
use customiesdevs\customies\item\component\CooldownComponent;
use customiesdevs\customies\item\component\DamageAbsorptionComponent;
use customiesdevs\customies\item\component\DamageComponent;
use customiesdevs\customies\item\component\DiggerComponent;
use customiesdevs\customies\item\component\DisplayNameComponent;
use customiesdevs\customies\item\component\DurabilityComponent;
use customiesdevs\customies\item\component\DyeableComponent;
use customiesdevs\customies\item\component\EnchantableSlotComponent;
use customiesdevs\customies\item\component\EnchantableValueComponent;
use customiesdevs\customies\item\component\FoodComponent;
use customiesdevs\customies\item\component\FuelComponent;
use customiesdevs\customies\item\component\GlintComponent;
use customiesdevs\customies\item\component\HandEquippedComponent;
use customiesdevs\customies\item\component\HoverTextColorComponent;
use customiesdevs\customies\item\component\IconComponent;
use customiesdevs\customies\item\component\InteractButtonComponent;
use customiesdevs\customies\item\component\LiquidClippedComponent;
use customiesdevs\customies\item\component\MaxStackSizeComponent;
use customiesdevs\customies\item\component\ProjectileComponent;
use customiesdevs\customies\item\component\RarityComponent;
use customiesdevs\customies\item\component\RecordComponent;
use customiesdevs\customies\item\component\ShooterComponent;
use customiesdevs\customies\item\component\ShouldDespawnComponent;
use customiesdevs\customies\item\component\StackedByDataComponent;
use customiesdevs\customies\item\component\ThrowableComponent;
use customiesdevs\customies\item\component\UseAnimationComponent;
use customiesdevs\customies\item\component\UseDurationComponent;
use customiesdevs\customies\item\component\UseModifiersComponent;
use customiesdevs\customies\item\component\WearableComponent;
use customiesdevs\customies\item\CustomiesItemFactory;
use customiesdevs\customies\item\ItemComponents;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\StringToItemParser;
use pocketmine\plugin\PluginBase;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function preg_match_all;
use function round;
use function str_contains;
use function strrpos;
use function substr;

/**
 * Registers the minecraft:item files of the behavior packs through
 * Customies. Customies registers an item from a closure, so the same few
 * classes serve every identifier.
 *
 * @phpstan-import-type ItemDefinition from BehaviorItemTrait
 */
final class CustomItemRegistrar{

	/** Components handled by other loaders or by scripts, never reported. */
	private const IGNORED = [
		"minecraft:custom_components"
	];

	private const SATURATION = [
		"poor" => 0.1,
		"low" => 0.3,
		"normal" => 0.6,
		"good" => 0.8,
		"max" => 1.0,
		"supernatural" => 1.2
	];

	private const ANIMATIONS = [
		"none" => UseAnimationComponent::ANIMATION_NONE,
		"eat" => UseAnimationComponent::ANIMATION_EAT,
		"drink" => UseAnimationComponent::ANIMATION_DRINK,
		"block" => UseAnimationComponent::ANIMATION_BLOCK,
		"bow" => UseAnimationComponent::ANIMATION_BOW,
		"camera" => UseAnimationComponent::ANIMATION_CAMERA,
		"spear" => UseAnimationComponent::ANIMATION_SPEAR,
		"crossbow" => UseAnimationComponent::ANIMATION_CROSSBOW,
		"spyglass" => UseAnimationComponent::ANIMATION_SPYGLASS,
		"brush" => UseAnimationComponent::ANIMATION_BRUSH
	];

	private const ARMOR_SLOTS = [
		WearableComponent::SLOT_ARMOR_HEAD => ArmorInventory::SLOT_HEAD,
		WearableComponent::SLOT_ARMOR_CHEST => ArmorInventory::SLOT_CHEST,
		WearableComponent::SLOT_ARMOR_LEGS => ArmorInventory::SLOT_LEGS,
		WearableComponent::SLOT_ARMOR_FEET => ArmorInventory::SLOT_FEET
	];

	public function __construct(
		private PluginBase $plugin
	){
	}

	/**
	 * Reads the identifier of an item file without registering it, or null
	 * when the file is not a valid item definition.
	 */
	public function readIdentifier(string $file) : ?string{
		try{
			$identifier = BehaviorPack::readJson($file)["minecraft:item"]["description"]["identifier"] ?? null;
		}catch(BehaviorPackException){
			return null;
		}
		return is_string($identifier) ? $identifier : null;
	}

	/**
	 * Registers one item file, returns whether an item was registered.
	 */
	public function register(BehaviorPack $pack, string $file) : bool{
		$path = $pack->getName() . "/" . $pack->relativePath($file);
		try{
			return $this->registerFile($file);
		}catch(BehaviorPackException $e){
			$this->plugin->getLogger()->warning("Behavior packs: skipped item $path: " . $e->getMessage());
			return false;
		}
	}

	/**
	 * @throws BehaviorPackException
	 */
	private function registerFile(string $file) : bool{
		$json = BehaviorPack::readJson($file)["minecraft:item"] ?? null;
		if(!is_array($json)){
			throw new BehaviorPackException("no minecraft:item object");
		}
		$description = $json["description"] ?? null;
		$identifier = is_array($description) ? ($description["identifier"] ?? null) : null;
		if(!is_array($description) || !is_string($identifier) || !str_contains($identifier, ":")){
			throw new BehaviorPackException("missing or invalid description.identifier");
		}
		if(StringToItemParser::getInstance()->parse($identifier) !== null){
			throw new BehaviorPackException("$identifier is already registered");
		}
		$components = $json["components"] ?? [];
		if(!is_array($components)){
			throw new BehaviorPackException("components must be an object");
		}

		$list = [];
		$unsupported = [];
		/** @phpstan-var ItemDefinition $definition */
		$definition = [
			"identifier" => $identifier,
			"name" => $identifier,
			"maxStackSize" => 64,
			"attackPoints" => 0,
			"fuelTicks" => 0,
			"cooldownTicks" => 0,
			"cooldownTag" => null,
			"blockPlacer" => null,
			"durability" => 0,
			"nutrition" => 0,
			"saturation" => 0.0,
			"canAlwaysEat" => false,
			"residue" => null,
			"useTicks" => 0,
			"armorSlot" => null,
			"protection" => 0
		];
		$food = false;
		$texture = substr($identifier, (int) strrpos($identifier, ":") + 1);
		$dyedTexture = "";
		$trimTexture = "";

		foreach($components as $name => $value){
			$name = (string) $name;
			switch($name){
				case "minecraft:icon":
					[$texture, $dyedTexture, $trimTexture] = $this->icon($value);
					break;
				case "minecraft:display_name":
					$definition["name"] = $this->string($value, "value", $name);
					$list[] = new DisplayNameComponent($definition["name"]);
					break;
				case "minecraft:max_stack_size":
					$definition["maxStackSize"] = (int) $this->number($value, "value", $name);
					break;
				case "minecraft:durability":
					$definition["durability"] = (int) $this->number($value, "max_durability", $name);
					$chance = is_array($value) ? ($value["damage_chance"] ?? []) : [];
					$min = is_array($chance) ? ($chance["min"] ?? 100) : 100;
					$max = is_array($chance) ? ($chance["max"] ?? 100) : 100;
					$list[] = new DurabilityComponent($definition["durability"], is_int($min) ? $min : 100, is_int($max) ? $max : 100);
					break;
				case "minecraft:damage":
					$definition["attackPoints"] = (int) $this->number($value, "value", $name);
					$list[] = new DamageComponent($definition["attackPoints"]);
					break;
				case "minecraft:digger":
					$digger = $this->digger($value);
					if($digger !== null){
						$list[] = $digger;
					}
					break;
				case "minecraft:food":
					$food = true;
					$list[] = $this->food($value, $definition);
					break;
				case "minecraft:fuel":
					$duration = $this->number($value, "duration", $name);
					$definition["fuelTicks"] = (int) round($duration * 20);
					$list[] = new FuelComponent($duration);
					break;
				case "minecraft:hand_equipped":
					$list[] = new HandEquippedComponent($this->bool($value, $name));
					break;
				case "minecraft:wearable":
					$slot = $this->string($value, "slot", $name);
					$definition["protection"] = is_array($value) ? (int) $this->optionalNumber($value["protection"] ?? 0, $name) : 0;
					$definition["armorSlot"] = self::ARMOR_SLOTS[$slot] ?? null;
					$list[] = new WearableComponent($slot, $definition["protection"], !is_array($value) || ($value["dispensable"] ?? true) !== false);
					break;
				case "minecraft:cooldown":
					$category = $this->string($value, "category", $name);
					$duration = $this->number($value, "duration", $name);
					$definition["cooldownTag"] = $category;
					$definition["cooldownTicks"] = (int) round($duration * 20);
					$list[] = new CooldownComponent($category, $duration);
					break;
				case "minecraft:throwable":
					$list[] = $this->throwable($value);
					break;
				case "minecraft:projectile":
					$list[] = new ProjectileComponent(
						is_array($value) ? $this->optionalNumber($value["minimum_critical_power"] ?? 1.25, $name) : 1.25,
						$this->string($value, "projectile_entity", $name)
					);
					break;
				case "minecraft:shooter":
					$list[] = $this->shooter($value);
					break;
				case "minecraft:allow_off_hand":
					$list[] = new AllowOffHandComponent($this->bool($value, $name));
					break;
				case "minecraft:glint":
					$list[] = new GlintComponent($this->bool($value, $name));
					break;
				case "minecraft:use_animation":
					$animation = $this->string($value, "value", $name);
					if(!isset(self::ANIMATIONS[$animation])){
						throw new BehaviorPackException("unknown use animation $animation");
					}
					$list[] = new UseAnimationComponent(self::ANIMATIONS[$animation]);
					break;
				case "minecraft:use_modifiers":
					$seconds = is_array($value) ? $this->optionalNumber($value["use_duration"] ?? 0, $name) : 0.0;
					$movement = is_array($value) ? $this->optionalNumber($value["movement_modifier"] ?? 1.0, $name) : 1.0;
					$definition["useTicks"] = (int) round($seconds * 20);
					$list[] = new UseModifiersComponent($movement, $seconds);
					$list[] = new UseDurationComponent($definition["useTicks"]);
					break;
				case "minecraft:use_duration":
					$definition["useTicks"] = (int) $this->number($value, "value", $name);
					$list[] = new UseDurationComponent($definition["useTicks"]);
					break;
				case "minecraft:block_placer":
					$list[] = $this->blockPlacer($value, $definition);
					break;
				case "minecraft:rarity":
					$list[] = new RarityComponent($this->string($value, "value", $name));
					break;
				case "minecraft:hover_text_color":
					$list[] = new HoverTextColorComponent($this->string($value, "value", $name));
					break;
				case "minecraft:stacked_by_data":
					$list[] = new StackedByDataComponent($this->bool($value, $name));
					break;
				case "minecraft:should_despawn":
					$list[] = new ShouldDespawnComponent($this->bool($value, $name));
					break;
				case "minecraft:liquid_clipped":
					$list[] = new LiquidClippedComponent($this->bool($value, $name));
					break;
				case "minecraft:can_destroy_in_creative":
					$list[] = new CanDestroyInCreativeComponent($this->bool($value, $name));
					break;
				case "minecraft:enchantable":
					if(!is_array($value)){
						throw new BehaviorPackException("$name must be an object");
					}
					$list[] = new EnchantableSlotComponent(is_string($value["slot"] ?? null) ? $value["slot"] : EnchantableSlotComponent::SLOT_ALL);
					$list[] = new EnchantableValueComponent((int) $this->optionalNumber($value["value"] ?? 1, $name));
					break;
				case "minecraft:interact_button":
					$list[] = new InteractButtonComponent(is_string($value) ? $value : true);
					break;
				case "minecraft:record":
					$list[] = new RecordComponent(
						(int) $this->number($value, "comparator_signal", $name),
						$this->number($value, "duration", $name),
						$this->string($value, "sound_event", $name)
					);
					break;
				case "minecraft:dyeable":
					$list[] = new DyeableComponent($this->string($value, "default_color", $name));
					break;
				case "minecraft:damage_absorption":
					$causes = is_array($value) ? ($value["absorbable_causes"] ?? null) : null;
					if(!is_array($causes)){
						throw new BehaviorPackException("$name needs absorbable_causes");
					}
					$list[] = new DamageAbsorptionComponent($causes);
					break;
				case "minecraft:bundle_interaction":
					$list[] = new BundleInteractionComponent((int) $this->number($value, "num_viewable_slots", $name));
					break;
				default:
					if(!in_array($name, self::IGNORED, true)){
						$unsupported[] = $name;
					}
			}
		}

		if(!isset($components["minecraft:max_stack_size"]) && ($definition["durability"] > 0 || $definition["armorSlot"] !== null)){
			$definition["maxStackSize"] = 1;
		}
		if($food && !isset($components["minecraft:use_animation"])){
			$list[] = new UseAnimationComponent(UseAnimationComponent::ANIMATION_EAT);
		}
		if($food && $definition["useTicks"] === 0){
			$list[] = new UseDurationComponent(32);
		}
		$list[] = new IconComponent($texture, $dyedTexture, $trimTexture);
		$list[] = new MaxStackSizeComponent($definition["maxStackSize"]);
		if(!isset($components["minecraft:can_destroy_in_creative"])){
			$list[] = new CanDestroyInCreativeComponent();
		}

		if(count($unsupported) > 0){
			$this->plugin->getLogger()->warning("Behavior packs: item $identifier: unsupported components ignored: " . implode(", ", $unsupported));
		}

		$item = $this->createItem($definition, $food);
		foreach($list as $component){
			$item->addComponent($component);
		}
		CustomiesItemFactory::getInstance()->registerItem(
			static fn() : Item => $item,
			$identifier,
			CustomContentLoader::creativeInfo($description)
		);
		return true;
	}

	/**
	 * @phpstan-param ItemDefinition $definition
	 */
	private function createItem(array $definition, bool $food) : Item&ItemComponents{
		$identifier = new ItemIdentifier(ItemTypeIds::newId());
		if($definition["armorSlot"] !== null){
			return new BehaviorArmorItem($identifier, $definition);
		}
		if($food){
			return new BehaviorFoodItem($identifier, $definition);
		}
		if($definition["durability"] > 0){
			return new BehaviorDurableItem($identifier, $definition);
		}
		return new BehaviorItem($identifier, $definition);
	}

	/**
	 * @return array{string, string, string}
	 * @throws BehaviorPackException
	 */
	private function icon(mixed $value) : array{
		if(is_string($value)){
			return [$value, "", ""];
		}
		if(!is_array($value)){
			throw new BehaviorPackException("minecraft:icon must be a string or an object");
		}
		$textures = $value["textures"] ?? $value["texture"] ?? null;
		if(is_string($textures)){
			return [$textures, "", ""];
		}
		if(!is_array($textures) || !is_string($textures["default"] ?? null)){
			throw new BehaviorPackException("minecraft:icon needs a default texture");
		}
		return [
			$textures["default"],
			is_string($textures["dyed"] ?? null) ? $textures["dyed"] : "",
			is_string($textures["icon_trim"] ?? null) ? $textures["icon_trim"] : ""
		];
	}

	/**
	 * @phpstan-param ItemDefinition $definition
	 * @throws BehaviorPackException
	 */
	private function food(mixed $value, array &$definition) : FoodComponent{
		if(!is_array($value)){
			throw new BehaviorPackException("minecraft:food must be an object");
		}
		$nutrition = (int) $this->optionalNumber($value["nutrition"] ?? 0, "nutrition");
		$modifier = $value["saturation_modifier"] ?? 0.6;
		if(is_string($modifier)){
			$modifier = self::SATURATION[$modifier] ?? throw new BehaviorPackException("unknown saturation_modifier $modifier");
		}
		$modifier = $this->optionalNumber($modifier, "saturation_modifier");
		$canAlwaysEat = ($value["can_always_eat"] ?? false) === true;
		$residue = $value["using_converts_to"] ?? null;
		if(is_array($residue)){
			$residue = $residue["name"] ?? $residue["item"] ?? null;
		}
		$residue = is_string($residue) && $residue !== "" ? $residue : null;

		$definition["nutrition"] = $nutrition;
		$definition["saturation"] = $nutrition * $modifier * 2;
		$definition["canAlwaysEat"] = $canAlwaysEat;
		$definition["residue"] = $residue;
		return new FoodComponent($canAlwaysEat, $nutrition, $modifier, $residue ?? "");
	}

	/**
	 * @throws BehaviorPackException
	 */
	private function digger(mixed $value) : ?DiggerComponent{
		if(!is_array($value)){
			throw new BehaviorPackException("minecraft:digger must be an object");
		}
		$digger = new DiggerComponent(($value["use_efficiency"] ?? false) === true);
		$added = false;
		foreach($value["destroy_speeds"] ?? [] as $entry){
			if(!is_array($entry)){
				continue;
			}
			$speed = (int) $this->optionalNumber($entry["speed"] ?? 1, "speed");
			$target = $entry["block"] ?? null;
			if(is_array($target) && is_string($target["tags"] ?? null)){
				preg_match_all("/'([^']+)'/", $target["tags"], $matches);
				if(count($matches[1]) > 0){
					$digger->withTags($speed, ...$matches[1]);
					$added = true;
				}
				continue;
			}
			if(is_array($target)){
				$target = $target["name"] ?? null;
			}
			$block = is_string($target) ? $this->resolveBlock($target) : null;
			if($block !== null){
				$digger->withBlocks($speed, $block);
				$added = true;
			}
		}
		return $added ? $digger : null;
	}

	/**
	 * @throws BehaviorPackException
	 */
	private function throwable(mixed $value) : ThrowableComponent{
		if(!is_array($value)){
			$value = [];
		}
		return new ThrowableComponent(
			($value["do_swing_animation"] ?? false) === true,
			$this->optionalNumber($value["launch_power_scale"] ?? 1.0, "launch_power_scale"),
			$this->optionalNumber($value["max_draw_duration"] ?? 0.0, "max_draw_duration"),
			$this->optionalNumber($value["max_launch_power"] ?? 1.0, "max_launch_power"),
			$this->optionalNumber($value["min_draw_duration"] ?? 0.0, "min_draw_duration"),
			($value["scale_power_by_draw_duration"] ?? false) === true
		);
	}

	/**
	 * Customies only sends the first ammunition entry.
	 *
	 * @throws BehaviorPackException
	 */
	private function shooter(mixed $value) : ShooterComponent{
		$ammunition = is_array($value) ? ($value["ammunition"][0] ?? null) : null;
		$item = is_array($ammunition) ? ($ammunition["item"] ?? null) : null;
		if(is_array($item)){
			$item = $item["name"] ?? null;
		}
		if(!is_array($value) || !is_array($ammunition) || !is_string($item)){
			throw new BehaviorPackException("minecraft:shooter needs an ammunition item");
		}
		return new ShooterComponent(
			$item,
			($ammunition["use_offhand"] ?? false) === true,
			($ammunition["search_inventory"] ?? false) === true,
			($ammunition["use_in_creative"] ?? false) === true,
			($value["charge_on_draw"] ?? false) === true,
			$this->optionalNumber($value["max_draw_duration"] ?? 0.0, "max_draw_duration"),
			($value["scale_power_by_draw_duration"] ?? false) === true
		);
	}

	/**
	 * @phpstan-param ItemDefinition $definition
	 * @throws BehaviorPackException
	 */
	private function blockPlacer(mixed $value, array &$definition) : BlockPlacerComponent{
		$target = is_array($value) ? ($value["block"] ?? null) : $value;
		if(is_array($target)){
			$target = $target["name"] ?? null;
		}
		$block = is_string($target) ? $this->resolveBlock($target) : null;
		if(!is_string($target) || $block === null){
			throw new BehaviorPackException("minecraft:block_placer block is unknown");
		}
		$definition["blockPlacer"] = $target;
		$component = new BlockPlacerComponent($block);
		$useOn = [];
		foreach(is_array($value) && is_array($value["use_on"] ?? null) ? $value["use_on"] : [] as $entry){
			$name = is_array($entry) ? ($entry["name"] ?? null) : $entry;
			$useOnBlock = is_string($name) ? $this->resolveBlock($name) : null;
			if($useOnBlock !== null){
				$useOn[] = $useOnBlock;
			}
		}
		if(count($useOn) > 0){
			$component->useOn(...$useOn);
		}
		return $component;
	}

	private function resolveBlock(string $name) : ?Block{
		$item = StringToItemParser::getInstance()->parse($name);
		if($item === null){
			return null;
		}
		$block = $item->getBlock();
		return $block->getTypeId() === BlockTypeIds::AIR ? null : $block;
	}

	/**
	 * @throws BehaviorPackException
	 */
	private function string(mixed $value, string $key, string $component) : string{
		$result = is_array($value) ? ($value[$key] ?? null) : $value;
		if(!is_string($result)){
			throw new BehaviorPackException("$component: expected a string for $key");
		}
		return $result;
	}

	/**
	 * @throws BehaviorPackException
	 */
	private function number(mixed $value, string $key, string $component) : float{
		$result = is_array($value) ? ($value[$key] ?? null) : $value;
		if(!is_int($result) && !is_float($result)){
			throw new BehaviorPackException("$component: expected a number for $key");
		}
		return (float) $result;
	}

	/**
	 * @throws BehaviorPackException
	 */
	private function optionalNumber(mixed $value, string $what) : float{
		if(!is_int($value) && !is_float($value)){
			throw new BehaviorPackException("expected a number for $what");
		}
		return (float) $value;
	}

	/**
	 * @throws BehaviorPackException
	 */
	private function bool(mixed $value, string $component) : bool{
		$result = is_array($value) ? ($value["value"] ?? true) : $value;
		if(!is_bool($result)){
			throw new BehaviorPackException("$component: expected a boolean");
		}
		return $result;
	}
}
