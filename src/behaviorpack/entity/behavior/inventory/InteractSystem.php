<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\inventory;

use behaviorpack\entity\behavior\EntitySystem;
use behaviorpack\entity\BehaviorEntity;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\SpawnParticleEffectPacket;
use pocketmine\player\Player;
use function array_is_list;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function max;
use function str_contains;

/**
 * minecraft:interact: the first interaction whose filters pass runs when a
 * player interacts with the entity.
 */
final class InteractSystem extends EntitySystem{

	private const DATA_COOLDOWN = "interact_cooldown";

	/**
	 * @return list<array<mixed>>
	 */
	public function getInteractions() : array{
		$interactions = $this->config["interactions"] ?? $this->config;
		if(!is_array($interactions)){
			return [];
		}
		if(!array_is_list($interactions)){
			$interactions = [$interactions];
		}
		$result = [];
		foreach($interactions as $interaction){
			if(is_array($interaction)){
				$result[] = $interaction;
			}
		}
		return $result;
	}

	/**
	 * @param array<mixed> $interaction
	 */
	private function passes(array $interaction, Player $player) : bool{
		$trigger = $interaction["on_interact"] ?? null;
		$context = ["other" => $player, "player" => $player];
		if(is_array($trigger) && is_array($trigger["filters"] ?? null)){
			return $this->entity->testFilter($trigger["filters"], $context, true);
		}
		return true;
	}

	private function currentTick() : int{
		return $this->entity->getWorld()->getServer()->getTick();
	}

	private function isCoolingDown() : bool{
		$until = $this->entity->getData(self::DATA_COOLDOWN);
		return is_int($until) && $until > $this->currentTick();
	}

	/**
	 * @param array<mixed> $interaction
	 */
	private function startCooldown(array $interaction) : void{
		$seconds = BehaviorEntity::toFloat($interaction["cooldown"] ?? null, 0.0);
		if($seconds > 0){
			$this->entity->setData(self::DATA_COOLDOWN, $this->currentTick() + (int) ($seconds * 20));
		}
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if($this->isCoolingDown()){
			return false;
		}
		foreach($this->getInteractions() as $interaction){
			$afterAttack = BehaviorEntity::toFloat($interaction["cooldown_after_being_attacked"] ?? null, 0.0);
			$lastHurt = $this->entity->getLastHurtTick();
			if($afterAttack > 0 && $lastHurt >= 0 && $this->currentTick() - $lastHurt < (int) ($afterAttack * 20)){
				continue;
			}
			if(!$this->passes($interaction, $player)){
				continue;
			}
			$this->run($interaction, $player);
			return true;
		}
		return false;
	}

	/**
	 * Returns the interact text of the first interaction available to a
	 * player.
	 */
	public function getInteractText(Player $player) : ?string{
		foreach($this->getInteractions() as $interaction){
			if(is_string($interaction["interact_text"] ?? null) && $this->passes($interaction, $player)){
				return $interaction["interact_text"];
			}
		}
		return null;
	}

