<?php

declare(strict_types=1);

namespace dimension\portal;

use pocketmine\block\Air;
use pocketmine\block\BaseFire;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\NetherPortal;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Axis;
use pocketmine\world\World;
use function max;

/**
 * Detection of an obsidian nether portal frame around an empty position and
 * the checks that keep a lit portal standing.
 */
final class NetherPortalFrame{

	public const MIN_WIDTH = 2;
	public const MAX_WIDTH = 21;
	public const MIN_HEIGHT = 3;
	public const MAX_HEIGHT = 21;

	private function __construct(
		private World $world,
		private int $axis,
		private int $x,
		private int $y,
		private int $z,
		private int $width,
		private int $height
	){
	}

	/**
	 * Finds a complete frame enclosing the given position, trying the X axis
	 * first and then the Z axis.
	 */
	public static function find(World $world, int $x, int $y, int $z) : ?self{
		foreach([Axis::X, Axis::Z] as $axis){
			$frame = self::findOnAxis($world, $x, $y, $z, $axis);
			if($frame !== null){
				return $frame;
			}
		}
		return null;
	}

	private static function findOnAxis(World $world, int $x, int $y, int $z, int $axis) : ?self{
		[$dx, $dz] = $axis === Axis::X ? [1, 0] : [0, 1];
		if(!self::isEmpty($world->getBlockAt($x, $y, $z))){
			return null;
		}

		$lowest = max($world->getMinY(), $y - self::MAX_HEIGHT);
		while($y > $lowest && self::isEmpty($world->getBlockAt($x, $y - 1, $z))){
			$y--;
		}

		$left = null;
		for($i = 0; $i <= self::MAX_WIDTH; $i++){
			$bx = $x - $dx * $i;
			$bz = $z - $dz * $i;
			$block = $world->getBlockAt($bx, $y, $bz);
			if(self::isFrame($block)){
				$left = $i - 1;
				break;
			}
			if(!self::isEmpty($block) || !self::isFrame($world->getBlockAt($bx, $y - 1, $bz))){
				return null;
			}
		}
		if($left === null){
			return null;
		}
		$startX = $x - $dx * $left;
		$startZ = $z - $dz * $left;

		$width = null;
		for($i = 0; $i <= self::MAX_WIDTH; $i++){
			$bx = $startX + $dx * $i;
			$bz = $startZ + $dz * $i;
			$block = $world->getBlockAt($bx, $y, $bz);
			if(self::isFrame($block)){
				$width = $i;
				break;
			}
			if(!self::isEmpty($block) || !self::isFrame($world->getBlockAt($bx, $y - 1, $bz))){
				return null;
			}
		}
		if($width === null || $width < self::MIN_WIDTH || $width > self::MAX_WIDTH){
			return null;
		}

		$height = self::MAX_HEIGHT;
		for($h = 0; $h < self::MAX_HEIGHT; $h++){
			$by = $y + $h;
			if(
				!self::isFrame($world->getBlockAt($startX - $dx, $by, $startZ - $dz)) ||
				!self::isFrame($world->getBlockAt($startX + $dx * $width, $by, $startZ + $dz * $width))
			){
				$height = $h;
				break;
			}
			$complete = true;
			for($i = 0; $i < $width; $i++){
				if(!self::isEmpty($world->getBlockAt($startX + $dx * $i, $by, $startZ + $dz * $i))){
					$complete = false;
					break;
				}
			}
			if(!$complete){
				$height = $h;
				break;
			}
		}
		if($height < self::MIN_HEIGHT || $height > self::MAX_HEIGHT){
			return null;
		}
		for($i = 0; $i < $width; $i++){
			if(!self::isFrame($world->getBlockAt($startX + $dx * $i, $y + $height, $startZ + $dz * $i))){
				return null;
			}
		}

		return new self($world, $axis, $startX, $y, $startZ, $width, $height);
	}

	/**
	 * Fills the inside of the frame with nether portal blocks facing the
	 * frame axis.
	 */
	public function light() : void{
		[$dx, $dz] = $this->axis === Axis::X ? [1, 0] : [0, 1];
		$portal = VanillaBlocks::NETHER_PORTAL()->setAxis($this->axis);
		for($h = 0; $h < $this->height; $h++){
			for($i = 0; $i < $this->width; $i++){
				$this->world->setBlockAt($this->x + $dx * $i, $this->y + $h, $this->z + $dz * $i, $portal);
			}
		}
	}

	/**
	 * A portal block stays while the four neighbours in its plane are portal
	 * blocks of the same axis or frame blocks.
	 */
	public static function isPortalBlockSupported(World $world, NetherPortal $portal, int $x, int $y, int $z) : bool{
		[$dx, $dz] = $portal->getAxis() === Axis::X ? [1, 0] : [0, 1];
		foreach([[$dx, 0, $dz], [-$dx, 0, -$dz], [0, 1, 0], [0, -1, 0]] as [$ox, $oy, $oz]){
			$side = $world->getBlockAt($x + $ox, $y + $oy, $z + $oz);
			if(self::isFrame($side)){
				continue;
			}
			if($side instanceof NetherPortal && $side->getAxis() === $portal->getAxis()){
				continue;
			}
			return false;
		}
		return true;
	}

	public static function isFrame(Block $block) : bool{
		return $block->getTypeId() === BlockTypeIds::OBSIDIAN;
	}

	private static function isEmpty(Block $block) : bool{
		return $block instanceof Air || $block instanceof BaseFire || $block instanceof NetherPortal;
	}
}
