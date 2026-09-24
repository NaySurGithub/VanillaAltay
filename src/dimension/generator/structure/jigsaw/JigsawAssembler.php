<?php

declare(strict_types=1);

namespace dimension\generator\structure\jigsaw;

use dimension\generator\random\RandomSource;
use dimension\generator\structure\BoundingBox;
use dimension\generator\structure\StructureBuffer;
use dimension\generator\structure\template\JigsawConnector;
use dimension\generator\structure\template\Rotation;
use dimension\generator\structure\template\StateBlocks;
use dimension\generator\structure\template\StructureTemplate;
use dimension\generator\structure\template\StructureTemplates;
use pocketmine\math\Facing;
use function array_shift;
use function array_splice;
use function count;
use function strpos;
use function substr;
use function trim;
use function usort;

/**
 * Assembles a structure from template pools: a start piece is drawn from
 * the entry pool, then every jigsaw block of every placed piece pulls a
 * matching piece from its pool, breadth first by placement priority, until
 * the maximum depth is reached. Jigsaw blocks are then replaced by their
 * final state.
 */
abstract class JigsawAssembler{

	private const AIR = "minecraft:air";
	private const JIGSAW = "minecraft:jigsaw";
	private const STRUCTURE_VOID = "minecraft:structure_void";

	private string $resourceFolder = "";
	private int $originX = 0;
	private int $originY = 0;
	private int $originZ = 0;

	/**
	 * @return array<string, StructurePool>
	 */
	abstract protected function getPools() : array;

	abstract protected function getEntryPool() : string;

	abstract protected function getMaxDepth() : int;

	protected function strictlyIntersects(BoundingBox $first, BoundingBox $second) : bool{
		return $first->x1 >= $second->x0 && $first->x0 <= $second->x1
			&& $first->y1 >= $second->y0 && $first->y0 <= $second->y1
			&& $first->z1 >= $second->z0 && $first->z0 <= $second->z1;
	}

	/**
	 * Plans the whole structure into $buffer, the start piece having its
	 * minimum corner at the origin.
	 */
	final public function assemble(StructureBuffer $buffer, int $originX, int $originY, int $originZ, string $resourceFolder, RandomSource $random) : void{
		$this->resourceFolder = $resourceFolder;
		$this->originX = $originX;
		$this->originY = $originY;
		$this->originZ = $originZ;
		$pools = $this->getPools();

		$startPool = $pools[self::normalize($this->getEntryPool())] ?? null;
		if($startPool === null){
			return;
		}
		$rootPiece = $this->placeFromPool($this->getRandomRotation($random), $buffer, $startPool, $random);
		if($rootPiece === null){
			return;
		}

		$connected = [];
		$occupied = [$rootPiece->boundingBox];
		$pending = [[$rootPiece, 0, 0]];
		$maxDepth = $this->getMaxDepth();

		while(count($pending) > 0){
			[$piece, $depth] = array_shift($pending);
			if($depth >= $maxDepth){
				continue;
			}
			foreach($this->getOrderedJigsaws($piece->sourceJigsaws, $piece->placedJigsaws, $random) as [$sourceJigsaw, $placedJigsaw]){
				$parentKey = self::key($piece->x + $placedJigsaw->x, $piece->y + $placedJigsaw->y, $piece->z + $placedJigsaw->z);
				if(isset($connected[$parentKey])){
					continue;
				}
				$nextPool = $pools[self::normalize($sourceJigsaw->pool)] ?? null;
				if($nextPool === null){
					continue;
				}
				$candidate = $this->findCandidate($pools, $nextPool, $piece, $sourceJigsaw, $placedJigsaw, $random, $connected, $occupied);
				if($candidate === null){
					continue;
				}
				[$structureName, $childTemplate, $connection] = $candidate;
				[$childRotation, $childX, $childY, $childZ, $jigsawX, $jigsawY, $jigsawZ] = $connection;
				$childPiece = $this->placePiece($childX, $childY, $childZ, $childRotation, $buffer, $structureName, $childTemplate, $occupied, $piece->boundingBox);
				if($childPiece === null){
					continue;
				}
				$connected[$parentKey] = true;
				$connected[self::key($jigsawX, $jigsawY, $jigsawZ)] = true;
				$occupied[] = $childPiece->boundingBox;
				if($depth + 1 < $maxDepth){
					$this->insertPendingPiece($pending, [$childPiece, $depth + 1, $sourceJigsaw->placementPriority]);
				}
			}
		}
	}

	private static function key(int $x, int $y, int $z) : string{
		return $x . ":" . $y . ":" . $z;
	}

