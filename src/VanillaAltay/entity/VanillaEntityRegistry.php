<?php

declare(strict_types=1);

namespace VanillaAltay\entity;

use pocketmine\entity\Entity;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\World;
use VanillaAltay\entity\aquatic\Axolotl;
use VanillaAltay\entity\aquatic\Cod;
use VanillaAltay\entity\aquatic\Dolphin;
use VanillaAltay\entity\aquatic\ElderGuardian;
use VanillaAltay\entity\aquatic\GlowSquid;
use VanillaAltay\entity\aquatic\Guardian;
use VanillaAltay\entity\aquatic\Nautilus;
use VanillaAltay\entity\aquatic\Pufferfish;
use VanillaAltay\entity\aquatic\Salmon;
use VanillaAltay\entity\aquatic\Squid;
use VanillaAltay\entity\aquatic\Tadpole;
use VanillaAltay\entity\aquatic\TropicalFish;
use VanillaAltay\entity\flying\Allay;
use VanillaAltay\entity\flying\Bat;
use VanillaAltay\entity\flying\Bee;
use VanillaAltay\entity\flying\Parrot;
use VanillaAltay\entity\flying\Phantom;
use VanillaAltay\entity\flying\Vex;
use VanillaAltay\entity\monster\Blaze;
use VanillaAltay\entity\monster\Bogged;
use VanillaAltay\entity\monster\CaveSpider;
use VanillaAltay\entity\monster\Creeper;
use VanillaAltay\entity\monster\Drowned;
use VanillaAltay\entity\monster\Enderman;
use VanillaAltay\entity\monster\Endermite;
use VanillaAltay\entity\monster\Hoglin;
use VanillaAltay\entity\monster\Husk;
use VanillaAltay\entity\monster\MagmaCube;
use VanillaAltay\entity\monster\Piglin;
use VanillaAltay\entity\monster\PiglinBrute;
use VanillaAltay\entity\monster\Ravager;
use VanillaAltay\entity\monster\Silverfish;
use VanillaAltay\entity\monster\Skeleton;
use VanillaAltay\entity\monster\Slime;
use VanillaAltay\entity\monster\Spider;
use VanillaAltay\entity\monster\Stray;
use VanillaAltay\entity\monster\Vindicator;
use VanillaAltay\entity\monster\WitherSkeleton;
use VanillaAltay\entity\monster\Zoglin;
use VanillaAltay\entity\monster\Zombie;
use VanillaAltay\entity\monster\ZombieVillager;
use VanillaAltay\entity\passive\Armadillo;
use VanillaAltay\entity\passive\Camel;
use VanillaAltay\entity\passive\Cat;
use VanillaAltay\entity\passive\Chicken;
use VanillaAltay\entity\passive\Cow;
use VanillaAltay\entity\passive\Donkey;
use VanillaAltay\entity\passive\Fox;
use VanillaAltay\entity\passive\Frog;
use VanillaAltay\entity\passive\Goat;
use VanillaAltay\entity\passive\Horse;
use VanillaAltay\entity\passive\Llama;
use VanillaAltay\entity\passive\Mooshroom;
use VanillaAltay\entity\passive\Mule;
use VanillaAltay\entity\passive\Ocelot;
use VanillaAltay\entity\passive\Panda;
use VanillaAltay\entity\passive\Pig;
use VanillaAltay\entity\passive\PolarBear;
use VanillaAltay\entity\passive\Rabbit;
use VanillaAltay\entity\passive\Sheep;
use VanillaAltay\entity\passive\SkeletonHorse;
use VanillaAltay\entity\passive\Sniffer;
use VanillaAltay\entity\passive\Strider;
use VanillaAltay\entity\passive\TraderLlama;
use VanillaAltay\entity\passive\Turtle;
use VanillaAltay\entity\passive\Wolf;
use VanillaAltay\entity\passive\ZombieHorse;
use VanillaAltay\entity\spawn\SpawnCategory;
use VanillaAltay\entity\spawn\SpawnConditions;
use VanillaAltay\entity\spawn\SpawnRule;

use function array_keys;
use function array_values;
use function is_subclass_of;
use function mt_rand;
use function sort;
use function str_starts_with;
use function strtolower;
use function substr;

final class VanillaEntityRegistry
{
	/** @var array<class-string<Entity>,true> */
	private static array $registeredClasses = [];

	/** @var array<string, \Closure(Location) : Entity> */
	private static array $summonFactories = [];

	private function __construct()
	{
		//NOOP
	}

