<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\jigsaw;

use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use VanillaAltay\world\generator\structure\mineshaft\BoundingBox;
use VanillaAltay\world\generator\structure\template\JigsawBlock;
use VanillaAltay\world\generator\structure\template\StructureTemplate;
use VanillaAltay\world\generator\structure\template\TemplateArchive;
use function array_shift;
use function count;

/**
 * Assembles a structure out of templates that name each other through their connection points.
 *
 * A piece is drawn from the pool a connection point asks for, turned until one of its own points faces back at
 * the parent and carries the name the parent targeted, then kept if it does not overlap anything already
 * placed. The result is a list of positioned templates, in world coordinates relative to the origin.
 */
final class JigsawAssembler{

	private const EMPTY_POOL = "empty";

	/**
	 * @param array[] $pools template identifiers and their weights, by pool name
	 * @phpstan-param array<string, list<array{string, int}>> $pools
	 * @param BoundingBox|null $clip area the pieces have to stay inside, relative to the origin
	 *
	 * @return JigsawPiece[]
	 */
	public static function assemble(array $pools, string $entryPool, int $maxDepth, int $maxPieces, Random $random, ?BoundingBox $clip = null) : array{
		$archive = TemplateArchive::getInstance();

		$root = self::createRoot($archive, $pools[$entryPool] ?? [], $random->nextBoundedInt(4), $random, $clip);
		if($root === null){
			return [];
		}

		$pieces = [$root];
		$connected = [];
		$pending = [[$root, 0]];

		while(count($pending) > 0 && count($pieces) < $maxPieces){
			[$piece, $depth] = array_shift($pending);
			if($depth >= $maxDepth){
				continue;
			}

			foreach(self::shuffle($piece->template->getJigsaws(), $random) as $jigsaw){
				[$sourceX, $sourceZ] = $piece->template->rotateOffset($jigsaw->x, $jigsaw->z, $piece->rotation);

				$sourceWorldX = $piece->x + $sourceX;
				$sourceWorldY = $piece->y + $jigsaw->y;
				$sourceWorldZ = $piece->z + $sourceZ;

				$key = $sourceWorldX . ":" . $sourceWorldY . ":" . $sourceWorldZ;
				if(isset($connected[$key])){
					continue;
				}

				$entries = $pools[$jigsaw->pool] ?? null;
				if($entries === null){
					continue;
				}

				$child = self::connect($archive, $entries, $piece, $jigsaw, $sourceWorldX, $sourceWorldY, $sourceWorldZ, $pieces, $connected, $random, $clip);
				if($child === null){
					continue;
				}

				[$childPiece, $childKey] = $child;

				$connected[$key] = true;
				$connected[$childKey] = true;
				$pieces[] = $childPiece;

				if($depth + 1 < $maxDepth){
					$pending[] = [$childPiece, $depth + 1];
				}
			}
		}

		return $pieces;
	}

	/**
	 * @phpstan-param list<array{string, int}> $entries
	 * @param JigsawPiece[] $pieces
	 * @phpstan-param array<string, true> $connected
	 *
	 * @return mixed[]|null
	 * @phpstan-return array{JigsawPiece, string}|null
	 */
	private static function connect(
		TemplateArchive $archive,
		array $entries,
		JigsawPiece $parent,
		JigsawBlock $jigsaw,
		int $sourceWorldX,
		int $sourceWorldY,
		int $sourceWorldZ,
		array $pieces,
		array $connected,
		Random $random,
		?BoundingBox $clip
	) : ?array{
		$front = self::turn($jigsaw->facing, $parent->rotation);
		$top = self::turn($jigsaw->top, $parent->rotation);
		$back = Facing::opposite($front);

		[$stepX, $stepY, $stepZ] = self::step($front);

		$targetX = $sourceWorldX + $stepX;
		$targetY = $sourceWorldY + $stepY;
		$targetZ = $sourceWorldZ + $stepZ;

		$key = $targetX . ":" . $targetY . ":" . $targetZ;
		if(isset($connected[$key])){
			return null;
		}

		foreach(self::weighted($entries, $random) as $identifier){
			if($identifier === self::EMPTY_POOL){
				return null;
			}

			$template = $archive->get($identifier);
			if($template === null){
				continue;
			}

			foreach(self::shuffle([StructureTemplate::ROTATION_NONE, StructureTemplate::ROTATION_90, StructureTemplate::ROTATION_180, StructureTemplate::ROTATION_270], $random) as $rotation){
				foreach(self::shuffle($template->getJigsaws(), $random) as $candidate){
					if($candidate->name !== $jigsaw->target){
						continue;
					}

					if(self::turn($candidate->facing, $rotation) !== $back){
						continue;
					}

					if(!$jigsaw->rollable && self::turn($candidate->top, $rotation) !== $top){
						continue;
					}

					[$offsetX, $offsetZ] = $template->rotateOffset($candidate->x, $candidate->z, $rotation);

					$x = $targetX - $offsetX;
					$y = $targetY - $candidate->y;
					$z = $targetZ - $offsetZ;

					$box = self::boundingBox($template, $x, $y, $z, $rotation);
					if($clip !== null && !self::contains($clip, $box)){
						continue;
					}

					if(self::overlaps($pieces, $box, $parent)){
						continue;
					}

					return [new JigsawPiece($template, $x, $y, $z, $rotation, $box), $key];
				}
			}
		}

		return null;
	}