	private static function normalize(string $key) : string{
		$separator = strpos($key, ":");
		return $separator === false ? $key : substr($key, $separator + 1);
	}

	private static function isEmptyPoolEntry(string $key) : bool{
		$normalized = self::normalize($key);
		return $normalized === "" || $normalized === "empty";
	}

	private function template(string $name) : ?StructureTemplate{
		return StructureTemplates::get($this->resourceFolder, $name);
	}

	private function placeFromPool(int $rotation, StructureBuffer $buffer, StructurePool $pool, RandomSource $random) : ?JigsawPiece{
		$entry = $pool->getRandomEntry($random);
		if(self::isEmptyPoolEntry($entry->structureName)){
			return null;
		}
		$structureName = self::normalize($entry->structureName);
		$template = $this->template($structureName);
		if($template === null){
			return null;
		}
		return $this->placePiece(0, 0, 0, $rotation, $buffer, $structureName, $template, null, null);
	}

	/**
	 * @param list<BoundingBox>|null $occupied
	 */
	private function placePiece(int $x, int $y, int $z, int $rotation, StructureBuffer $buffer, string $structureName, StructureTemplate $template, ?array $occupied, ?BoundingBox $ignored) : ?JigsawPiece{
		$placed = $rotation === Rotation::NONE ? $template : $template->rotateGrid($rotation);
		$box = self::createBoundingBox($x, $y, $z, $placed);
		if($occupied !== null && $this->overlapsExistingBox($occupied, $box, $ignored)){
			return null;
		}
		$worldX = $this->originX + $x;
		$worldY = $this->originY + $y;
		$worldZ = $this->originZ + $z;
		$volume = $placed->getVolume();
		for($index = 0; $index < $volume; ++$index){
			$state = $placed->stateAt($index);
			if($state === null){
				continue;
			}
			$buffer->set($worldX + $placed->xOf($index), $worldY + $placed->yOf($index), $worldZ + $placed->zOf($index), $state);
		}
		foreach($placed->getJigsaws() as $jigsaw){
			$finalState = $jigsaw->finalState;
			if($finalState !== null && $finalState->getName() === self::STRUCTURE_VOID){
				continue;
			}
			if($finalState === null || $finalState->getName() === self::JIGSAW){
				$finalState = StateBlocks::state(self::AIR);
				if($finalState === null){
					continue;
				}
			}
			$buffer->set($worldX + $jigsaw->x, $worldY + $jigsaw->y, $worldZ + $jigsaw->z, $finalState);
		}
		return new JigsawPiece($structureName, $template, $x, $y, $z, $rotation, $box, $template->getJigsaws(), $placed->getJigsaws());
	}

	/**
	 * @param array<string, StructurePool> $pools
	 * @param array<string, true>          $connected
	 * @param list<BoundingBox>            $occupied
	 *
	 * @return array{string, StructureTemplate, array{int, int, int, int, int, int, int}}|null
	 */
	private function findCandidate(array $pools, StructurePool $pool, JigsawPiece $parent, JigsawConnector $sourceJigsaw, JigsawConnector $placedJigsaw, RandomSource $random, array $connected, array $occupied) : ?array{
		foreach($this->getCandidateEntries($pools, $pool, $random) as $entry){
			if(self::isEmptyPoolEntry($entry->structureName)){
				return null;
			}
			$structureKey = self::normalize($entry->structureName);
			$childTemplate = $this->template($structureKey);
			if($childTemplate === null){
				continue;
			}
			foreach($this->getShuffledRotations($random) as $childRotation){
				$connection = $this->resolveConnection($parent, $sourceJigsaw, $placedJigsaw, $childTemplate, $childRotation, $random);
				if($connection === null){
					continue;
				}
				[$connection, $childBox] = $connection;
				if(isset($connected[self::key($connection[4], $connection[5], $connection[6])])){
					continue;
				}
				if($this->overlapsExistingBox($occupied, $childBox, $parent->boundingBox)){
					continue;
				}
				return [$structureKey, $childTemplate, $connection];
			}
		}
		return null;
	}

	/**
	 * @param array<string, StructurePool> $pools
	 *
	 * @return list<PoolEntry>
	 */
	private function getCandidateEntries(array $pools, StructurePool $pool, RandomSource $random) : array{
		$weighted = [];
		foreach($pool->entries as $entry){
			for($i = 0; $i < $entry->weight; ++$i){
				$weighted[] = $entry;
			}
		}
		if($pool->fallback !== null){
			$fallbackPool = $pools[self::normalize($pool->fallback)] ?? null;
			if($fallbackPool !== null){
				foreach($fallbackPool->entries as $entry){
					for($i = 0; $i < $entry->weight; ++$i){
						$weighted[] = $entry;
					}
				}
			}
		}
		for($i = count($weighted) - 1; $i > 0; --$i){
			$index = $random->nextBoundedInt($i);
			$value = $weighted[$i];
			$weighted[$i] = $weighted[$index];
			$weighted[$index] = $value;
		}
		return $weighted;
	}

