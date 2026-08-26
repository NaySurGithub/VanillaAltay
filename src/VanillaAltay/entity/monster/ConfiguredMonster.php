<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

use function mt_rand;

abstract class ConfiguredMonster extends Monster
{
	protected const NETWORK_ID = "";

	protected const NAME = "Monster";

	protected const HEIGHT = 1.8;

	protected const WIDTH = 0.6;

	protected const HEALTH = 20;

	protected const SPEED = 0.23;

	protected const DAMAGE = 3.0;

	protected const FOLLOW = 40.0;

	public static function getNetworkTypeId() : string
	{
		return static::NETWORK_ID;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(static::HEIGHT, static::WIDTH);
	}

	protected function getVanillaMaxHealth() : int
	{
		return static::HEALTH;
	}

	protected function getAttackSpeed() : float
	{
		return static::SPEED;
	}

	protected function getWanderSpeed() : float
	{
		return static::SPEED * 0.75;
	}

	protected function getAttackDamage() : float
	{
		return static::DAMAGE;
	}

	protected function getFollowRange() : float
	{
		return static::FOLLOW;
	}

	public function getName() : string
	{
		return static::NAME;
	}

	public function getDrops() : array
	{
		return match (static::NETWORK_ID) {
			EntityIds::EVOCATION_ILLAGER => [VanillaItems::TOTEM(),VanillaItems::EMERALD()->setCount(mt_rand(0, 2))],
			EntityIds::WITCH => [VanillaItems::REDSTONE_DUST()->setCount(mt_rand(4, 8)),VanillaItems::STICK()->setCount(mt_rand(0, 1)),VanillaItems::SPIDER_EYE()->setCount(mt_rand(0, 1)),VanillaItems::GLOWSTONE_DUST()->setCount(mt_rand(0, 1)),VanillaItems::GUNPOWDER()->setCount(mt_rand(0, 1))],
			EntityIds::SHULKER => mt_rand(0, 1) === 1 ? [VanillaItems::SHULKER_SHELL()] : [],
			EntityIds::PARCHED => [VanillaItems::BONE()->setCount(mt_rand(0, 2)),VanillaItems::ARROW()->setCount(mt_rand(0, 2))],
			EntityIds::ZOMBIE_PIGMAN => [VanillaItems::ROTTEN_FLESH()->setCount(mt_rand(0, 1)),VanillaItems::GOLD_NUGGET()->setCount(mt_rand(0, 1))],
			EntityIds::ZOMBIE_NAUTILUS => [VanillaItems::ROTTEN_FLESH()->setCount(mt_rand(0, 3))],
			EntityIds::CAMEL_HUSK => [VanillaItems::ROTTEN_FLESH()->setCount(mt_rand(0, 3))],
			EntityIds::BLAZE => [VanillaItems::BLAZE_ROD()->setCount(mt_rand(0, 1))],
			EntityIds::VINDICATOR => [VanillaItems::EMERALD()->setCount(mt_rand(0, 1))],
			default => [],
		};
	}

	public function getXpDropAmount() : int
	{
		return 5;
	}
}
