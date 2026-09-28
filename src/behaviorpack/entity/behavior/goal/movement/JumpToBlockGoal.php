<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\movement;

use pocketmine\math\Vector3;
use pocketmine\utils\Utils;
use function count;
use function floor;
use function max;
use function mt_rand;
use function sqrt;

/**
 * Leaps in an arc onto a nearby block, favoring the preferred blocks.
 */
final class JumpToBlockGoal extends MovementGoal{

	private const GRAVITY = 0.08;

	private ?Vector3 $destination = null;
	private int $cooldown = 0;
	private int $phase = 0;
	private int $timer = 0;

	public function getControls() : int{
		return self::MOVE | self::JUMP;
	}

	public function canUse() : bool{
		if($this->cooldown > 0){
			--$this->cooldown;
			return false;
		}
		if($this->isSitting() || !$this->entity->isOnGround() || $this->entity->isInWater()){
			return false;
		}
		$this->destination = $this->findTarget();
		if($this->destination === null){
			$this->cooldown = 20;
		}
		return $this->destination !== null;
	}

	private function findTarget() : ?Vector3{
		$width = $this->int("search_width", 8);
		$height = $this->int("search_height", 10);
		$minDistance = $this->num("minimum_distance", 2.0);
		$minPath = $this->num("minimum_path_length", 5.0);
		$maxVelocity = $this->num("max_velocity", 1.5);
		$origin = $this->entity->getPosition();
		$ox = (int) floor($origin->x);
		$oy = (int) floor($origin->y);
		$oz = (int) floor($origin->z);
		$preferredList = $this->config["preferred_blocks"] ?? null;
		$forbiddenList = $this->config["forbidden_blocks"] ?? null;
		$preferred = [];
		$others = [];
		for($i = 0; $i < 48; ++$i){
			$x = $ox + mt_rand(-$width, $width);
			$z = $oz + mt_rand(-$width, $width);
			for($y = $oy + $height; $y >= $oy - $height; --$y){
				if(!$this->isStandable($x, $y, $z)){
					continue;
				}
				$target = new Vector3($x + 0.5, $y, $z + 0.5);
				$distance = sqrt($this->entity->horizontalDistanceSquared($target));
				if($distance < $minDistance || $target->distance($origin) < $minPath * 0.5){
					break;
				}
				$below = $this->blockAt($x, $y - 1, $z);
				if(self::matchesBlockList($below, $forbiddenList)){
					break;
				}
				if($this->launchVelocity($target)->length() > $maxVelocity){
					break;
				}
				if(self::matchesBlockList($below, $preferredList)){
					$preferred[] = $target;
				}else{
					$others[] = $target;
				}
				break;
			}
		}
		if(count($preferred) > 0 && (count($others) === 0 || Utils::getRandomFloat() < $this->num("preferred_blocks_chance", 1.0))){
			return $preferred[mt_rand(0, count($preferred) - 1)];
		}
		return count($others) > 0 ? $others[mt_rand(0, count($others) - 1)] : null;
	}

	private function launchVelocity(Vector3 $target) : Vector3{
		$origin = $this->entity->getPosition();
		$dx = $target->x - $origin->x;
		$dy = $target->y - $origin->y;
		$dz = $target->z - $origin->z;
		$apex = max($dy, 0.0) + max(0.5, $this->num("scale_factor", 0.7) * sqrt($dx * $dx + $dz * $dz) * 0.5);
		$vy = sqrt(2 * self::GRAVITY * $apex);
		$up = $vy / self::GRAVITY;
		$down = sqrt(2 * max($apex - $dy, 0.01) / self::GRAVITY);
		$time = max($up + $down, 1.0);
		return new Vector3($dx / $time, $vy, $dz / $time);
	}

	public function canContinue() : bool{
		return $this->destination !== null && $this->phase < 2;
	}

	public function start() : void{
		$this->phase = 0;
		$this->timer = 10;
		$this->entity->getNavigator()->stop();
		if($this->destination !== null){
			$this->entity->lookAt($this->destination);
		}
	}

	public function stop() : void{
		$this->destination = null;
		$this->phase = 0;
		$this->cooldown = (int) ($this->range("cooldown_range", 0.0) * 20);
	}

	public function tick(int $tickDiff) : void{
		if($this->destination === null){
			return;
		}
		if($this->phase === 0){
			$this->timer -= $tickDiff;
			if($this->timer <= 0){
				$velocity = $this->launchVelocity($this->destination);
				$max = $this->num("max_velocity", 1.5);
				$length = $velocity->length();
				if($length > $max && $length > 0){
					$velocity = $velocity->multiply($max / $length);
				}
				$this->entity->setMotion($velocity);
				$this->phase = 1;
				$this->timer = 60;
			}
			return;
		}
		$this->timer -= $tickDiff;
		if(($this->timer < 55 && $this->entity->isOnGround()) || $this->timer <= 0 || $this->entity->isInWater()){
			$this->phase = 2;
		}
	}
}
