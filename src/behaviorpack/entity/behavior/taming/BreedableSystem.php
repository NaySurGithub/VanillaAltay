<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\taming;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\utils\Utils;
use pocketmine\world\particle\HeartParticle;
use function floor;
use function is_array;
use function is_int;
use function is_string;
use function max;
use function min;
use function mt_rand;

/**
 * minecraft:breedable: feeding a breed item puts the entity in love for 30
 * seconds; two entities in love that breed with each other make a baby and
 * wait 5 minutes before breeding again. The state is kept as
 * "in_love_until", "love_player" and "breed_cooldown_until" (server ticks).
 */
final class BreedableSystem extends EntitySystem{

	public const LOVE_TICKS = 600;
	public const COOLDOWN_TICKS = 6000;

	private int $particleTicks = 0;

	public function onAdd() : void{
		$this->entity->setFlag(EntityMetadataFlags::INLOVE, $this->isInLove());
	}

	public function onRemove() : void{
		$this->entity->setFlag(EntityMetadataFlags::INLOVE, false);
		$this->entity->setData("in_love_until", null);
		$this->entity->setData("love_player", null);
	}

	private function now() : int{
		return $this->entity->getWorld()->getServer()->getTick();
	}

	private function tickData(string $key) : int{
		$value = $this->entity->getData($key);
		return is_int($value) ? $value : 0;
	}

	public function isInLove() : bool{
		return $this->tickData("in_love_until") > $this->now();
	}

	public function isOnCooldown() : bool{
		return $this->tickData("breed_cooldown_until") > $this->now();
	}

	public function getLovePlayer() : ?Player{
		$name = $this->entity->getData("love_player");
		return is_string($name) ? $this->entity->getWorld()->getServer()->getPlayerExact($name) : null;
	}

	private function isTamed() : bool{
		return $this->entity->hasComponent("minecraft:is_tamed");
	}

	private function isBaby() : bool{
		return $this->entity->hasComponent("minecraft:is_baby");
	}

	public function setInLove(?Player $player) : void{
		$this->entity->setData("in_love_until", $this->now() + self::LOVE_TICKS);
		$this->entity->setData("love_player", $player?->getName());
		$this->entity->setFlag(EntityMetadataFlags::INLOVE, true);
		TamingHelper::particles($this->entity, new HeartParticle(), 7);
	}

	public function clearLove() : void{
		$this->entity->setData("in_love_until", null);
		$this->entity->setData("love_player", null);
		$this->entity->setFlag(EntityMetadataFlags::INLOVE, false);
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if($this->isBaby() || $this->isInLove() || $this->isOnCooldown()){
			return false;
		}
		$requireTame = $this->config["require_tame"] ?? null;
		if(!$this->isTamed() && ($requireTame === true || ($requireTame === null && $this->entity->hasComponent("minecraft:tameable")))){
			return false;
		}
		if(!TamingHelper::matches($player->getInventory()->getItemInHand(), $this->config["breed_items"] ?? [])){
			return false;
		}
		$filters = $this->config["love_filters"] ?? null;
		if(is_array($filters) && !$this->entity->testFilter($filters, ["player" => $player, "other" => $player])){
			return false;
		}
		TamingHelper::consumeHeldItem($player);
		$this->setInLove($player);
		return true;
	}

	public function tick(int $tickDiff) : void{
		$until = $this->tickData("in_love_until");
		if($until === 0){
			return;
		}
		if($until <= $this->now()){
			$this->clearLove();
			return;
		}
		$this->particleTicks += $tickDiff;
		if($this->particleTicks >= 10){
			$this->particleTicks = 0;
			TamingHelper::particles($this->entity, new HeartParticle(), 1);
		}
	}

	/**
	 * Returns the breeds_with entry for a partner, or null when they cannot
	 * breed together.
	 *
	 * @return array<mixed>|null
	 */
	private function findPairing(BehaviorEntity $partner) : ?array{
		$entries = TamingHelper::objectList($this->config["breeds_with"] ?? null);
		if($entries === []){
			$offspring = $this->entity->getSystem("minecraft:offspring");
			if($offspring instanceof OffspringSystem && $offspring->hasPairs()){
				$baby = $offspring->babyFor($partner->getIdentifier());
				return $baby === null ? null : ["mate_type" => $partner->getIdentifier(), "baby_type" => $baby];
			}
			return $partner->getIdentifier() === $this->entity->getIdentifier() ? ["mate_type" => $partner->getIdentifier(), "baby_type" => $this->entity->getIdentifier()] : null;
		}
		foreach($entries as $entry){
			$mate = $entry["mate_type"] ?? null;
			if(is_string($mate) && $mate === $partner->getIdentifier()){
				return $entry;
			}
		}
		return null;
	}