	/**
	 * @phpstan-param list<array{string, int}> $entries
	 */
	private static function createRoot(TemplateArchive $archive, array $entries, int $rotation, Random $random, ?BoundingBox $clip) : ?JigsawPiece{
		foreach(self::weighted($entries, $random) as $identifier){
			$template = $archive->get($identifier);
			if($template === null){
				continue;
			}

			$box = self::boundingBox($template, 0, 0, 0, $rotation);
			if($clip !== null && !self::contains($clip, $box)){
				continue;
			}

			return new JigsawPiece($template, 0, 0, 0, $rotation, $box);
		}

		return null;
	}

	private static function boundingBox(StructureTemplate $template, int $x, int $y, int $z, int $rotation) : BoundingBox{
		[$sizeX, $sizeZ] = $template->getRotatedSize($rotation);

		return new BoundingBox($x, $y, $z, $x + $sizeX - 1, $y + $template->getSizeY() - 1, $z + $sizeZ - 1);
	}

	/**
	 * @param JigsawPiece[] $pieces
	 */
	private static function overlaps(array $pieces, BoundingBox $box, JigsawPiece $parent) : bool{
		foreach($pieces as $piece){
			if($piece !== $parent && $piece->boundingBox->intersects($box)){
				return true;
			}
		}

		return false;
	}

	private static function contains(BoundingBox $clip, BoundingBox $box) : bool{
		return $box->x0 >= $clip->x0 && $box->x1 <= $clip->x1
			&& $box->y0 >= $clip->y0 && $box->y1 <= $clip->y1
			&& $box->z0 >= $clip->z0 && $box->z1 <= $clip->z1;
	}

	/**
	 * @phpstan-param list<array{string, int}> $entries
	 *
	 * @return string[]
	 */
	private static function weighted(array $entries, Random $random) : array{
		$identifiers = [];
		foreach($entries as [$identifier, $weight]){
			for($i = 0; $i < $weight; ++$i){
				$identifiers[] = $identifier;
			}
		}

		return self::shuffle($identifiers, $random);
	}

	/**
	 * @template T
	 * @param T[] $values
	 *
	 * @return T[]
	 */
	private static function shuffle(array $values, Random $random) : array{
		for($i = count($values) - 1; $i > 0; --$i){
			$index = $random->nextBoundedInt($i + 1);
			$swap = $values[$i];
			$values[$i] = $values[$index];
			$values[$index] = $swap;
		}

		return $values;
	}

	/**
	 * The templates turn clockwise, so a direction they carry has to turn the same way.
	 */
	private static function turn(int $facing, int $rotation) : int{
		if(Facing::axis($facing) === Axis::Y){
			return $facing;
		}

		for($i = 0; $i < $rotation; ++$i){
			$facing = Facing::rotateY($facing, true);
		}

		return $facing;
	}

	/**
	 * @return int[]
	 * @phpstan-return array{int, int, int}
	 */
	private static function step(int $facing) : array{
		return match($facing){
			Facing::DOWN => [0, -1, 0],
			Facing::UP => [0, 1, 0],
			Facing::NORTH => [0, 0, -1],
			Facing::SOUTH => [0, 0, 1],
			Facing::WEST => [-1, 0, 0],
			default => [1, 0, 0]
		};
	}
}
