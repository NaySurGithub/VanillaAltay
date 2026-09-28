<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior;

use behaviorpack\entity\BehaviorEntity;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

/**
 * The runtime of one component of a custom entity. A system is created when
 * its component becomes active, receives the new configuration when a
 * component group changes it, and is removed with the component. State that
 * must survive a reload goes in the data store of the entity.
 */
abstract class EntitySystem{

	/**
	 * @param array<mixed> $config
	 */
	public function __construct(
		protected BehaviorEntity $entity,
		protected string $component,
		protected array $config
	){}

	public function getComponent() : string{
		return $this->component;
	}

	/**
	 * @return array<mixed>
	 */
	public function getConfig() : array{
		return $this->config;
	}

	/**
	 * Called once the system is attached, and again after the configuration
	 * changed.
	 */
	public function onAdd() : void{
	}

	public function onRemove() : void{
	}

	/**
	 * @param array<mixed> $config
	 */
	public function updateConfig(array $config) : void{
		$this->config = $config;
		$this->onAdd();
	}

	public function tick(int $tickDiff) : void{
	}

	/**
	 * Called before the damage is applied: the system may change or cancel it.
	 */
	public function beforeDamage(EntityDamageEvent $source) : void{
	}

	/**
	 * Called after the damage was applied.
	 */
	public function afterDamage(EntityDamageEvent $source) : void{
	}

	/**
	 * Returns whether the interaction did something.
	 */
	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		return false;
	}

	public function onDeath() : void{
	}

	/**
	 * Called after the entity was spawned to a player, to send what the spawn
	 * packet does not carry.
	 */
	public function onSpawnTo(Player $player) : void{
	}

	/**
	 * Called when the attack target of the entity changes.
	 */
	public function onTargetChanged(?\pocketmine\entity\Entity $previous, ?\pocketmine\entity\Entity $target) : void{
	}
}
