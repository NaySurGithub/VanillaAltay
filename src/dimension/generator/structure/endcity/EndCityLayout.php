<?php

declare(strict_types=1);

namespace dimension\generator\structure\endcity;

use dimension\generator\random\RandomSource;
use dimension\generator\structure\BoundingBox;
use dimension\generator\structure\StructureBuffer;
use dimension\generator\structure\template\Rotation;
use dimension\generator\structure\template\StateBlocks;
use dimension\generator\structure\template\StructureTemplate;
use dimension\generator\structure\template\StructureTemplates;
use pocketmine\math\Facing;
use pocketmine\nbt\tag\IntTag;
use function array_merge;

/**
 * Lays out an end city: a base house topped by towers, which branch into
 * bridges leading to fat towers, further houses and at most one ship.
 * Branches that would collide with an earlier branch are dropped.
 */
final class EndCityLayout{

	private const MAX_GEN_DEPTH = 8;
	private const HOUSE_TOWER = 0;
	private const TOWER = 1;
	private const TOWER_BRIDGE = 2;
	private const FAT_TOWER = 3;

	private const TOWER_BRIDGES = [
		[Rotation::NONE, 1, -1, 0],
		[Rotation::ROTATE_90, 6, -1, 1],
		[Rotation::ROTATE_270, 0, -1, 5],
		[Rotation::ROTATE_180, 5, -1, 6]
	];
	private const FAT_TOWER_BRIDGES = [
		[Rotation::NONE, 4, -1, 0],
		[Rotation::ROTATE_90, 12, -1, 4],
		[Rotation::ROTATE_270, 0, -1, 8],
		[Rotation::ROTATE_180, 8, -1, 12]
	];

	private bool $shipCreated = false;
	private bool $missingTemplate = false;
	/** @var array<string, StructureTemplate|null> */
	private array $templates = [];

	public function __construct(
		private string $resourceFolder
	){}

	/**
	 * Plans the city into $buffer. Structure blocks marking shulker spots
	 * become air. Returns false, leaving $buffer untouched, when a template
	 * is missing.
	 */
	public function place(StructureBuffer $buffer, int $x, int $y, int $z, int $rotation, RandomSource $random) : bool{
		$pieces = $this->generate($x, $y, $z, $rotation, $random);
		if($pieces === null){
			return false;
		}
		foreach($pieces as $piece){
			$piece->place($buffer);
		}
		$air = StateBlocks::state(StructureTemplate::AIR);
		foreach($buffer->all() as [$blockX, $blockY, $blockZ, $state]){
			if($state->getName() === "minecraft:structure_block"){
				if($air === null){
					$buffer->remove($blockX, $blockY, $blockZ);
				}else{
					$buffer->set($blockX, $blockY, $blockZ, $air);
				}
			}
		}
		return true;
	}

	/**
	 * @return list<EndCityPiece>|null
	 */
	public function generate(int $x, int $y, int $z, int $rotation, RandomSource $random) : ?array{
		$this->shipCreated = false;
		$this->missingTemplate = false;
		$pieces = [];
		$base = $this->createPiece("base_floor", $x, $y, $z, $rotation, true);
		if($base === null){
			return null;
		}
		try{
			$lastPiece = $this->addHelper($pieces, $base);
			$lastPiece = $this->addHelper($pieces, $this->addPiece($lastPiece, -1, 0, -1, "second_floor_1", $rotation, false));
			$lastPiece = $this->addHelper($pieces, $this->addPiece($lastPiece, -1, 4, -1, "third_floor_1", $rotation, false));
			$lastPiece = $this->addHelper($pieces, $this->addPiece($lastPiece, -1, 8, -1, "third_roof", $rotation, true));
		}catch(\RuntimeException){
			return null;
		}
		$this->recursiveChildren(self::TOWER, 1, $lastPiece, null, $pieces, $random);
		return $this->missingTemplate ? null : $pieces;
	}

