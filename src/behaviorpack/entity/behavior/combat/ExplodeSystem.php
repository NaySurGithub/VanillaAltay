<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\combat;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\item\FlintSteel;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\Explosion;
use pocketmine\world\Position;
use pocketmine\world\sound\IgniteSound;
use function is_int;
use function max;
use function min;

/**
 * minecraft:explode: lights a fuse and explodes the entity when it burns
 * out. The remaining fuse ticks are stored as "explode_fuse" (absent while
 * the fuse is not lit).
 */
final class ExplodeSystem extends EntitySystem{

	private const KEY = "explode_fuse";

	public function onAdd() : void{
		if($this->entity->getData(self::KEY) === null && ($this->config["fuse_lit"] ?? false) === true){
			$this->light();
		}
		$this->syncFuse();
	}

	public function onRemove() : void{
		$this->entity->setData(self::KEY, null);
		$this->entity->setFlag(EntityMetadataFlags::IGNITED, false);
	}

	/**
	 * Lights the fuse, if it is not already burning.
	 */
	public function light() : void{
		if(is_int($this->entity->getData(self::KEY))){
			return;
		}
		$ticks = max(0, (int) (BehaviorEntity::rangeValue($this->config["fuse_length"] ?? null, 3.0) * 20));
		$this->entity->setData(self::KEY, $ticks);
		$this->entity->setMetadata(EntityMetadataProperties::FUSE_LENGTH, "int", $ticks);
		$this->syncFuse();
	}

	public function isLit() : bool{
		return is_int($this->entity->getData(self::KEY));
	}

	private function syncFuse() : void{
		$this->entity->setFlag(EntityMetadataFlags::IGNITED, $this->isLit());
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();
		if($this->isLit() || !$item instanceof FlintSteel){
			return false;
		}
		if(!$player->isCreative()){
			$item->applyDamage(1);
			$player->getInventory()->setItemInHand($item);
		}
		$this->entity->getWorld()->addSound($this->entity->getPosition(), new IgniteSound());
		$this->light();
		return true;
	}

	public function tick(int $tickDiff) : void{
		$fuse = $this->entity->getData(self::KEY);
		if(!is_int($fuse)){
			return;
		}
		$fuse -= $tickDiff;
		if($fuse > 0){
			$this->entity->setData(self::KEY, $fuse);
			return;
		}
		$this->explode();
	}

	/**
	 * Explodes now and removes the entity.
	 */
	public function explode() : void{
		$entity = $this->entity;
		$entity->setData(self::KEY, null);
		$power = BehaviorEntity::toFloat($this->config["power"] ?? null, 3.0);
		if($power > 0){
			$position = Position::fromObject($entity->getPosition()->add(0, $entity->size->getHeight() / 2, 0), $entity->getWorld());
			$fireChance = ($this->config["causes_fire"] ?? false) === true ? 1 / 3 : 0.0;
			$explosion = new Explosion($position, $power, $entity, $fireChance);
			$breaks = ($this->config["breaks_blocks"] ?? true) !== false;
			if($breaks && $entity->isInWater() && ($this->config["allow_underwater"] ?? false) !== true){
				$breaks = false;
			}
			if($breaks){
				$explosion->explodeA();
			}
			$explosion->explodeB();
		}
		$entity->flagForDespawn();
	}

	/**
	 * Returns the fuse progress between 0 and 1, for renderers and goals.
	 */
	public function getFuseProgress() : float{
		$fuse = $this->entity->getData(self::KEY);
		if(!is_int($fuse)){
			return 0.0;
		}
		$length = max(1.0, BehaviorEntity::rangeValue($this->config["fuse_length"] ?? null, 3.0) * 20);
		return min(1.0, max(0.0, 1 - $fuse / $length));
	}
}