	public static function register(bool $overrideAltayEntities) : void
	{
		$factory = EntityFactory::getInstance();
		if ($overrideAltayEntities) {
			self::registerType($factory, Zombie::class, ["Zombie", "minecraft:zombie"]);
			self::registerType($factory, Squid::class, ["Squid", "minecraft:squid"]);
		}
		self::registerType($factory, Chicken::class, ["Chicken", "minecraft:chicken"]);
		self::registerType($factory, Cow::class, ["Cow", "minecraft:cow"]);
		self::registerType($factory, Pig::class, ["Pig", "minecraft:pig"]);
		self::registerType($factory, Sheep::class, ["Sheep", "minecraft:sheep"]);
		self::registerType($factory, Husk::class, ["Husk", "minecraft:husk"]);
		self::registerType($factory, Drowned::class, ["Drowned", "minecraft:drowned"]);
		self::registerType($factory, ZombieVillager::class, ["ZombieVillager", "minecraft:zombie_villager", "minecraft:zombie_villager_v2"]);
		self::registerType($factory, Spider::class, ["Spider", "minecraft:spider"]);
		self::registerType($factory, CaveSpider::class, ["CaveSpider", "minecraft:cave_spider"]);
		self::registerType($factory, Silverfish::class, ["Silverfish", "minecraft:silverfish"]);
		self::registerType($factory, Endermite::class, ["Endermite", "minecraft:endermite"]);
		self::registerType($factory, Vindicator::class, ["Vindicator", "minecraft:vindicator"]);
		self::registerType($factory, Skeleton::class, ["Skeleton", "minecraft:skeleton"]);
		self::registerType($factory, Stray::class, ["Stray", "minecraft:stray"]);
		self::registerType($factory, Bogged::class, ["Bogged", "minecraft:bogged"]);
		self::registerType($factory, Creeper::class, ["Creeper", "minecraft:creeper"]);
		self::registerType($factory, Cod::class, ["Cod", "minecraft:cod"]);
		self::registerType($factory, Salmon::class, ["Salmon", "minecraft:salmon"]);
		self::registerType($factory, TropicalFish::class, ["TropicalFish", "minecraft:tropicalfish"]);
		self::registerType($factory, Pufferfish::class, ["Pufferfish", "minecraft:pufferfish"]);
		self::registerType($factory, GlowSquid::class, ["GlowSquid", "minecraft:glow_squid"]);
		self::registerType($factory, Axolotl::class, ["Axolotl", "minecraft:axolotl"]);
		self::registerType($factory, Dolphin::class, ["Dolphin", "minecraft:dolphin"]);
		self::registerType($factory, Tadpole::class, ["Tadpole", "minecraft:tadpole"]);
		self::registerType($factory, Nautilus::class, ["Nautilus", "minecraft:nautilus"]);
		self::registerType($factory, Guardian::class, ["Guardian", "minecraft:guardian"]);
		self::registerType($factory, ElderGuardian::class, ["ElderGuardian", "minecraft:elder_guardian"]);
		self::registerType($factory, Bat::class, ["Bat", "minecraft:bat"]);
		self::registerType($factory, Parrot::class, ["Parrot", "minecraft:parrot"]);
		self::registerType($factory, Allay::class, ["Allay", "minecraft:allay"]);
		self::registerType($factory, Bee::class, ["Bee", "minecraft:bee"]);
		self::registerType($factory, Phantom::class, ["Phantom", "minecraft:phantom"]);
		self::registerType($factory, Vex::class, ["Vex", "minecraft:vex"]);
		self::registerType($factory, Rabbit::class, ["Rabbit", "minecraft:rabbit"]);
		self::registerType($factory, Horse::class, ["Horse", "minecraft:horse"]);
		self::registerType($factory, Donkey::class, ["Donkey", "minecraft:donkey"]);
		self::registerType($factory, Mule::class, ["Mule", "minecraft:mule"]);
		self::registerType($factory, Goat::class, ["Goat", "minecraft:goat"]);
		self::registerType($factory, Llama::class, ["Llama", "minecraft:llama"]);
		self::registerType($factory, Camel::class, ["Camel", "minecraft:camel"]);
		self::registerType($factory, Armadillo::class, ["Armadillo", "minecraft:armadillo"]);
		self::registerType($factory, Mooshroom::class, ["Mooshroom", "minecraft:mooshroom"]);
		self::registerType($factory, WitherSkeleton::class, ["WitherSkeleton", "minecraft:wither_skeleton"]);
		self::registerType($factory, Piglin::class, ["Piglin", "minecraft:piglin"]);
		self::registerType($factory, PiglinBrute::class, ["PiglinBrute", "minecraft:piglin_brute"]);
		self::registerType($factory, Hoglin::class, ["Hoglin", "minecraft:hoglin"]);
		self::registerType($factory, Zoglin::class, ["Zoglin", "minecraft:zoglin"]);
		self::registerType($factory, Blaze::class, ["Blaze", "minecraft:blaze"]);
		self::registerType($factory, Ravager::class, ["Ravager", "minecraft:ravager"]);
		self::registerType($factory, Slime::class, ["Slime", "minecraft:slime"]);
		self::registerType($factory, MagmaCube::class, ["MagmaCube", "minecraft:magma_cube"]);
		self::registerType($factory, Enderman::class, ["Enderman", "minecraft:enderman"]);
		foreach (
			[
				[Wolf::class, "Wolf", "minecraft:wolf"], [Cat::class, "Cat", "minecraft:cat"],
				[Ocelot::class, "Ocelot", "minecraft:ocelot"], [Fox::class, "Fox", "minecraft:fox"],
				[Panda::class, "Panda", "minecraft:panda"], [PolarBear::class, "PolarBear", "minecraft:polar_bear"],
				[Turtle::class, "Turtle", "minecraft:turtle"], [Frog::class, "Frog", "minecraft:frog"],
				[Sniffer::class, "Sniffer", "minecraft:sniffer"], [Strider::class, "Strider", "minecraft:strider"],
				[SkeletonHorse::class, "SkeletonHorse", "minecraft:skeleton_horse"], [ZombieHorse::class, "ZombieHorse", "minecraft:zombie_horse"],
				[TraderLlama::class, "TraderLlama", "minecraft:trader_llama"],
			] as [$class, $legacyName, $identifier]
		) {
			self::registerType($factory, $class, [$legacyName, $identifier]);
		}
		foreach (
			[
				[monster\Breeze::class, "Breeze", "minecraft:breeze"],
				[monster\CamelHusk::class, "CamelHusk", "minecraft:camel_husk"],
				[monster\Creaking::class, "Creaking", "minecraft:creaking"],
				[monster\Evoker::class, "EvocationIllager", "minecraft:evocation_illager"],
				[monster\Pillager::class, "Pillager", "minecraft:pillager"],
				[monster\Warden::class, "Warden", "minecraft:warden"],
				[monster\Witch::class, "Witch", "minecraft:witch"],
				[monster\Shulker::class, "Shulker", "minecraft:shulker"],
				[monster\Parched::class, "Parched", "minecraft:parched"],
				[monster\ZombiePigman::class, "ZombiePigman", "minecraft:zombie_pigman"],
				[monster\ZombieNautilus::class, "ZombieNautilus", "minecraft:zombie_nautilus"],
				[passive\CopperGolem::class, "CopperGolem", "minecraft:copper_golem"],
				[passive\IronGolem::class, "IronGolem", "minecraft:iron_golem"],
				[passive\SnowGolem::class, "SnowGolem", "minecraft:snow_golem"],
				[passive\WanderingTrader::class, "WanderingTrader", "minecraft:wandering_trader"]
				,[flying\Ghast::class, "Ghast", "minecraft:ghast"]
				,[flying\HappyGhast::class, "HappyGhast", "minecraft:happy_ghast"]
				,[flying\Wither::class, "Wither", "minecraft:wither"]
				,[flying\EnderDragon::class, "EnderDragon", "minecraft:ender_dragon"]
				,[monster\SulfurCube::class, "SulfurCube", "minecraft:sulfur_cube"]
				,[passive\Npc::class, "Npc", "minecraft:npc"],
			] as [$class, $legacyName, $identifier]
		) {
			self::registerType($factory, $class, [$legacyName, $identifier]);
		}
		self::registerType($factory, passive\Villager::class, ["Villager", "VillagerV2", "minecraft:villager", "minecraft:villager_v2"]);
		foreach (
			[
				[projectile\Fireball::class,"Fireball","minecraft:fireball"],
				[projectile\SmallFireball::class,"SmallFireball","minecraft:small_fireball"],
				[projectile\WitherSkull::class,"WitherSkull","minecraft:wither_skull"],
				[projectile\DragonFireball::class,"DragonFireball","minecraft:dragon_fireball"],
				[projectile\ShulkerBullet::class,"ShulkerBullet","minecraft:shulker_bullet"]
				,[projectile\EvocationFang::class,"EvocationFang","minecraft:evocation_fang"],
			] as [$class,$legacy,$identifier]
		) {
			self::registerProjectile($factory, $class, [$legacy,$identifier]);
		}
	}