	private function template(string $name) : ?StructureTemplate{
		if(!isset($this->templates[$name])){
			$template = StructureTemplates::get($this->resourceFolder, "end_city/" . $name);
			if($template !== null && $name === "ship"){
				$frame = StateBlocks::state("minecraft:frame", ["facing_direction" => new IntTag(Facing::SOUTH)]);
				if($frame !== null){
					$template = $template->withBlock(6, 5, 7, $frame);
				}
			}
			$this->templates[$name] = $template;
		}
		return $this->templates[$name];
	}

	private function createPiece(string $name, int $x, int $y, int $z, int $rotation, bool $overwrite) : ?EndCityPiece{
		$template = $this->template($name);
		if($template === null){
			$this->missingTemplate = true;
			return null;
		}
		return new EndCityPiece($name, $template, $x, $y, $z, $rotation, $overwrite);
	}

	/**
	 * @param list<EndCityPiece> $pieces
	 */
	private function addHelper(array &$pieces, ?EndCityPiece $piece) : EndCityPiece{
		if($piece === null){
			throw new \RuntimeException("Missing end city template");
		}
		$pieces[] = $piece;
		return $piece;
	}

	private function addPiece(EndCityPiece $parent, int $offsetX, int $offsetY, int $offsetZ, string $templateName, int $rotation, bool $overwrite) : ?EndCityPiece{
		$child = $this->createPiece($templateName, $parent->getX(), $parent->getY(), $parent->getZ(), $rotation, $overwrite);
		if($child === null){
			return null;
		}
		[$parentX, $parentY, $parentZ] = self::transform($offsetX, $offsetY, $offsetZ, $parent->getSizeX(), $parent->getSizeZ(), $parent->getRotation());
		[$childX, $childY, $childZ] = self::transform(0, 0, 0, $child->getSizeX(), $child->getSizeZ(), $child->getRotation());
		$originX = $parent->getX() + $parentX - $childX;
		$originY = $parent->getY() + $parentY - $childY;
		$originZ = $parent->getZ() + $parentZ - $childZ;
		$child->move($originX - $child->getX(), $originY - $child->getY(), $originZ - $child->getZ());
		return $child;
	}

	/**
	 * @return array{int, int, int}
	 */
	private static function transform(int $x, int $y, int $z, int $sizeX, int $sizeZ, int $rotation) : array{
		return match($rotation){
			Rotation::ROTATE_90 => [$sizeZ - 1 - $z, $y, $x],
			Rotation::ROTATE_180 => [$sizeX - 1 - $x, $y, $sizeZ - 1 - $z],
			Rotation::ROTATE_270 => [$z, $y, $sizeX - 1 - $x],
			default => [$x, $y, $z]
		};
	}

	/**
	 * @param array{int, int, int}|null $offset
	 * @param list<EndCityPiece>        $pieces
	 */
	private function recursiveChildren(int $generator, int $genDepth, EndCityPiece $parent, ?array $offset, array &$pieces, RandomSource $random) : bool{
		if($genDepth > self::MAX_GEN_DEPTH){
			return false;
		}
		$childPieces = [];
		try{
			$generated = $this->generateSection($generator, $genDepth, $parent, $offset, $childPieces, $random);
		}catch(\RuntimeException){
			$this->missingTemplate = true;
			return false;
		}
		if($generated){
			$collision = false;
			$childTag = $random->nextInt();
			foreach($childPieces as $child){
				$child->setGenDepth($childTag);
				$collisionPiece = self::findCollisionPiece($pieces, $child->getBoundingBox());
				if($collisionPiece !== null && $collisionPiece->getGenDepth() !== $parent->getGenDepth()){
					$collision = true;
					break;
				}
			}
			if(!$collision){
				$pieces = array_merge($pieces, $childPieces);
				return true;
			}
		}
		return false;
	}

	/**
	 * @param list<EndCityPiece> $pieces
	 */
	private static function findCollisionPiece(array $pieces, BoundingBox $boundingBox) : ?EndCityPiece{
		foreach($pieces as $piece){
			if($piece->getBoundingBox()->intersects($boundingBox)){
				return $piece;
			}
		}
		return null;
	}

