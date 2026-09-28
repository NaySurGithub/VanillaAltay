<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior;

use behaviorpack\entity\BehaviorEntity;
use pocketmine\block\Block;
use pocketmine\block\Campfire;
use pocketmine\block\CaveVines;
use pocketmine\block\Fire;
use pocketmine\block\Ladder;
use pocketmine\block\Lava;
use pocketmine\block\Magma;
use pocketmine\block\SnowLayer;
use pocketmine\block\SoulCampfire;
use pocketmine\block\Vine;
use pocketmine\block\Water;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\entity\effect\StringToEffectParser;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\entity\Living;
use pocketmine\item\Bow;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\Trident;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\Position;
use pocketmine\world\World;
use function array_is_list;
use function count;
use function floor;
use function fmod;
use function in_array;
use function intdiv;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function method_exists;
use function mt_rand;
use function sqrt;
use function str_contains;
use function str_starts_with;
use function strtolower;
use function substr;

/**
 * Evaluates the Bedrock filters of the entity definitions. The context holds
 * the entities a filter can name as its subject ("other", "target",
 * "player", "damager", "parent" and "baby"), the "damage_cause" of a damage
 * sensor and the "block" of block related triggers.
 */
final class FilterEvaluator{

	private const COLORS = [
		"white" => 0, "orange" => 1, "magenta" => 2, "light_blue" => 3, "yellow" => 4, "lime" => 5, "light_green" => 5,
		"pink" => 6, "gray" => 7, "silver" => 8, "light_gray" => 8, "cyan" => 9, "purple" => 10, "blue" => 11,
		"brown" => 12, "green" => 13, "red" => 14, "black" => 15
	];

	private const MOON_INTENSITY = [1.0, 0.75, 0.5, 0.25, 0.0, 0.25, 0.5, 0.75];

	private const DIFFICULTIES = ["peaceful", "easy", "normal", "hard"];