	/** @param class-string<Entity> $class @param list<string> $saveNames */
	private static function registerType(EntityFactory $factory, string $class, array $saveNames) : void
	{
		$factory->register($class, static function (World $world, CompoundTag $nbt) use ($class) : Entity {
			return new $class(EntityDataHelper::parseLocation($nbt, $world), $nbt);
		}, $saveNames);
		self::$registeredClasses[$class] = true;
		self::registerSummonAliases(
			$saveNames,
			static fn(Location $location) : Entity => new $class($location),
		);
	}

	/** @param class-string<\pocketmine\entity\projectile\Projectile> $class @param list<string> $saveNames */
	private static function registerProjectile(EntityFactory $factory, string $class, array $saveNames) : void
	{
		$factory->register($class, static function (World $world, CompoundTag $nbt) use ($class) : Entity {
			return new $class(EntityDataHelper::parseLocation($nbt, $world), null, $nbt);
		}, $saveNames);
		self::$registeredClasses[$class] = true;
		self::registerSummonAliases(
			$saveNames,
			static fn(Location $location) : Entity => new $class($location, null),
		);
	}

	/**
	 * @param list<string> $aliases
	 * @param \Closure(Location) : Entity $factory
	 */
	private static function registerSummonAliases(array $aliases, \Closure $factory) : void
	{
		foreach ($aliases as $alias) {
			$normalized = strtolower($alias);
			self::$summonFactories[$normalized] = $factory;

			if (str_starts_with($normalized, "minecraft:")) {
				self::$summonFactories[substr($normalized, 10)] = $factory;
			}
		}
	}