	/**
	 * @param array{int, int, int}|null $offset
	 * @param list<EndCityPiece>        $pieces
	 */
	private function generateSection(int $generator, int $genDepth, EndCityPiece $parent, ?array $offset, array &$pieces, RandomSource $random) : bool{
		return match($generator){
			self::HOUSE_TOWER => $this->houseTower($genDepth, $parent, $offset ?? [0, 0, 0], $pieces, $random),
			self::TOWER => $this->tower($genDepth, $parent, $pieces, $random),
			self::TOWER_BRIDGE => $this->towerBridge($genDepth, $parent, $pieces, $random),
			default => $this->fatTower($genDepth, $parent, $pieces, $random)
		};
	}

	/**
	 * @param array{int, int, int} $offset
	 * @param list<EndCityPiece>   $pieces
	 */
	private function houseTower(int $genDepth, EndCityPiece $parent, array $offset, array &$pieces, RandomSource $random) : bool{
		if($genDepth > self::MAX_GEN_DEPTH){
			return false;
		}
		$rotation = $parent->getRotation();
		$lastPiece = $this->addHelper($pieces, $this->addPiece($parent, $offset[0], $offset[1], $offset[2], "base_floor", $rotation, true));
		$numFloors = $random->nextInt(3);
		if($numFloors === 0){
			$this->addHelper($pieces, $this->addPiece($lastPiece, -1, 4, -1, "base_roof", $rotation, true));
		}elseif($numFloors === 1){
			$lastPiece = $this->addHelper($pieces, $this->addPiece($lastPiece, -1, 0, -1, "second_floor_2", $rotation, false));
			$lastPiece = $this->addHelper($pieces, $this->addPiece($lastPiece, -1, 8, -1, "second_roof", $rotation, false));
			$this->recursiveChildren(self::TOWER, $genDepth + 1, $lastPiece, null, $pieces, $random);
		}else{
			$lastPiece = $this->addHelper($pieces, $this->addPiece($lastPiece, -1, 0, -1, "second_floor_2", $rotation, false));
			$lastPiece = $this->addHelper($pieces, $this->addPiece($lastPiece, -1, 4, -1, "third_floor_2", $rotation, false));
			$lastPiece = $this->addHelper($pieces, $this->addPiece($lastPiece, -1, 8, -1, "third_roof", $rotation, true));
			$this->recursiveChildren(self::TOWER, $genDepth + 1, $lastPiece, null, $pieces, $random);
		}
		return true;
	}

	/**
	 * @param list<EndCityPiece> $pieces
	 */
	private function tower(int $genDepth, EndCityPiece $parent, array &$pieces, RandomSource $random) : bool{
		$rotation = $parent->getRotation();
		$offsetX = 3 + $random->nextInt(2);
		$offsetZ = 3 + $random->nextInt(2);
		$lastPiece = $this->addHelper($pieces, $this->addPiece($parent, $offsetX, -3, $offsetZ, "tower_base", $rotation, true));
		$lastPiece = $this->addHelper($pieces, $this->addPiece($lastPiece, 0, 7, 0, "tower_piece", $rotation, true));
		$bridgePiece = $random->nextInt(3) === 0 ? $lastPiece : null;
		$towerHeight = 1 + $random->nextInt(3);

		for($i = 0; $i < $towerHeight; ++$i){
			$lastPiece = $this->addHelper($pieces, $this->addPiece($lastPiece, 0, 4, 0, "tower_piece", $rotation, true));
			if($i < $towerHeight - 1 && $random->nextBoolean()){
				$bridgePiece = $lastPiece;
			}
		}

		if($bridgePiece !== null){
			foreach(self::TOWER_BRIDGES as [$bridgeRotation, $bridgeX, $bridgeY, $bridgeZ]){
				if($random->nextBoolean()){
					$bridgeStart = $this->addHelper($pieces, $this->addPiece($bridgePiece, $bridgeX, $bridgeY, $bridgeZ, "bridge_end", Rotation::add($rotation, $bridgeRotation), true));
					$this->recursiveChildren(self::TOWER_BRIDGE, $genDepth + 1, $bridgeStart, null, $pieces, $random);
				}
			}
			$this->addHelper($pieces, $this->addPiece($lastPiece, -1, 4, -1, "tower_top", $rotation, true));
		}else{
			if($genDepth !== 7){
				return $this->recursiveChildren(self::FAT_TOWER, $genDepth + 1, $lastPiece, null, $pieces, $random);
			}
			$this->addHelper($pieces, $this->addPiece($lastPiece, -1, 4, -1, "tower_top", $rotation, true));
		}
		return true;
	}