	public function canBreedWith(BehaviorEntity $partner) : bool{
		if($partner === $this->entity || !$partner->isAlive() || $partner->isClosed() || !$this->entity->isAlive()){
			return false;
		}
		if($partner->getWorld() !== $this->entity->getWorld() || !$this->isInLove() || $this->isBaby()){
			return false;
		}
		$other = $partner->getSystem("minecraft:breedable");
		if(!$other instanceof BreedableSystem || !$other->isInLove() || $partner->hasComponent("minecraft:is_baby")){
			return false;
		}
		if($this->findPairing($partner) === null){
			return false;
		}
		return $this->meetsEnvironment();
	}

	private function meetsEnvironment() : bool{
		foreach(TamingHelper::objectList($this->config["environment_requirements"] ?? null) as $requirement){
			$blocks = $requirement["blocks"] ?? [];
			$blocks = is_string($blocks) ? [$blocks] : (is_array($blocks) ? $blocks : []);
			$names = [];
			foreach($blocks as $block){
				if(is_string($block)){
					$names[TamingHelper::normalizeItemName($block)] = true;
				}
			}
			if($names === []){
				continue;
			}
			$needed = max(1, (int) BehaviorEntity::toFloat($requirement["count"] ?? null, 1.0));
			$radius = min(16, max(0, (int) BehaviorEntity::toFloat($requirement["radius"] ?? null, 1.0)));
			if($this->countBlocks($names, $radius, $needed) < $needed){
				return false;
			}
		}
		return true;
	}

	/**
	 * @param array<string, true> $names
	 */
	private function countBlocks(array $names, int $radius, int $needed) : int{
		$world = $this->entity->getWorld();
		$position = $this->entity->getPosition();
		$cx = (int) floor($position->x);
		$cy = (int) floor($position->y);
		$cz = (int) floor($position->z);
		$serializer = \pocketmine\world\format\io\GlobalBlockStateHandlers::getSerializer();
		$found = 0;
		for($x = $cx - $radius; $x <= $cx + $radius; ++$x){
			for($y = $cy - $radius; $y <= $cy + $radius; ++$y){
				for($z = $cz - $radius; $z <= $cz + $radius; ++$z){
					try{
						$name = $serializer->serialize($world->getBlockAt($x, $y, $z)->getStateId())->getName();
					}catch(\Throwable){
						continue;
					}
					if(isset($names[$name]) && ++$found >= $needed){
						return $found;
					}
				}
			}
		}
		return $found;
	}

	/**
	 * Breeds with a partner: spawns the baby (and sometimes a second one),
	 * runs the breed event, clears the love of both parents, starts their
	 * cooldown and drops experience. Returns the first baby.
	 */
	public function breedWith(BehaviorEntity $partner) : ?Entity{
		if(!$this->canBreedWith($partner)){
			return null;
		}
		$pairing = $this->findPairing($partner) ?? [];
		$other = $partner->getSystem("minecraft:breedable");
		$player = $this->getLovePlayer() ?? ($other instanceof BreedableSystem ? $other->getLovePlayer() : null);

		$this->clearLove();
		$this->startCooldown();
		if($other instanceof BreedableSystem){
			$other->clearLove();
			$other->startCooldown();
		}

		$baby = null;
		if(($this->config["causes_pregnancy"] ?? false) === true){
			$this->entity->setData("pregnant", true);
		}else{
			$baby = $this->spawnBaby($partner, $pairing, $player);
			$extra = BehaviorEntity::toFloat($this->config["extra_baby_chance"] ?? null, 0.0);
			if($baby !== null && $extra > 0 && Utils::getRandomFloat() < $extra){
				$this->spawnBaby($partner, $pairing, $player);
			}
		}

		$context = ["other" => $partner];
		if($baby !== null){
			$context["baby"] = $baby;
		}
		if($player !== null){
			$context["player"] = $player;
		}
		$this->entity->runTrigger($pairing["breed_event"] ?? null, $context);

		$world = $this->entity->getWorld();
		$world->dropExperience($this->entity->getPosition(), mt_rand(1, 7));
		return $baby;
	}