	/**
	 * @param array<mixed> $interaction
	 */
	private function run(array $interaction, Player $player) : void{
		$this->startCooldown($interaction);
		$inventory = $player->getInventory();
		$held = $inventory->getItemInHand();
		$world = $this->entity->getWorld();
		$position = $this->entity->getLocation();
		$context = ["other" => $player, "player" => $player];

		if(($interaction["swing"] ?? false) === true){
			$player->broadcastAnimation(new ArmSwingAnimation($player));
		}

		$this->playSound($interaction["play_sounds"] ?? null);
		$this->spawnParticle($interaction["particle_on_start"] ?? null, $player);

		$healthAmount = $interaction["health_amount"] ?? null;
		if(is_numeric($healthAmount) && $healthAmount > 0){
			$this->entity->heal(new EntityRegainHealthEvent($this->entity, (float) $healthAmount, EntityRegainHealthEvent::CAUSE_CUSTOM));
		}

		$equipment = MobEquipment::of($this->entity);
		if(($interaction["give_item"] ?? false) === true && !$held->isNull()){
			$given = (clone $held)->setCount(1);
			$previous = $equipment->getMainHand();
			$equipment->setMainHand($given);
			if(!$previous->isNull()){
				$this->giveToPlayer($player, $previous);
			}
		}
		if(($interaction["take_item"] ?? false) === true){
			$taken = $equipment->getMainHand();
			if(!$taken->isNull()){
				$equipment->setMainHand(VanillaItems::AIR());
				$this->giveToPlayer($player, $taken);
			}
		}
		$dropSlot = $interaction["drop_item_slot"] ?? null;
		if(is_string($dropSlot) || is_int($dropSlot)){
			$this->dropSlot($equipment, $dropSlot);
		}
		$equipSlot = $interaction["equip_item_slot"] ?? null;
		if((is_string($equipSlot) || is_int($equipSlot)) && !$held->isNull()){
			$single = (clone $held)->setCount(1);
			if(is_int($equipSlot) || is_numeric($equipSlot)){
				$equipment->setSlot((int) $equipSlot, $single);
			}elseif(!$equipment->setByName($equipSlot, $single)){
				$equipment->equip($single);
			}
		}

		$killer = $player;
		foreach(ItemCodec::rollTable($interaction["spawn_items"]["table"] ?? null, $this->entity, $killer) as $item){
			$world->dropItem($position->add(0, 0.5, 0), $item);
		}
		$addItems = ItemCodec::rollTable($interaction["add_items"]["table"] ?? null, $this->entity, $killer);
		if($addItems !== []){
			$system = $this->entity->getSystem("minecraft:inventory");
			$left = $system instanceof InventorySystem ? $system->addItems(...$addItems) : $addItems;
			foreach($left as $item){
				$world->dropItem($position, $item);
			}
		}

		$spawnEntities = $interaction["spawn_entities"] ?? null;
		if(is_string($spawnEntities)){
			$spawnEntities = [$spawnEntities];
		}
		if(is_array($spawnEntities)){
			foreach($spawnEntities as $identifier){
				if(is_string($identifier)){
					$this->spawnEntity($identifier);
				}
			}
		}

		$consumed = false;
		if(($interaction["use_item"] ?? false) === true && !$held->isNull() && $player->hasFiniteResources()){
			$held->pop();
			$consumed = true;
		}
		$hurt = $interaction["hurt_item"] ?? 0;
		if(is_int($hurt) && $hurt > 0 && $held instanceof Durable && !$held->isNull() && $player->hasFiniteResources()){
			$held->applyDamage($hurt);
			if($held->isBroken()){
				$held->pop();
			}
			$consumed = true;
		}
		$transform = $interaction["transform_to_item"] ?? null;
		if(is_string($transform) && $transform !== ""){
			$result = ItemCodec::parse($transform);
			if($result !== null){
				if(!$consumed && $player->hasFiniteResources() && !$held->isNull()){
					$held->pop();
				}
				$consumed = true;
				if($held->isNull()){
					$held = $result;
				}else{
					$this->giveToPlayer($player, $result);
				}
			}
		}
		if($consumed){
			$inventory->setItemInHand($held);
		}

		$trigger = $interaction["on_interact"] ?? null;
		if(is_array($trigger)){
			unset($trigger["filters"]);
			$this->entity->runTrigger($trigger, $context);
		}elseif(is_string($trigger)){
			$this->entity->runTrigger($trigger, $context);
		}
	}

	private function giveToPlayer(Player $player, Item $item) : void{
		foreach($player->getInventory()->addItem($item) as $left){
			$player->getWorld()->dropItem($player->getLocation(), $left);
		}
	}

	private function dropSlot(MobEquipment $equipment, string|int $slot) : void{
		$world = $this->entity->getWorld();
		if(is_int($slot) || is_numeric($slot)){
			$item = $equipment->getSlot((int) $slot);
			if(!$item->isNull()){
				$equipment->setSlot((int) $slot, VanillaItems::AIR());
				$world->dropItem($this->entity->getLocation(), $item);
			}
			return;
		}
		$item = $equipment->getByName($slot);
		if(!$item->isNull()){
			$equipment->setByName($slot, VanillaItems::AIR());
			$world->dropItem($this->entity->getLocation(), $item);
		}
	}

	private function spawnEntity(string $identifier) : ?Entity{
		if(!str_contains($identifier, ":")){
			$identifier = "minecraft:" . $identifier;
		}
		$location = $this->entity->getLocation();
		$nbt = CompoundTag::create()
			->setString(EntityFactory::TAG_IDENTIFIER, $identifier)
			->setTag(Entity::TAG_POS, new ListTag([new DoubleTag($location->x), new DoubleTag($location->y), new DoubleTag($location->z)]))
			->setTag(Entity::TAG_ROTATION, new ListTag([new FloatTag($location->yaw), new FloatTag(0.0)]));
		$entity = EntityFactory::getInstance()->createFromData($this->entity->getWorld(), $nbt);
		$entity?->spawnToAll();
		return $entity;
	}

	private function playSound(mixed $sound) : void{
		if(!is_string($sound) || $sound === ""){
			return;
		}
		$position = $this->entity->getPosition();
		$this->entity->getWorld()->broadcastPacketToViewers($position, LevelSoundEventPacket::create($sound, $position, -1, $this->entity->getIdentifier(), false, false, $this->entity->getId(), null));
	}

	private function spawnParticle(mixed $config, Player $player) : void{
		if(!is_array($config) || !is_string($config["particle_type"] ?? null)){
			return;
		}
		$type = $config["particle_type"];
		$position = $this->entity->getPosition()->add(0, BehaviorEntity::toFloat($config["particle_y_offset"] ?? null, 0.0), 0);
		if(($config["particle_offset_towards_interactor"] ?? false) === true){
			$direction = $player->getPosition()->subtractVector($position);
			$length = max($direction->length(), 0.0001);
			$position = $position->addVector($direction->divide($length)->multiply($this->entity->getSize()->getWidth() / 2));
		}
		$name = str_contains($type, ":") ? $type : "minecraft:" . $type . "_particle";
		$this->entity->getWorld()->broadcastPacketToViewers($position, SpawnParticleEffectPacket::create(0, -1, $position, $name, null));
	}
}
