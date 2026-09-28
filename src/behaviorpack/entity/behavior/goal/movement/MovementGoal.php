<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\movement;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\Goal;
use pocketmine\block\Block;
use pocketmine\block\Water;
use pocketmine\math\Vector3;
use pocketmine\utils\Utils;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use function floor;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function mt_rand;
use function str_contains;

/**
 * Shared helpers of the movement goals: configuration readers, random
 * positions and block tests.
 */
abstract class MovementGoal extends Goal{

	protected function num(string $key, float $default) : float{
		return BehaviorEntity::toFloat($this->config[$key] ?? null, $default);
	}

	protected function int(string $key, int $default) : int{
		$value = $this->config[$key] ?? null;
		return is_int($value) ? $value : (int) BehaviorEntity::toFloat($value, $default);
	}

	protected function bool(string $key, bool $default) : bool{
		$value = $this->config[$key] ?? null;
		return is_bool($value) ? $value : $default;
	}

	protected function range(string $key, float $default) : float{
		return BehaviorEntity::rangeValue($this->config[$key] ?? null, $default);
	}

	protected function speed() : float{
		return $this->num("speed_multiplier", 1.0);
	}

	protected function isSitting() : bool{
		return $this->entity->getData("sitting", false) === true;
	}

	protected function chance(int $interval) : bool{
		return $interval <= 1 || mt_rand(0, $interval - 1) === 0;
	}

	protected function randomFloat(float $min, float $max) : float{
		return $min + Utils::getRandomFloat() * ($max - $min);
	}

	protected function blockAt(float $x, float $y, float $z) : Block{
		return $this->entity->getWorld()->getBlockAt((int) floor($x), (int) floor($y), (int) floor($z));
	}

	protected function isWaterAt(float $x, float $y, float $z) : bool{
		return $this->blockAt($x, $y, $z) instanceof Water;
	}

	protected function isPassableAt(float $x, float $y, float $z) : bool{
		$block = $this->blockAt($x, $y, $z);
		return !$block->isSolid() && !$block instanceof Water;
	}

	/**
	 * Returns whether an entity can stand at the block position: solid below,
	 * two free blocks above.
	 */
	protected function isStandable(int $x, int $y, int $z) : bool{
		return $this->blockAt($x, $y - 1, $z)->isSolid()
			&& $this->isPassableAt($x, $y, $z)
			&& $this->isPassableAt($x, $y + 1, $z);
	}

	/**
	 * Finds a standable land position around the entity, or null.
	 */
	protected function randomLandPosition(int $xz, int $y) : ?Vector3{
		$origin = $this->entity->getPosition();
		for($i = 0; $i < 10; ++$i){
			$x = (int) floor($origin->x) + mt_rand(-$xz, $xz);
			$z = (int) floor($origin->z) + mt_rand(-$xz, $xz);
			$by = (int) floor($origin->y) + mt_rand(-$y, $y);
			for($dy = 0; $dy <= $y * 2; ++$dy){
				$cy = $by - $dy;
				if($this->isStandable($x, $cy, $z)){
					return new Vector3($x + 0.5, $cy, $z + 0.5);
				}
			}
		}
		return null;
	}

	/**
	 * Finds a water position around the entity, or null.
	 */
	protected function randomWaterPosition(int $xz, int $y, bool $avoidSurface) : ?Vector3{
		$origin = $this->entity->getPosition();
		for($i = 0; $i < 10; ++$i){
			$x = (int) floor($origin->x) + mt_rand(-$xz, $xz);
			$z = (int) floor($origin->z) + mt_rand(-$xz, $xz);
			$by = (int) floor($origin->y) + mt_rand(-$y, $y);
			if(!$this->isWaterAt($x, $by, $z)){
				continue;
			}
			if($avoidSurface && !$this->isWaterAt($x, $by + 1, $z)){
				continue;
			}
			return new Vector3($x + 0.5, $by + 0.5, $z + 0.5);
		}
		return null;
	}

	public static function blockName(Block $block) : string{
		try{
			return GlobalBlockStateHandlers::getSerializer()->serializeBlock($block)->getName();
		}catch(\Throwable){
			return "";
		}
	}

	/**
	 * Returns whether a block matches one of the block descriptors of a list.
	 */
	protected static function matchesBlockList(Block $block, mixed $list) : bool{
		if(!is_array($list)){
			return false;
		}
		$name = self::blockName($block);
		foreach($list as $entry){
			$wanted = is_string($entry) ? $entry : (is_array($entry) && is_string($entry["name"] ?? null) ? $entry["name"] : null);
			if($wanted === null){
				continue;
			}
			if(!str_contains($wanted, ":")){
				$wanted = "minecraft:" . $wanted;
			}
			if($wanted === $name){
				return true;
			}
		}
		return false;
	}
}