	/** @return list<string> */
	public static function getSummonIdentifiers() : array
	{
		$identifiers = [];
		foreach (array_keys(self::$summonFactories) as $identifier) {
			if (str_starts_with($identifier, "minecraft:")) {
				$identifiers[] = $identifier;
			}
		}

		sort($identifiers);

		return array_values($identifiers);
	}

	public static function createForSummon(string $identifier, Location $location) : ?Entity
	{
		$normalized = strtolower($identifier);
		$factory = self::$summonFactories[$normalized]
			?? self::$summonFactories["minecraft:" . $normalized]
			?? null;

		return $factory !== null ? $factory($location) : null;
	}

	/** @return array<string,string> class => error */
	public static function selfTest(World $world) : array
	{
		$errors = [];
		$spawn = $world->getSafeSpawn();
		$location = Location::fromObject($spawn->add(0, 3, 0), $world, 0, 0);
		foreach (array_keys(self::$registeredClasses) as $class) {
			try {
				$entity = is_subclass_of($class, \pocketmine\entity\projectile\Projectile::class) ? new $class($location, null) : new $class($location);
				$entity->getDrops();
				$entity->close();
			} catch (\Throwable $e) {
				$errors[$class] = $e::class . ": " . $e->getMessage();
			}
		}
		return $errors;
	}