	/**
	 * @return array{array{int, int, int, int, int, int, int}, BoundingBox}|null
	 */
	private function resolveConnection(JigsawPiece $parent, JigsawConnector $sourceJigsaw, JigsawConnector $placedJigsaw, StructureTemplate $childTemplate, int $childRotation, RandomSource $random) : ?array{
		$parentOrientation = $this->getJigsawOrientation($parent->source, $sourceJigsaw, $parent->rotation);
		if($parentOrientation === null){
			return null;
		}
		[$parentFront, $parentTop] = $parentOrientation;
		$parentJoint = $this->getJigsawJoint($sourceJigsaw, $parentFront);
		$parentWorldX = $parent->x + $placedJigsaw->x;
		$parentWorldY = $parent->y + $placedJigsaw->y;
		$parentWorldZ = $parent->z + $placedJigsaw->z;
		$rotatedChild = $childRotation === Rotation::NONE ? $childTemplate : $childTemplate->rotateGrid($childRotation);
		$target = self::normalize($sourceJigsaw->target);

		foreach($this->getOrderedJigsaws($childTemplate->getJigsaws(), $rotatedChild->getJigsaws(), $random) as [$childSource, $childPlaced]){
			if(self::normalize($childSource->name) !== $target){
				continue;
			}
			$childOrientation = $this->getJigsawOrientation($childTemplate, $childSource, $childRotation);
			if($childOrientation === null){
				continue;
			}
			[$childFront, $childTop] = $childOrientation;
			if($parentFront !== Facing::opposite($childFront)){
				continue;
			}
			if($parentJoint !== "rollable" && $parentTop !== $childTop){
				continue;
			}
			[$dx, $dy, $dz] = self::offset($parentFront);
			$childWorldX = $parentWorldX + $dx;
			$childWorldY = $parentWorldY + $dy;
			$childWorldZ = $parentWorldZ + $dz;
			$childX = $childWorldX - $childPlaced->x;
			$childY = $childWorldY - $childPlaced->y;
			$childZ = $childWorldZ - $childPlaced->z;
			return [
				[$childRotation, $childX, $childY, $childZ, $childWorldX, $childWorldY, $childWorldZ],
				self::createBoundingBox($childX, $childY, $childZ, $rotatedChild)
			];
		}
		return null;
	}

	/**
	 * Front and top faces of a jigsaw block once its template is turned by
	 * $appliedRotation, or null when the position holds no jigsaw block.
	 *
	 * @return array{int, int}|null
	 */
	private function getJigsawOrientation(StructureTemplate $template, JigsawConnector $jigsaw, int $appliedRotation) : ?array{
		$state = $template->stateAt($template->index($jigsaw->x, $jigsaw->y, $jigsaw->z));
		if($state === null || $state->getName() !== self::JIGSAW){
			return null;
		}
		$facing = $state->getState("facing_direction")?->getValue();
		$front = self::rotateFace((int) ($facing ?? 0) & 0x7, $appliedRotation);
		if(self::isHorizontal($front)){
			return [$front, Facing::UP];
		}
		$blockRotation = (int) ($state->getState("rotation")?->getValue() ?? 0);
		if($front === Facing::DOWN){
			$top = match($blockRotation){
				1 => Facing::WEST,
				2 => Facing::SOUTH,
				3 => Facing::EAST,
				default => Facing::NORTH
			};
		}else{
			$top = match($blockRotation){
				1 => Facing::EAST,
				2 => Facing::SOUTH,
				3 => Facing::WEST,
				default => Facing::NORTH
			};
		}
		return [$front, self::rotateFace($top, $appliedRotation)];
	}

	private function getJigsawJoint(JigsawConnector $jigsaw, int $front) : string{
		if(trim($jigsaw->joint) !== ""){
			return $jigsaw->joint;
		}
		return self::isHorizontal($front) ? "aligned" : "rollable";
	}

	private static function isHorizontal(int $face) : bool{
		return $face === Facing::NORTH || $face === Facing::SOUTH || $face === Facing::EAST || $face === Facing::WEST;
	}