	public function startCooldown() : void{
		$this->entity->setData("breed_cooldown_until", $this->now() + self::COOLDOWN_TICKS);
	}

	/**
	 * @param array<mixed> $pairing
	 */
	private function spawnBaby(BehaviorEntity $partner, array $pairing, ?Player $player) : ?Entity{
		$type = $pairing["baby_type"] ?? $this->entity->getIdentifier();
		if(!is_string($type)){
			return null;
		}
		$a = $this->entity->getLocation();
		$b = $partner->getLocation();
		$location = new Location(($a->x + $b->x) / 2, min($a->y, $b->y), ($a->z + $b->z) / 2, $a->getWorld(), $a->yaw, 0.0);
		$baby = TamingHelper::createEntity($type, $location);
		if($baby === null){
			return null;
		}
		if($baby instanceof BehaviorEntity){
			$this->inheritVariants($baby, $partner);
		}
		$baby->spawnToAll();
		if($baby instanceof BehaviorEntity){
			$baby->triggerEvent("minecraft:entity_born", ["parent" => $this->entity, "other" => $partner]);
			if(($this->config["inherit_tamed"] ?? true) !== false && $this->isTamed()){
				$owner = $this->entity->getOwner() ?? $player;
				if($owner !== null){
					$baby->setOwner($owner);
				}
			}
			if(($this->config["blend_attributes"] ?? true) !== false){
				$this->blendAttributes($baby, $partner);
			}
		}
		return $baby;
	}

	private function inheritVariants(BehaviorEntity $baby, BehaviorEntity $partner) : void{
		$mutation = is_array($this->config["mutation_factor"] ?? null) ? $this->config["mutation_factor"] : [];
		$deny = is_array($this->config["deny_parents_variant"] ?? null) ? $this->config["deny_parents_variant"] : null;
		$parent = mt_rand(0, 1) === 0 ? $this->entity : $partner;
		$variant = $parent->getData("variant");
		if($deny !== null && Utils::getRandomFloat() < BehaviorEntity::toFloat($deny["chance"] ?? null, 0.0)){
			$min = (int) BehaviorEntity::toFloat($deny["min_variant"] ?? null, 0.0);
			$max = (int) BehaviorEntity::toFloat($deny["max_variant"] ?? null, (float) $min);
			$variant = mt_rand($min, max($min, $max));
		}elseif(Utils::getRandomFloat() < BehaviorEntity::toFloat($mutation["variant"] ?? null, 0.0) && $deny !== null){
			$min = (int) BehaviorEntity::toFloat($deny["min_variant"] ?? null, 0.0);
			$max = (int) BehaviorEntity::toFloat($deny["max_variant"] ?? null, (float) $min);
			$variant = mt_rand($min, max($min, $max));
		}
		if(is_int($variant)){
			$baby->setData("variant", $variant);
		}
		$markParent = mt_rand(0, 1) === 0 ? $this->entity : $partner;
		$mark = $markParent->getData("mark_variant");
		if(is_int($mark) && Utils::getRandomFloat() >= BehaviorEntity::toFloat($mutation["extra_variant"] ?? null, 0.0)){
			$baby->setData("mark_variant", $mark);
		}
		$colorParent = mt_rand(0, 1) === 0 ? $this->entity : $partner;
		$color = $colorParent->getData("color");
		if(is_int($color) && Utils::getRandomFloat() >= BehaviorEntity::toFloat($mutation["color"] ?? null, 0.0)){
			$baby->setData("color", $color);
		}
	}

	private function blendAttributes(BehaviorEntity $baby, BehaviorEntity $partner) : void{
		$health = (int) (($this->entity->getMaxHealth() + $partner->getMaxHealth()) / 2);
		if($this->entity->getIdentifier() === $baby->getIdentifier() && $health > 0){
			$baby->setMaxHealth($health);
			$baby->setHealth((float) $health);
		}
		$speed = ($this->entity->getMovementSpeed() + $partner->getMovementSpeed()) / 2;
		if($this->entity->getIdentifier() === $baby->getIdentifier() && $speed > 0){
			$baby->setMovementSpeed($speed);
		}
	}
}