	/**
	 * @param array<mixed>          $filter
	 * @param array<string, mixed> $context
	 */
	public static function test(array $filter, BehaviorEntity $self, array $context = [], bool $unknownResult = false) : bool{
		if(array_is_list($filter)){
			foreach($filter as $entry){
				if(is_array($entry) && !self::test($entry, $self, $context, $unknownResult)){
					return false;
				}
			}
			return true;
		}
		foreach(["all_of" => true, "any_of" => false, "none_of" => null, "AND" => true, "OR" => false] as $group => $mode){
			if(!is_array($filter[$group] ?? null)){
				continue;
			}
			foreach($filter[$group] as $entry){
				if(!is_array($entry)){
					continue;
				}
				$result = self::test($entry, $self, $context, $unknownResult);
				if($mode === true && !$result){
					return false;
				}
				if($mode === false && $result){
					return true;
				}
				if($mode === null && $result){
					return false;
				}
			}
			return $mode !== false;
		}
		$test = $filter["test"] ?? null;
		if(!is_string($test)){
			return $unknownResult;
		}
		$subjectName = $filter["subject"] ?? "self";
		$block = $context["block"] ?? null;
		if($subjectName === "block"){
			if(!$block instanceof Block){
				return false;
			}
			$entity = null;
			$position = $block->getPosition();
		}else{
			$entity = self::subject($subjectName, $self, $context);
			if($entity === null){
				return false;
			}
			$position = $entity->getPosition();
		}
		$result = self::evaluate($test, $filter, $entity, $position, $self, $context);
		return $result ?? $unknownResult;
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public static function subject(mixed $name, BehaviorEntity $self, array $context) : ?Entity{
		if($name === "self" || !is_string($name)){
			return $self;
		}
		$entity = $context[$name] ?? null;
		if($entity instanceof Entity){
			return $entity;
		}
		if($name === "target"){
			return $self->getTargetEntity();
		}
		return null;
	}

	public static function compare(mixed $actual, mixed $expected, mixed $operator) : bool{
		$negate = $operator === "!=" || $operator === "not" || $operator === "<>";
		if(is_bool($actual) && !is_bool($expected) && is_numeric($expected)){
			$expected = (float) $expected != 0;
		}
		if(is_bool($expected) && !is_bool($actual) && is_numeric($actual)){
			$actual = (float) $actual != 0;
		}
		if((is_int($actual) || is_float($actual)) && is_numeric($expected)){
			$expected = (float) $expected;
			return match($operator){
				"!=", "not", "<>" => $actual != $expected,
				"<" => $actual < $expected,
				"<=" => $actual <= $expected,
				">" => $actual > $expected,
				">=" => $actual >= $expected,
				default => $actual == $expected
			};
		}
		if(is_string($actual) && is_string($expected)){
			$equal = self::stripNamespace(strtolower($actual)) === self::stripNamespace(strtolower($expected));
		}else{
			$equal = $actual === $expected;
		}
		return $negate ? !$equal : $equal;
	}

	/**
	 * @param array<mixed>          $filter
	 * @param array<string, mixed> $context
	 */
	private static function evaluate(string $test, array $filter, ?Entity $entity, Position $position, BehaviorEntity $self, array $context) : ?bool{
		$world = $position->getWorld();
		$value = $filter["value"] ?? null;
		$domain = $filter["domain"] ?? null;
		$living = $entity instanceof Living ? $entity : null;
		$behavior = $entity instanceof BehaviorEntity ? $entity : null;
		return match($test){
			"actor_health" => $entity === null ? false : self::cmp($entity->getHealth(), $filter, 1),
			"all_slots_empty" => $entity === null ? false : self::flag(self::slotsEmpty($entity, $domain, true), $filter),
			"any_slot_empty" => $entity === null ? false : self::flag(self::slotsEmpty($entity, $domain, false), $filter),
			"bool_property", "int_property", "float_property", "enum_property" => self::propertyTest($test, $filter, $entity),
			"has_property" => self::flag($behavior !== null && is_string($value) && $behavior->getPropertyValue($value) !== null, $filter),
			"clock_time" => self::cmp(fmod(($world->getTimeOfDay() - 6000 + 24000) % 24000, 24000) / 24000, $filter, 0.0),
			"hourly_clock_time" => self::cmp($world->getTimeOfDay() % 24000, $filter, 0),
			"distance_to_nearest_player" => self::cmp(self::nearestPlayerDistance($position), $filter, 0.0),
			"has_ability" => $entity === null ? false : self::flag(self::hasAbility($entity, $value), ["operator" => $filter["operator"] ?? "=="]),
			"has_biome_tag" => self::flag(is_string($value) && in_array(self::stripNamespace(strtolower($value)), self::biomeTags($world, $position), true), ["operator" => $filter["operator"] ?? "=="]),
			"is_biome" => self::flag(is_string($value) && in_array(self::stripNamespace(strtolower($value)), self::biomeTags($world, $position), true), ["operator" => $filter["operator"] ?? "=="]),
			"has_component" => self::flag(is_string($value) && $behavior !== null && $behavior->hasComponent($value), ["operator" => $filter["operator"] ?? "=="]),
			"has_container_open" => self::flag($entity instanceof Player && $entity->getCurrentWindow() !== null, $filter),
			"has_damage" => self::hasDamage($value, $filter, $context),
			"has_equipment" => $entity === null ? false : self::flag(self::hasEquipment($entity, $domain, $value), ["operator" => $filter["operator"] ?? "=="]),
			"has_mob_effect" => self::flag($living !== null && self::hasEffect($living, $value), ["operator" => $filter["operator"] ?? "=="]),
			"has_nametag" => self::flag($entity !== null && $entity->getNameTag() !== "", $filter),
			"has_ranged_weapon" => self::flag($entity !== null && self::hasRangedWeapon($entity), $filter),
			"has_silk_touch" => self::flag($entity !== null && self::handItem($entity)?->hasEnchantment(VanillaEnchantments::SILK_TOUCH()) === true, $filter),
			"has_tag" => self::flag(is_string($value) && in_array($value, self::tagsOf($entity), true), ["operator" => $filter["operator"] ?? "=="]),
			"has_target" => self::flag(self::targetOf($entity) !== null, $filter),
			"has_trade_supply" => self::flag($behavior !== null && ($behavior->hasComponent("minecraft:trade_table") || $behavior->hasComponent("minecraft:economy_trade_table")) && $behavior->getData("trade_supply", true) !== false, $filter),
			"in_block" => self::cmp(self::blockName($world->getBlock($position)), $filter, ""),
			"is_block" => self::cmp(self::blockName($context["block"] ?? $world->getBlock($position)), $filter, ""),
			"in_caravan" => self::flag($behavior !== null && ($behavior->getData("caravan_head") !== null || $behavior->getData("caravan_tail") !== null), $filter),
			"in_clouds" => self::flag(self::dimension($world) === "overworld" && $position->y >= 192 && $position->y < 196, $filter),
			"in_contact_with_water" => self::flag(self::touchesWater($entity, $position), $filter),
			"in_lava" => self::flag($world->getBlock($position) instanceof Lava || $world->getBlock($position->add(0, 0.3, 0)) instanceof Lava, $filter),
			"in_water" => self::flag(self::inWater($entity, $position), $filter),
			"in_water_or_rain" => self::flag(self::inWater($entity, $position) || self::exposedToRain($world, $position), $filter),
			"in_nether" => self::flag(self::dimension($world) === "nether", $filter),
			"in_overworld" => self::flag(self::dimension($world) === "overworld", $filter),
			"in_the_end" => self::flag(self::dimension($world) === "the_end", $filter),
			"is_altitude" => self::cmp((int) floor($position->y), $filter, 0),
			"is_avoiding_mobs" => self::flag($behavior !== null && $behavior->getData("avoiding_mobs", false) === true, $filter),
			"is_baby" => self::flag($behavior !== null && $behavior->hasComponent("minecraft:is_baby"), $filter),
			"is_bound_to_creaking_heart" => self::flag(false, $filter),
			"is_brightness" => self::cmp($world->getFullLight($position) / 15, $filter, 0.0),
			"light_level" => self::cmp($world->getFullLight($position), $filter, 0),
			"is_climbing", "on_ladder" => self::flag(self::onClimbable($world, $position), $filter),
			"is_color" => self::isColor($behavior, $filter),
			"is_daytime" => self::flag(self::isDay($world), $filter),
			"is_difficulty" => self::cmp(self::DIFFICULTIES[$world->getDifficulty()] ?? "normal", $filter, "normal"),
			"is_family" => self::flag(is_string($value) && $entity !== null && in_array(strtolower($value), BehaviorEntity::familiesOf($entity), true), ["operator" => $filter["operator"] ?? "=="]),
			"is_humid" => self::flag($world->getBiome((int) floor($position->x), (int) floor($position->y), (int) floor($position->z))->getRainfall() > 0.85, $filter),
			"is_immobile" => self::flag($entity !== null && ($entity->hasNoClientPredictions() || !$entity->isAlive()), $filter),
			"is_in_village" => self::flag($behavior !== null && $behavior->getData("village") !== null, $filter),
			"is_leashed" => self::flag($behavior !== null && $behavior->getData("leash_holder") !== null, $filter),
			"is_leashed_to" => self::isLeashedTo($entity, $self, $filter),
			"is_mark_variant" => $behavior === null ? false : self::cmp((int) $behavior->getData("mark_variant", 0), $filter, 0),
			"is_variant" => $behavior === null ? false : self::cmp((int) $behavior->getData("variant", 0), $filter, 0),
			"is_skin_id" => $behavior === null ? false : self::cmp((int) $behavior->getData("skin_id", 0), $filter, 0),
			"is_missing_health" => self::flag($entity !== null && $entity->getHealth() < $entity->getMaxHealth(), $filter),
			"is_moving" => self::flag($entity !== null && $entity->getMotion()->lengthSquared() > 0.0001, $filter),
			"is_navigating" => self::flag($behavior !== null && !$behavior->getNavigator()->isDone(), $filter),
			"is_owner" => self::flag($entity !== null && $self->isOwnedBy($entity), $filter),
			"is_persistent" => self::flag($behavior !== null && ($behavior->hasComponent("minecraft:persistent") || $behavior->getNameTag() !== ""), $filter),
			"is_raider" => self::flag($entity !== null && in_array("raider", BehaviorEntity::familiesOf($entity), true), $filter),
			"is_riding" => self::flag($entity !== null && $entity->isRiding(), $filter),
			"rider_count" => $entity === null ? false : self::cmp(count($entity->getPassengers()), $filter, 0),
			"is_sitting" => self::flag($behavior !== null && $behavior->getData("sitting", false) === true, $filter),
			"is_sleeping" => self::flag(($entity instanceof Player && $entity->isSleeping()) || ($behavior !== null && $behavior->getData("sleeping", false) === true), $filter),
			"is_sneak_held", "is_sneaking" => self::flag($living !== null && $living->isSneaking(), $filter),
			"is_sprinting" => self::flag($living !== null && $living->isSprinting(), $filter),
			"is_snow_covered" => self::flag(self::snowCovered($world, $position), $filter),
			"is_target" => self::flag($entity !== null && $self->getTargetEntity() === $entity, $filter),
			"is_temperature_type" => self::cmp(self::temperatureType($world, $position), $filter, "mild"),
			"is_temperature_value" => self::cmp(self::temperature($world, $position), $filter, 0.0),
			"is_underground" => self::flag(self::isUnderground($world, $position), $filter),
			"surface_mob" => self::flag(!self::isUnderground($world, $position), $filter),
			"is_underwater" => self::flag($entity !== null && $entity->isUnderwater(), $filter),
			"is_visible" => self::flag($entity !== null && !$entity->isInvisible(), $filter),
			"is_waterlogged" => self::flag(false, $filter),
			"is_weather", "weather" => self::cmp(self::weather($world), $filter, "clear"),
			"weather_at_position" => self::cmp(self::weatherAt($world, $position), $filter, "clear"),
			"moon_intensity" => self::cmp(self::MOON_INTENSITY[self::moonPhase($world)], $filter, 0.0),
			"moon_phase" => self::cmp(self::moonPhase($world), $filter, 0),
			"on_fire" => self::flag($entity !== null && $entity->isOnFire(), $filter),
			"on_ground" => self::flag($entity !== null && $entity->isOnGround(), $filter),
			"on_hot_block" => self::flag(self::onHotBlock($world, $position), $filter),
			"owner_distance" => self::ownerDistance($entity, $filter),
			"target_distance" => self::targetDistance($entity, $filter),
			"random_chance" => self::randomChance($value),
			"taking_fire_damage" => self::flag(self::takingFireDamage($entity, $context), $filter),
			"trusts" => self::flag($entity !== null && self::trusts($self, $entity), $filter),
			"y_rotation" => $entity === null ? false : self::cmp(self::yRotation($entity), $filter, 0.0),
			"inactivity_timer", "is_game_rule" => null,
			default => null
		};
	}

	/**
	 * @param array<mixed> $filter
	 */
	private static function cmp(mixed $actual, array $filter, mixed $default) : bool{
		return self::compare($actual, $filter["value"] ?? $default, $filter["operator"] ?? "==");
	}

	/**
	 * @param array<mixed> $filter
	 */
	private static function flag(bool $actual, array $filter) : bool{
		$expected = $filter["value"] ?? true;
		return self::compare($actual, is_bool($expected) ? $expected : true, $filter["operator"] ?? "==");
	}

	private static function stripNamespace(string $name) : string{
		return str_starts_with($name, "minecraft:") ? substr($name, 10) : $name;
	}

	/**
	 * @param array<mixed> $filter
	 */
	private static function propertyTest(string $test, array $filter, ?Entity $entity) : bool{
		$name = $filter["domain"] ?? null;
		if(!$entity instanceof BehaviorEntity || !is_string($name)){
			return false;
		}
		$actual = $entity->getPropertyValue($name);
		if($actual === null){
			return false;
		}
		return match($test){
			"bool_property" => is_bool($actual) && self::flag($actual, $filter),
			"enum_property" => is_string($actual) && self::cmp($actual, $filter, ""),
			default => (is_int($actual) || is_float($actual)) && self::cmp($actual, $filter, 0)
		};
	}

	private static function slotsEmpty(Entity $entity, mixed $domain, bool $all) : bool{
		$items = self::domainItems($entity, is_string($domain) ? $domain : "any", true);
		if(count($items) === 0){
			return $all;
		}
		foreach($items as $item){
			if($all && !$item->isNull()){
				return false;
			}
			if(!$all && $item->isNull()){
				return true;
			}
		}
		return $all;
	}

	/**
	 * @return list<Item>
	 */
	private static function domainItems(Entity $entity, string $domain, bool $inventory = false) : array{
		$domain = strtolower($domain);
		$hand = [];
		$armor = [];
		$system = $entity instanceof BehaviorEntity ? $entity->getSystem("minecraft:equipment") : null;
		if($entity instanceof Human){
			$hand[] = $entity->getInventory()->getItemInHand();
			$hand[] = $entity->getOffHandInventory()->getItem(0);
		}elseif($system !== null && method_exists($system, "getMainHand")){
			$main = $system->getMainHand();
			if($main instanceof Item){
				$hand[] = $main;
			}
		}
		$armorSlots = [];
		if($entity instanceof Living){
			$inventory2 = $entity->getArmorInventory();
			$armorSlots = [
				"head" => $inventory2->getHelmet(),
				"torso" => $inventory2->getChestplate(),
				"leg" => $inventory2->getLeggings(),
				"feet" => $inventory2->getBoots()
			];
		}
		if($system !== null && method_exists($system, "getArmor")){
			$index = 0;
			foreach(["head", "torso", "leg", "feet"] as $slot){
				$item = $system->getArmor($index);
				if($item instanceof Item && !$item->isNull()){
					$armorSlots[$slot] = $item;
				}
				$index++;
			}
		}
		foreach($armorSlots as $item){
			$armor[] = $item;
		}
		$result = match($domain){
			"hand" => $hand,
			"head", "torso", "leg", "feet" => isset($armorSlots[$domain]) ? [$armorSlots[$domain]] : [],
			"armor" => $armor,
			"inventory" => [],
			default => [...$hand, ...$armor]
		};
		if($inventory && ($domain === "inventory" || $domain === "any") && $entity instanceof Human){
			foreach($entity->getInventory()->getContents(true) as $item){
				$result[] = $item;
			}
		}
		return $result;
	}

	private static function handItem(Entity $entity) : ?Item{
		return self::domainItems($entity, "hand")[0] ?? null;
	}

	private static function hasEquipment(Entity $entity, mixed $domain, mixed $value) : bool{
		if(!is_string($value)){
			return false;
		}
		$parsed = StringToItemParser::getInstance()->parse(self::stripNamespace(strtolower($value)));
		if($parsed === null){
			return false;
		}
		foreach(self::domainItems($entity, is_string($domain) ? $domain : "any", true) as $item){
			if(!$item->isNull() && $item->getTypeId() === $parsed->getTypeId()){
				return true;
			}
		}
		return false;
	}

	private static function hasRangedWeapon(Entity $entity) : bool{
		$item = self::handItem($entity);
		return $item instanceof Bow || $item instanceof Trident || ($item !== null && str_contains(strtolower($item->getVanillaName()), "crossbow"));
	}

	private static function hasEffect(Living $living, mixed $value) : bool{
		if(!is_string($value) || $value === ""){
			return count($living->getEffects()->all()) > 0;
		}
		$effect = StringToEffectParser::getInstance()->parse(self::stripNamespace(strtolower($value)));
		return $effect !== null && $living->getEffects()->has($effect);
	}

	private static function hasAbility(Entity $entity, mixed $value) : bool{
		if(!$entity instanceof Player || !is_string($value)){
			return false;
		}
		return match(strtolower($value)){
			"flying" => $entity->isFlying(),
			"mayfly" => $entity->getAllowFlight(),
			"instabuild" => $entity->isCreative(),
			"invulnerable" => $entity->isCreative() || $entity->isSpectator(),
			"noclip" => $entity->isSpectator(),
			"build", "mine", "attackmobs", "attackplayers", "doorsandswitches", "opencontainers" => !$entity->isSpectator(),
			"operator_commands", "teleport" => $entity->hasPermission("pocketmine.group.operator"),
			default => false
		};
	}

	/**
	 * @param array<mixed>          $filter
	 * @param array<string, mixed> $context
	 */
	private static function hasDamage(mixed $value, array $filter, array $context) : bool{
		$cause = $context["damage_cause"] ?? null;
		if(!is_string($cause)){
			return self::flag(false, ["operator" => $filter["operator"] ?? "=="]);
		}
		if(!is_string($value) || $value === "any"){
			$match = true;
		}elseif($value === "fatal"){
			$match = ($context["fatal"] ?? false) === true;
		}else{
			$match = strtolower($value) === strtolower($cause);
		}
		return self::flag($match, ["operator" => $filter["operator"] ?? "=="]);
	}

	/**
	 * @return list<string>
	 */
	private static function tagsOf(?Entity $entity) : array{
		if(!$entity instanceof BehaviorEntity){
			return [];
		}
		$tags = $entity->getData("tags", []);
		$result = [];
		if(is_array($tags)){
			foreach($tags as $tag){
				if(is_string($tag)){
					$result[] = $tag;
				}
			}
		}
		return $result;
	}

	private static function targetOf(?Entity $entity) : ?Entity{
		if($entity === null){
			return null;
		}
		return $entity->getTargetEntity();
	}

	private static function blockName(mixed $block) : string{
		if(!$block instanceof Block){
			return "";
		}
		try{
			return GlobalBlockStateHandlers::getSerializer()->serialize($block->getStateId())->getName();
		}catch(\Throwable){
			return "";
		}
	}

	private static function dimension(World $world) : string{
		$generator = strtolower($world->getProvider()->getWorldData()->getGenerator());
		$folder = strtolower($world->getFolderName());
		if(str_contains($generator, "nether") || str_contains($generator, "hell") || str_contains($folder, "nether")){
			return "nether";
		}
		if(str_contains($generator, "end") || str_contains($folder, "the_end") || str_contains($folder, "end")){
			return "the_end";
		}
		return "overworld";
	}

	private static function biomeId(World $world, Vector3 $position) : int{
		return $world->getBiomeId((int) floor($position->x), (int) floor($position->y), (int) floor($position->z));
	}

	/**
	 * @return list<string>
	 */
	private static function biomeTags(World $world, Vector3 $position) : array{
		$id = self::biomeId($world, $position);
		$dimension = self::dimension($world);
		if($dimension === "nether"){
			$tags = ["nether"];
			$tags[] = match($id){
				BiomeIds::SOULSAND_VALLEY => "soulsand_valley",
				BiomeIds::CRIMSON_FOREST => "crimson_forest",
				BiomeIds::WARPED_FOREST => "warped_forest",
				BiomeIds::BASALT_DELTAS => "basalt_deltas",
				default => "nether_wastes"
			};
			if($id === BiomeIds::CRIMSON_FOREST || $id === BiomeIds::WARPED_FOREST){
				$tags[] = "forest";
			}
			return $tags;
		}
		if($dimension === "the_end" || $id === BiomeIds::THE_END){
			return ["the_end"];
		}
		$tags = ["overworld"];
		$add = match($id){
			BiomeIds::OCEAN => ["ocean"],
			BiomeIds::DEEP_OCEAN => ["ocean", "deep", "monster"],
			BiomeIds::WARM_OCEAN => ["ocean", "warm"],
			BiomeIds::DEEP_WARM_OCEAN => ["ocean", "warm", "deep"],
			BiomeIds::LUKEWARM_OCEAN => ["ocean", "lukewarm"],
			BiomeIds::DEEP_LUKEWARM_OCEAN => ["ocean", "lukewarm", "deep"],
			BiomeIds::COLD_OCEAN => ["ocean", "cold"],
			BiomeIds::DEEP_COLD_OCEAN => ["ocean", "cold", "deep"],
			BiomeIds::FROZEN_OCEAN, BiomeIds::LEGACY_FROZEN_OCEAN => ["ocean", "frozen", "cold"],
			BiomeIds::DEEP_FROZEN_OCEAN => ["ocean", "frozen", "cold", "deep"],
			BiomeIds::PLAINS => ["plains", "monster", "animal"],
			BiomeIds::SUNFLOWER_PLAINS => ["plains", "mutated", "monster", "animal"],
			BiomeIds::DESERT => ["desert", "monster"],
			BiomeIds::DESERT_HILLS => ["desert", "hills", "monster"],
			BiomeIds::DESERT_MUTATED => ["desert", "mutated", "monster"],
			BiomeIds::EXTREME_HILLS, BiomeIds::EXTREME_HILLS_EDGE => ["extreme_hills", "mountains", "mountain", "monster"],
			BiomeIds::EXTREME_HILLS_PLUS_TREES => ["extreme_hills", "mountains", "mountain", "forest", "monster"],
			BiomeIds::EXTREME_HILLS_MUTATED, BiomeIds::EXTREME_HILLS_PLUS_TREES_MUTATED => ["extreme_hills", "mountains", "mountain", "mutated", "monster"],
			BiomeIds::STONE_BEACH => ["beach", "stone", "monster"],
			BiomeIds::FOREST => ["forest", "monster", "animal"],
			BiomeIds::FOREST_HILLS => ["forest", "hills", "monster", "animal"],
			BiomeIds::FLOWER_FOREST => ["forest", "flower_forest", "mutated", "monster"],
			BiomeIds::BIRCH_FOREST => ["forest", "birch", "monster", "animal"],
			BiomeIds::BIRCH_FOREST_HILLS => ["forest", "birch", "hills", "monster", "animal"],
			BiomeIds::BIRCH_FOREST_MUTATED, BiomeIds::BIRCH_FOREST_HILLS_MUTATED => ["forest", "birch", "mutated", "monster"],
			BiomeIds::ROOFED_FOREST, BiomeIds::ROOFED_FOREST_MUTATED => ["forest", "roofed", "monster"],
			BiomeIds::TAIGA => ["taiga", "forest", "monster", "animal"],
			BiomeIds::TAIGA_HILLS => ["taiga", "forest", "hills", "monster", "animal"],
			BiomeIds::TAIGA_MUTATED => ["taiga", "forest", "mutated", "monster"],
			BiomeIds::MEGA_TAIGA, BiomeIds::MEGA_TAIGA_HILLS, BiomeIds::REDWOOD_TAIGA_MUTATED, BiomeIds::REDWOOD_TAIGA_HILLS_MUTATED => ["taiga", "mega", "forest", "monster", "animal"],
			BiomeIds::COLD_TAIGA, BiomeIds::COLD_TAIGA_HILLS, BiomeIds::COLD_TAIGA_MUTATED => ["taiga", "cold", "forest", "frozen", "monster", "animal"],
			BiomeIds::SWAMPLAND, BiomeIds::SWAMPLAND_MUTATED => ["swamp", "monster", "animal"],
			BiomeIds::MANGROVE_SWAMP => ["swamp", "mangrove_swamp", "monster", "animal"],
			BiomeIds::RIVER => ["river", "monster"],
			BiomeIds::FROZEN_RIVER => ["river", "frozen", "cold", "monster"],
			BiomeIds::ICE_PLAINS => ["ice_plains", "frozen", "cold", "monster"],
			BiomeIds::ICE_PLAINS_SPIKES => ["ice_plains", "frozen", "cold", "mutated", "monster"],
			BiomeIds::ICE_MOUNTAINS => ["ice", "mountain", "mountains", "frozen", "cold", "monster"],
			BiomeIds::MUSHROOM_ISLAND => ["mooshroom_island"],
			BiomeIds::MUSHROOM_ISLAND_SHORE => ["mooshroom_island", "shore"],
			BiomeIds::BEACH => ["beach", "warm", "monster"],
			BiomeIds::COLD_BEACH => ["beach", "cold", "frozen", "monster"],
			BiomeIds::JUNGLE, BiomeIds::JUNGLE_MUTATED => ["jungle", "monster", "animal"],
			BiomeIds::JUNGLE_HILLS => ["jungle", "hills", "monster", "animal"],
			BiomeIds::JUNGLE_EDGE, BiomeIds::JUNGLE_EDGE_MUTATED => ["jungle", "edge", "monster", "animal"],
			BiomeIds::BAMBOO_JUNGLE, BiomeIds::BAMBOO_JUNGLE_HILLS => ["jungle", "bamboo", "monster", "animal"],
			BiomeIds::SAVANNA, BiomeIds::SAVANNA_MUTATED => ["savanna", "monster", "animal"],
			BiomeIds::SAVANNA_PLATEAU, BiomeIds::SAVANNA_PLATEAU_MUTATED => ["savanna", "plateau", "monster", "animal"],
			BiomeIds::MESA, BiomeIds::MESA_BRYCE => ["mesa", "monster"],
			BiomeIds::MESA_PLATEAU, BiomeIds::MESA_PLATEAU_STONE, BiomeIds::MESA_PLATEAU_MUTATED, BiomeIds::MESA_PLATEAU_STONE_MUTATED => ["mesa", "plateau", "monster"],
			BiomeIds::JAGGED_PEAKS => ["mountains", "mountain", "peaks", "jagged_peaks", "frozen", "cold", "monster"],
			BiomeIds::FROZEN_PEAKS => ["mountains", "mountain", "peaks", "frozen_peaks", "frozen", "cold", "monster"],
			BiomeIds::STONY_PEAKS => ["mountains", "mountain", "peaks", "stony_peaks", "monster"],
			BiomeIds::SNOWY_SLOPES => ["mountains", "mountain", "snowy_slopes", "frozen", "cold", "monster"],
			BiomeIds::GROVE => ["mountains", "mountain", "grove", "forest", "frozen", "cold", "monster"],
			BiomeIds::MEADOW => ["mountains", "mountain", "meadow", "monster"],
			BiomeIds::LUSH_CAVES => ["caves", "lush_caves", "monster"],
			BiomeIds::DRIPSTONE_CAVES => ["caves", "dripstone_caves", "monster"],
			BiomeIds::DEEP_DARK => ["caves", "deep_dark"],
			BiomeIds::CHERRY_GROVE => ["mountains", "mountain", "cherry_grove", "monster", "animal"],
			BiomeIds::PALE_GARDEN => ["forest", "pale_garden", "monster"],
			default => []
		};
		foreach($add as $tag){
			$tags[] = $tag;
		}
		return $tags;
	}

	private static function temperature(World $world, Vector3 $position) : float{
		return $world->getBiome((int) floor($position->x), (int) floor($position->y), (int) floor($position->z))->getTemperature();
	}

	private static function temperatureType(World $world, Vector3 $position) : string{
		if(in_array("ocean", self::biomeTags($world, $position), true)){
			return "ocean";
		}
		$temperature = self::temperature($world, $position);
		if($temperature < 0.15){
			return "cold";
		}
		return $temperature < 1.0 ? "mild" : "warm";
	}

	private static function nearestPlayerDistance(Position $position) : float{
		$nearest = null;
		foreach($position->getWorld()->getPlayers() as $player){
			$distance = $player->getPosition()->distanceSquared($position);
			if($nearest === null || $distance < $nearest){
				$nearest = $distance;
			}
		}
		return $nearest === null ? 1.0e9 : sqrt($nearest);
	}

	private static function inWater(?Entity $entity, Position $position) : bool{
		if($entity instanceof BehaviorEntity){
			return $entity->isInWater();
		}
		if($entity !== null && $entity->isUnderwater()){
			return true;
		}
		return $position->getWorld()->getBlock($position) instanceof Water;
	}

	private static function touchesWater(?Entity $entity, Position $position) : bool{
		$world = $position->getWorld();
		if($world->getBlock($position) instanceof Water){
			return true;
		}
		$height = $entity === null ? 1.0 : $entity->getSize()->getHeight();
		return $world->getBlock($position->add(0, $height * 0.9, 0)) instanceof Water;
	}

	private static function rainLevel(World $world) : float{
		return $world->getProvider()->getWorldData()->getRainLevel();
	}

	private static function weather(World $world) : string{
		if(self::rainLevel($world) <= 0){
			return "clear";
		}
		return $world->getProvider()->getWorldData()->getLightningLevel() > 0 ? "thunderstorm" : "rain";
	}

	private static function weatherAt(World $world, Position $position) : string{
		$weather = self::weather($world);
		if($weather === "clear" || self::dimension($world) !== "overworld"){
			return "clear";
		}
		$temperature = self::temperature($world, $position);
		if($temperature < 0.15){
			return "snow";
		}
		if(in_array("desert", self::biomeTags($world, $position), true) || in_array("mesa", self::biomeTags($world, $position), true) || in_array("savanna", self::biomeTags($world, $position), true)){
			return "clear";
		}
		return $weather;
	}

	private static function exposedToRain(World $world, Position $position) : bool{
		if(self::weatherAt($world, $position) === "clear" || self::weatherAt($world, $position) === "snow"){
			return false;
		}
		$highest = $world->getHighestBlockAt((int) floor($position->x), (int) floor($position->z));
		return $highest === null || $highest < (int) floor($position->y);
	}

	private static function isUnderground(World $world, Position $position) : bool{
		$highest = $world->getHighestBlockAt((int) floor($position->x), (int) floor($position->z));
		return $highest !== null && $highest > (int) floor($position->y) + 1 && $world->getRealBlockSkyLightAt((int) floor($position->x), (int) floor($position->y), (int) floor($position->z)) === 0;
	}

	private static function onClimbable(World $world, Position $position) : bool{
		$block = $world->getBlock($position);
		return $block instanceof Ladder || $block instanceof Vine || $block instanceof CaveVines || str_contains(strtolower($block->getName()), "scaffolding") || str_contains(strtolower($block->getName()), "twisting") || str_contains(strtolower($block->getName()), "weeping");
	}

	private static function onHotBlock(World $world, Position $position) : bool{
		$below = $world->getBlock($position->subtract(0, 0.2, 0));
		$inside = $world->getBlock($position);
		return $below instanceof Magma || $below instanceof Campfire || $below instanceof SoulCampfire || $inside instanceof Fire || $inside instanceof Lava;
	}

	private static function snowCovered(World $world, Position $position) : bool{
		if($world->getBlock($position) instanceof SnowLayer){
			return true;
		}
		$x = (int) floor($position->x);
		$z = (int) floor($position->z);
		$highest = $world->getHighestBlockAt($x, $z);
		return $highest !== null && $world->getBlockAt($x, $highest, $z) instanceof SnowLayer;
	}

	private static function isDay(World $world) : bool{
		$time = $world->getTimeOfDay() % 24000;
		return $time < 13000 || $time >= 23000;
	}

	private static function moonPhase(World $world) : int{
		return intdiv($world->getTime(), 24000) % 8;
	}

	/**
	 * @param array<mixed> $filter
	 */
	private static function isColor(?BehaviorEntity $entity, array $filter) : bool{
		if($entity === null){
			return false;
		}
		$value = $filter["value"] ?? null;
		$expected = is_string($value) ? (self::COLORS[strtolower($value)] ?? -1) : (is_int($value) ? $value : -1);
		return self::compare((int) $entity->getData("color", 0), $expected, $filter["operator"] ?? "==");
	}

	/**
	 * @param array<mixed> $filter
	 */
	private static function isLeashedTo(?Entity $entity, BehaviorEntity $self, array $filter) : bool{
		$holder = $self->getData("leash_holder");
		$leashed = $entity !== null && match(true){
			is_int($holder) => $holder === $entity->getId(),
			is_string($holder) => $entity instanceof Player && $entity->getName() === $holder,
			is_array($holder) => ($holder["id"] ?? null) === $entity->getId(),
			default => false
		};
		return self::flag($leashed, $filter);
	}

	/**
	 * @param array<mixed> $filter
	 */
	private static function ownerDistance(?Entity $entity, array $filter) : bool{
		if(!$entity instanceof BehaviorEntity){
			return false;
		}
		$owner = $entity->getOwner();
		if($owner === null || $owner->getWorld() !== $entity->getWorld()){
			return false;
		}
		return self::cmp($owner->getPosition()->distance($entity->getPosition()), $filter, 0.0);
	}

	/**
	 * @param array<mixed> $filter
	 */
	private static function targetDistance(?Entity $entity, array $filter) : bool{
		$target = self::targetOf($entity);
		if($entity === null || $target === null || $target->getWorld() !== $entity->getWorld()){
			return false;
		}
		return self::cmp($target->getPosition()->distance($entity->getPosition()), $filter, 0.0);
	}

	private static function randomChance(mixed $value) : bool{
		$max = is_numeric($value) ? (int) $value : 2;
		if($max <= 1){
			return true;
		}
		return mt_rand(0, $max - 1) === 0;
	}

	/**
	 * @param array<string, mixed> $context
	 */
	private static function takingFireDamage(?Entity $entity, array $context) : bool{
		$cause = $context["damage_cause"] ?? null;
		if(is_string($cause) && in_array(strtolower($cause), ["fire", "fire_tick", "lava", "magma", "campfire", "soul_campfire"], true)){
			return true;
		}
		return $entity !== null && $entity->isOnFire() && !$entity->isFireProof();
	}

	private static function trusts(BehaviorEntity $self, Entity $entity) : bool{
		if($self->isOwnedBy($entity)){
			return true;
		}
		$trusted = $self->getData("trusted", []);
		if(!is_array($trusted)){
			return false;
		}
		$key = $entity instanceof Player ? $entity->getName() : $entity->getId();
		return in_array($key, $trusted, true);
	}

	private static function yRotation(Entity $entity) : float{
		$yaw = fmod($entity->getLocation()->yaw, 360.0);
		if($yaw > 180){
			$yaw -= 360;
		}elseif($yaw < -180){
			$yaw += 360;
		}
		return $yaw;
	}
}