	/** @return list<SpawnRule> */
	public static function getSpawnRules() : array
	{
		$rules = [
			new SpawnRule(
				"minecraft:zombie",
				SpawnCategory::MONSTER,
				95,
				2,
				4,
				static fn(World $world, Vector3 $position) : Zombie => new Zombie(Location::fromObject($position, $world, (float) mt_rand(0, 359), 0.0)),
				SpawnConditions::monsterOnGround(...),
			),
		];
		foreach (
			[
				[Chicken::class, "minecraft:chicken", 10, 2, 4],
				[Cow::class, "minecraft:cow", 8, 2, 3],
				[Pig::class, "minecraft:pig", 10, 1, 3],
				[Sheep::class, "minecraft:sheep", 12, 2, 3],
			] as [$class, $identifier, $weight, $herdMin, $herdMax]
		) {
			$rules[] = new SpawnRule(
				$identifier,
				SpawnCategory::CREATURE,
				$weight,
				$herdMin,
				$herdMax,
				static fn(World $world, Vector3 $position) : Entity => new $class(Location::fromObject($position, $world, (float) mt_rand(0, 359), 0.0)),
				SpawnConditions::creatureOnGrass(...),
			);
		}
		foreach (
			[
				[Spider::class, "minecraft:spider", 100, 1, 1, SpawnConditions::monsterOnGround(...)],
				[Husk::class, "minecraft:husk", 240, 2, 4, SpawnConditions::monsterInDesert(...)],
				[Skeleton::class, "minecraft:skeleton", 100, 1, 1, SpawnConditions::monsterOnGround(...)],
				[Creeper::class, "minecraft:creeper", 100, 1, 1, SpawnConditions::monsterOnGround(...)],
			] as [$class, $identifier, $weight, $herdMin, $herdMax, $condition]
		) {
			$rules[] = new SpawnRule(
				$identifier,
				SpawnCategory::MONSTER,
				$weight,
				$herdMin,
				$herdMax,
				static fn(World $world, Vector3 $position) : Entity => new $class(Location::fromObject($position, $world, (float) mt_rand(0, 359), 0.0)),
				$condition,
			);
		}
		foreach (
			[
				[Axolotl::class, "minecraft:axolotl", 4, 6, 10, SpawnConditions::axolotlWater(...)],
				[Dolphin::class, "minecraft:dolphin", 3, 5, 7, SpawnConditions::dolphinWater(...)],
			] as [$class, $identifier, $weight, $herdMin, $herdMax, $condition]
		) {
			$rules[] = new SpawnRule(
				$identifier,
				SpawnCategory::WATER_CREATURE,
				$weight,
				$herdMin,
				$herdMax,
				static fn(World $world, Vector3 $position) : Entity => new $class(Location::fromObject($position, $world, (float) mt_rand(0, 359), 0.0)),
				$condition,
			);
		}
		foreach (
			[
				[Cod::class, "minecraft:cod", 75, 4, 7, SpawnConditions::coldOceanWater(...)],
				[Salmon::class, "minecraft:salmon", 26, 3, 5, SpawnConditions::coldOceanWater(...)],
				[TropicalFish::class, "minecraft:tropicalfish", 75, 3, 5, SpawnConditions::warmOceanWater(...)],
				[Pufferfish::class, "minecraft:pufferfish", 25, 3, 5, SpawnConditions::warmOceanWater(...)],
				[GlowSquid::class, "minecraft:glow_squid", 10, 2, 4, SpawnConditions::glowSquidWater(...)],
				[Squid::class, "minecraft:squid", 2, 2, 4, SpawnConditions::coldOceanWater(...)],
			] as [$class, $identifier, $weight, $herdMin, $herdMax, $condition]
		) {
			$rules[] = new SpawnRule(
				$identifier,
				SpawnCategory::WATER_CREATURE,
				$weight,
				$herdMin,
				$herdMax,
				static fn(World $world, Vector3 $position) : Entity => new $class(Location::fromObject($position, $world, (float) mt_rand(0, 359), 0.0)),
				$condition,
			);
		}
		$rules[] = new SpawnRule(
			"minecraft:bat",
			SpawnCategory::AMBIENT,
			10,
			8,
			8,
			static fn(World $world, Vector3 $position) : Bat => new Bat(Location::fromObject($position, $world, (float) mt_rand(0, 359), 0.0)),
			SpawnConditions::batCave(...),
		);
		$rules[] = new SpawnRule(
			"minecraft:parrot",
			SpawnCategory::CREATURE,
			40,
			1,
			2,
			static fn(World $world, Vector3 $position) : Parrot => new Parrot(Location::fromObject($position, $world, (float) mt_rand(0, 359), 0.0)),
			SpawnConditions::parrotJungle(...),
		);
		foreach (
			[
				[Rabbit::class, "minecraft:rabbit", 4, 2, 3, SpawnConditions::creatureOnGrass(...)],
				[Horse::class, "minecraft:horse", 4, 2, 6, SpawnConditions::creatureOnGrass(...)],
				[Donkey::class, "minecraft:donkey", 1, 1, 3, SpawnConditions::creatureOnGrass(...)],
				[Goat::class, "minecraft:goat", 5, 1, 3, SpawnConditions::mountainAnimal(...)],
				[Llama::class, "minecraft:llama", 5, 4, 6, SpawnConditions::mountainAnimal(...)],
				[Camel::class, "minecraft:camel", 1, 1, 1, SpawnConditions::camelDesert(...)],
				[Armadillo::class, "minecraft:armadillo", 10, 2, 3, SpawnConditions::armadilloBiome(...)],
				[Mooshroom::class, "minecraft:mooshroom", 8, 4, 8, SpawnConditions::mushroomIsland(...)],
			] as [$class, $identifier, $weight, $herdMin, $herdMax, $condition]
		) {
			$rules[] = new SpawnRule(
				$identifier,
				SpawnCategory::CREATURE,
				$weight,
				$herdMin,
				$herdMax,
				static fn(World $world, Vector3 $position) : Entity => new $class(Location::fromObject($position, $world, (float) mt_rand(0, 359), 0.0)),
				$condition,
			);
		}
		foreach (
			[
				[Piglin::class, "minecraft:piglin", 100, 2, 4],
				[Hoglin::class, "minecraft:hoglin", 100, 3, 4],
				[MagmaCube::class, "minecraft:magma_cube", 100, 1, 4],
			] as [$class, $identifier, $weight, $herdMin, $herdMax]
		) {
			$rules[] = new SpawnRule(
				$identifier,
				SpawnCategory::MONSTER,
				$weight,
				$herdMin,
				$herdMax,
				static fn(World $world, Vector3 $position) : Entity => new $class(Location::fromObject($position, $world, (float) mt_rand(0, 359), 0.0)),
				SpawnConditions::netherMonster(...),
			);
		}
		$rules[] = new SpawnRule(
			"minecraft:enderman",
			SpawnCategory::MONSTER,
			10,
			1,
			4,
			static fn(World $world, Vector3 $position) : Enderman => new Enderman(Location::fromObject($position, $world, (float) mt_rand(0, 359), 0.0)),
			SpawnConditions::monsterOnGround(...),
		);
		foreach (
			[
				[Wolf::class, "minecraft:wolf", 8, 4, 4, SpawnConditions::taigaAnimal(...)],
				[Fox::class, "minecraft:fox", 8, 2, 4, SpawnConditions::taigaAnimal(...)],
				[Ocelot::class, "minecraft:ocelot", 30, 1, 2, SpawnConditions::jungleAnimal(...)],
				[Panda::class, "minecraft:panda", 10, 1, 2, SpawnConditions::jungleAnimal(...)],
				[PolarBear::class, "minecraft:polar_bear", 1, 1, 2, SpawnConditions::frozenAnimal(...)],
				[Turtle::class, "minecraft:turtle", 8, 2, 6, SpawnConditions::turtleBeach(...)],
				[Frog::class, "minecraft:frog", 10, 2, 5, SpawnConditions::frogWetland(...)],
			] as [$class, $identifier, $weight, $herdMin, $herdMax, $condition]
		) {
			$rules[] = new SpawnRule(
				$identifier,
				SpawnCategory::CREATURE,
				$weight,
				$herdMin,
				$herdMax,
				static fn(World $world, Vector3 $position) : Entity => new $class(Location::fromObject($position, $world, (float) mt_rand(0, 359), 0.0)),
				$condition,
			);
		}
		foreach (
			[
				[Bogged::class,"minecraft:bogged",40,1,2,SpawnConditions::swampMonster(...),SpawnCategory::MONSTER],
				[Drowned::class,"minecraft:drowned",100,2,4,SpawnConditions::drownedWater(...),SpawnCategory::MONSTER],
				[Stray::class,"minecraft:stray",120,1,2,SpawnConditions::frozenMonster(...),SpawnCategory::MONSTER],
				[Slime::class,"minecraft:slime",100,1,1,SpawnConditions::swampMonster(...),SpawnCategory::MONSTER],
				[monster\Witch::class,"minecraft:witch",5,1,1,SpawnConditions::monsterOnGround(...),SpawnCategory::MONSTER],
				[flying\Ghast::class,"minecraft:ghast",40,1,1,SpawnConditions::netherAirMonster(...),SpawnCategory::MONSTER],
				[Phantom::class,"minecraft:phantom",100,1,1,SpawnConditions::phantomNight(...),SpawnCategory::MONSTER],
				[Strider::class,"minecraft:strider",20,2,4,SpawnConditions::lavaStrider(...),SpawnCategory::CREATURE],
				[monster\SulfurCube::class,"minecraft:sulfur_cube",150,2,4,SpawnConditions::sulfurCaves(...),SpawnCategory::CREATURE],
				[monster\ZombiePigman::class,"minecraft:zombie_pigman",100,2,4,SpawnConditions::netherMonster(...),SpawnCategory::MONSTER],
			] as [$class,$identifier,$weight,$herdMin,$herdMax,$condition,$category]
		) {
			$rules[] = new SpawnRule($identifier, $category, $weight, $herdMin, $herdMax, static fn(World $world, Vector3 $position) : Entity => new $class(Location::fromObject($position, $world, (float) mt_rand(0, 359), 0.0)), $condition);
		}
		return $rules;
	}
}