	/**
	 * @param list<EndCityPiece> $pieces
	 */
	private function towerBridge(int $genDepth, EndCityPiece $parent, array &$pieces, RandomSource $random) : bool{
		$rotation = $parent->getRotation();
		$bridgeLength = $random->nextInt(4) + 1;
		$lastPiece = $this->addHelper($pieces, $this->addPiece($parent, 0, 0, -4, "bridge_piece", $rotation, true));
		$lastPiece->setGenDepth(-1);
		$nextY = 0;

		for($i = 0; $i < $bridgeLength; ++$i){
			if($random->nextBoolean()){
				$lastPiece = $this->addHelper($pieces, $this->addPiece($lastPiece, 0, $nextY, -4, "bridge_piece", $rotation, true));
				$nextY = 0;
			}else{
				if($random->nextBoolean()){
					$lastPiece = $this->addHelper($pieces, $this->addPiece($lastPiece, 0, $nextY, -4, "bridge_steep_stairs", $rotation, true));
				}else{
					$lastPiece = $this->addHelper($pieces, $this->addPiece($lastPiece, 0, $nextY, -8, "bridge_gentle_stairs", $rotation, true));
				}
				$nextY = 4;
			}
		}

		if(!$this->shipCreated && $random->nextInt(10 - $genDepth) === 0){
			$shipX = -8 + $random->nextInt(8);
			$shipZ = -70 + $random->nextInt(10);
			$this->addHelper($pieces, $this->addPiece($lastPiece, $shipX, $nextY, $shipZ, "ship", $rotation, true));
			$this->shipCreated = true;
		}elseif(!$this->recursiveChildren(self::HOUSE_TOWER, $genDepth + 1, $lastPiece, [-3, $nextY + 1, -11], $pieces, $random)){
			return false;
		}

		$lastPiece = $this->addHelper($pieces, $this->addPiece($lastPiece, 4, $nextY, 0, "bridge_end", Rotation::add($rotation, Rotation::ROTATE_180), true));
		$lastPiece->setGenDepth(-1);
		return true;
	}

	/**
	 * @param list<EndCityPiece> $pieces
	 */
	private function fatTower(int $genDepth, EndCityPiece $parent, array &$pieces, RandomSource $random) : bool{
		$rotation = $parent->getRotation();
		$lastPiece = $this->addHelper($pieces, $this->addPiece($parent, -3, 4, -3, "fat_tower_base", $rotation, true));
		$lastPiece = $this->addHelper($pieces, $this->addPiece($lastPiece, 0, 4, 0, "fat_tower_middle", $rotation, true));

		for($i = 0; $i < 2 && $random->nextInt(3) !== 0; ++$i){
			$lastPiece = $this->addHelper($pieces, $this->addPiece($lastPiece, 0, 8, 0, "fat_tower_middle", $rotation, true));
			foreach(self::FAT_TOWER_BRIDGES as [$bridgeRotation, $bridgeX, $bridgeY, $bridgeZ]){
				if($random->nextBoolean()){
					$bridgeStart = $this->addHelper($pieces, $this->addPiece($lastPiece, $bridgeX, $bridgeY, $bridgeZ, "bridge_end", Rotation::add($rotation, $bridgeRotation), true));
					$this->recursiveChildren(self::TOWER_BRIDGE, $genDepth + 1, $bridgeStart, null, $pieces, $random);
				}
			}
		}

		$this->addHelper($pieces, $this->addPiece($lastPiece, -2, 8, -2, "fat_tower_top", $rotation, true));
		return true;
	}
}