	/**
	 * A grid turn by one step moves faces a quarter turn counterclockwise.
	 */
	private static function rotateFace(int $face, int $rotation) : int{
		return match($rotation){
			Rotation::ROTATE_90 => self::rotateCounterClockwise($face),
			Rotation::ROTATE_180 => self::rotateClockwise(self::rotateClockwise($face)),
			Rotation::ROTATE_270 => self::rotateClockwise($face),
			default => $face
		};
	}

	private static function rotateClockwise(int $face) : int{
		return match($face){
			Facing::NORTH => Facing::EAST,
			Facing::EAST => Facing::SOUTH,
			Facing::SOUTH => Facing::WEST,
			Facing::WEST => Facing::NORTH,
			default => $face
		};
	}

	private static function rotateCounterClockwise(int $face) : int{
		return match($face){
			Facing::NORTH => Facing::WEST,
			Facing::WEST => Facing::SOUTH,
			Facing::SOUTH => Facing::EAST,
			Facing::EAST => Facing::NORTH,
			default => $face
		};
	}

	/**
	 * @return array{int, int, int}
	 */
	private static function offset(int $face) : array{
		return match($face){
			Facing::DOWN => [0, -1, 0],
			Facing::UP => [0, 1, 0],
			Facing::NORTH => [0, 0, -1],
			Facing::SOUTH => [0, 0, 1],
			Facing::WEST => [-1, 0, 0],
			default => [1, 0, 0]
		};
	}

	/**
	 * @return list<int>
	 */
	private function getShuffledRotations(RandomSource $random) : array{
		$rotations = [Rotation::NONE, Rotation::ROTATE_90, Rotation::ROTATE_180, Rotation::ROTATE_270];
		for($i = count($rotations) - 1; $i > 0; --$i){
			$index = $random->nextBoundedInt($i);
			$value = $rotations[$i];
			$rotations[$i] = $rotations[$index];
			$rotations[$index] = $value;
		}
		return $rotations;
	}

	private function getRandomRotation(RandomSource $random) : int{
		$rotations = [Rotation::NONE, Rotation::ROTATE_90, Rotation::ROTATE_180, Rotation::ROTATE_270];
		return $rotations[$random->nextBoundedInt(count($rotations) - 1)];
	}

	/**
	 * Jigsaws in random order, then stably sorted by descending selection
	 * priority, each paired with its rotated counterpart.
	 *
	 * @param list<JigsawConnector> $sourceJigsaws
	 * @param list<JigsawConnector> $placedJigsaws
	 *
	 * @return list<array{JigsawConnector, JigsawConnector}>
	 */
	private function getOrderedJigsaws(array $sourceJigsaws, array $placedJigsaws, RandomSource $random) : array{
		$indices = [];
		for($i = 0, $count = count($sourceJigsaws); $i < $count; ++$i){
			$indices[] = $i;
		}
		for($i = count($indices) - 1; $i > 0; --$i){
			$index = $random->nextBoundedInt($i);
			$value = $indices[$i];
			$indices[$i] = $indices[$index];
			$indices[$index] = $value;
		}
		usort($indices, static function(int $a, int $b) use ($sourceJigsaws) : int{
			return $sourceJigsaws[$b]->selectionPriority <=> $sourceJigsaws[$a]->selectionPriority;
		});
		$pairs = [];
		foreach($indices as $index){
			$pairs[] = [$sourceJigsaws[$index], $placedJigsaws[$index]];
		}
		return $pairs;
	}

	/**
	 * @param list<array{JigsawPiece, int, int}> $queue
	 * @param array{JigsawPiece, int, int}       $pending
	 */
	private function insertPendingPiece(array &$queue, array $pending) : void{
		$index = count($queue);
		foreach($queue as $i => $queued){
			if($pending[2] > $queued[2]){
				$index = $i;
				break;
			}
		}
		array_splice($queue, $index, 0, [$pending]);
	}

	/**
	 * @param list<BoundingBox> $occupied
	 */
	private function overlapsExistingBox(array $occupied, BoundingBox $box, ?BoundingBox $ignored) : bool{
		foreach($occupied as $occupiedBox){
			if($occupiedBox === $ignored){
				continue;
			}
			if($this->strictlyIntersects($occupiedBox, $box)){
				return true;
			}
		}
		return false;
	}

	private static function createBoundingBox(int $x, int $y, int $z, StructureTemplate $template) : BoundingBox{
		return new BoundingBox($x, $y, $z, $x + $template->getSizeX() - 1, $y + $template->getSizeY() - 1, $z + $template->getSizeZ() - 1);
	}
}
