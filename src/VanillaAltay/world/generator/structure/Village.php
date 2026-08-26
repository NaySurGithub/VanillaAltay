<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure;

use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use VanillaAltay\world\generator\structure\mineshaft\BoundingBox;
use VanillaAltay\world\generator\structure\template\StructureTemplate;
use VanillaAltay\world\generator\structure\template\TemplateArchive;
use VanillaAltay\world\generator\structure\village\VillagePiece;
use VanillaAltay\world\generator\structure\village\VillageVariant;

use function array_key_first;
use function count;
use function intdiv;

/**
 * A town centre with four streets running out of it and houses standing along them, all stamped from the
 * archive. Mojang assembles a village from the jigsaw blocks buried in those same templates; the archive keeps
 * that data but the reader does not expose it, so the streets are laid out here instead.
 */
final class Village implements SpreadStructure
{
	private const SALT = 10387312;
	private const MIN_DISTANCE = 8;
	private const MAX_DISTANCE = 34;

	/**
	 * Measured over sixty layouts across the five variants: nothing lands further than five chunks from the
	 * origin, and a village covers ten by eleven chunks at its widest.
	 */
	private const CHUNK_REACH = 6;

	private const LAYOUT_CACHE_SIZE = 4;

	/**
	 * Everything shares one ground level, so a village is flat. A per-piece terrain sample cannot be made to
	 * agree between the chunks that rebuild the layout, since most of them cannot see the ground under it.
	 */
	private const SURFACE_Y = 64;

	private const ARM_LENGTH = 48;
	private const HOUSE_GAP = 1;
	private const HOUSE_CHANCE = 70;

	/**
	 * @var array[]
	 * @phpstan-var array<int, VillagePiece[]>
	 */
	private array $layouts = [];

	public function getName() : string
	{
		return "village";
	}

	public function getPlacement() : StructurePlacement
	{
		return new StructurePlacement(self::SALT, self::MIN_DISTANCE, self::MAX_DISTANCE, fn(int $biomeId) => VillageVariant::forBiome($biomeId) !== null);
	}

	public function getChunkReach() : int
	{
		return self::CHUNK_REACH;
	}

	public function place(ChunkManager $world, Random $random, int $x, int $y, int $z) : void
	{
		$this->placeAround($world, $random, $x >> 4, $z >> 4, $x >> 4, $z >> 4);
	}

	public function placeAround(ChunkManager $world, Random $random, int $originChunkX, int $originChunkZ, int $targetChunkX, int $targetChunkZ) : void
	{
		$variant = $this->getVariant($world, $originChunkX, $originChunkZ, $targetChunkX, $targetChunkZ);
		if ($variant === null) {
			return;
		}

		$layoutSeed = $random->getSeed();
		$pieces = $this->getLayout($random, $layoutSeed, $originChunkX, $originChunkZ, $variant);

		$clip = new BoundingBox(
			($targetChunkX - 1) << 4,
			$world->getMinY(),
			($targetChunkZ - 1) << 4,
			(($targetChunkX + 2) << 4) - 1,
			$world->getMaxY() - 1,
			(($targetChunkZ + 2) << 4) - 1,
		);

		foreach ($pieces as $piece) {
			if (!$piece->boundingBox->intersects($clip)) {
				continue;
			}

			$piece->place($world);
		}
	}

	/**
	 * The origin decides which village is built, but a chunk far enough to rebuild the layout rarely has it
	 * loaded, so its own biome answers instead.
	 */
	private function getVariant(ChunkManager $world, int $originChunkX, int $originChunkZ, int $targetChunkX, int $targetChunkZ) : ?VillageVariant
	{
		$chunk = $world->getChunk($originChunkX, $originChunkZ) ?? $world->getChunk($targetChunkX, $targetChunkZ);

		return $chunk === null ? null : VillageVariant::forBiome($chunk->getBiomeId(7, 0, 7));
	}

	/**
	 * @return VillagePiece[]
	 */
	private function getLayout(Random $random, int $layoutSeed, int $chunkX, int $chunkZ, VillageVariant $variant) : array
	{
		if (isset($this->layouts[$layoutSeed])) {
			return $this->layouts[$layoutSeed];
		}

		$pieces = $this->buildLayout($random, $layoutSeed, $chunkX, $chunkZ, $variant);

		if (count($this->layouts) >= self::LAYOUT_CACHE_SIZE) {
			//the keys are layout seeds, so shifting would renumber them and hand back the wrong layout
			unset($this->layouts[array_key_first($this->layouts)]);
		}

		return $this->layouts[$layoutSeed] = $pieces;
	}

	/**
	 * @return VillagePiece[]
	 */
	private function buildLayout(Random $random, int $layoutSeed, int $chunkX, int $chunkZ, VillageVariant $variant) : array
	{
		$archive = TemplateArchive::getInstance();

		$centerX = ($chunkX << 4) + 8;
		$centerZ = ($chunkZ << 4) + 8;

		$index = 0;
		$centre = $this->pick($archive, $random, $layoutSeed, $index, $variant->townCenters);
		if ($centre === null) {
			return [];
		}

		[$centreSizeX, $centreSizeZ] = VillagePiece::footprint($centre, StructureTemplate::ROTATION_NONE);
		$pieces = [VillagePiece::create($centre, $centerX - intdiv($centreSizeX, 2), self::SURFACE_Y, $centerZ - intdiv($centreSizeZ, 2), StructureTemplate::ROTATION_NONE)];

		$streets = [];
		for ($direction = 0; $direction < 4; ++$direction) {
			$this->buildArm($archive, $random, $layoutSeed, $index, $variant, $pieces, $streets, $centerX, $centerZ, $direction);
		}

		//the streets are all laid before a single house goes up, or the first arm's houses would block the next
		foreach ($streets as [$street, $direction]) {
			$this->addHouse($archive, $random, $layoutSeed, $index, $variant, $pieces, $street, $direction, -1);
			$this->addHouse($archive, $random, $layoutSeed, $index, $variant, $pieces, $street, $direction, 1);
		}

		return $pieces;
	}

	/**
	 * @param VillagePiece[] $pieces
	 * @phpstan-param array<int, VillagePiece> $pieces
	 * @phpstan-param array<int, array{VillagePiece, int}> $streets
	 */
	private function buildArm(TemplateArchive $archive, Random $random, int $layoutSeed, int &$index, VillageVariant $variant, array &$pieces, array &$streets, int $centerX, int $centerZ, int $direction) : void
	{
		$centre = $pieces[0]->boundingBox;

		$cursor = match ($direction) {
			0 => $centre->x1 + 1,
			1 => $centre->z1 + 1,
			2 => $centre->x0 - 1,
			default => $centre->z0 - 1,
		};

		$travelled = 0;

		while ($travelled < self::ARM_LENGTH) {
			$template = $this->pick($archive, $random, $layoutSeed, $index, $variant->streets);
			if ($template === null) {
				return;
			}

			$street = $this->alongArm($template, $direction, $cursor, $centerX, $centerZ, self::SURFACE_Y - 1);

			//an arm laid later meets the corner of an earlier one, and slides past it rather than stopping there
			$length = 1;
			if (!$this->collides($pieces, $street->boundingBox)) {
				$pieces[] = $street;
				$streets[] = [$street, $direction];
				$length = ($direction & 1) === 0 ? $street->boundingBox->getXSpan() : $street->boundingBox->getZSpan();
			}

			$cursor += ($direction === 0 || $direction === 1) ? $length : -$length;
			$travelled += $length;
		}

		$template = $this->pick($archive, $random, $layoutSeed, $index, $variant->terminators);
		if ($template === null) {
			return;
		}

		$cap = $this->alongArm($template, $direction, $cursor, $centerX, $centerZ, self::SURFACE_Y - 1);
		if (!$this->collides($pieces, $cap->boundingBox)) {
			$pieces[] = $cap;
		}
	}

	/**
	 * Streets run along X in the archive, so turning one by the arm's own direction points it outwards. The
	 * cursor is the leading edge of the arm, which is the far corner when it runs towards a negative axis.
	 */
	private function alongArm(StructureTemplate $template, int $direction, int $cursor, int $centerX, int $centerZ, int $originY) : VillagePiece
	{
		[$sizeX, $sizeZ] = VillagePiece::footprint($template, $direction);

		return match ($direction) {
			0 => VillagePiece::create($template, $cursor, $originY, $centerZ - intdiv($sizeZ, 2), $direction),
			1 => VillagePiece::create($template, $centerX - intdiv($sizeX, 2), $originY, $cursor, $direction),
			2 => VillagePiece::create($template, $cursor - $sizeX + 1, $originY, $centerZ - intdiv($sizeZ, 2), $direction),
			default => VillagePiece::create($template, $centerX - intdiv($sizeX, 2), $originY, $cursor - $sizeZ + 1, $direction),
		};
	}

	/**
	 * @param VillagePiece[] $pieces
	 * @phpstan-param array<int, VillagePiece> $pieces
	 */
	private function addHouse(TemplateArchive $archive, Random $random, int $layoutSeed, int &$index, VillageVariant $variant, array &$pieces, VillagePiece $street, int $direction, int $side) : void
	{
		$random->setSeed($layoutSeed ^ ($index++ * 0x9E3779B1));
		if ($random->nextBoundedInt(100) >= self::HOUSE_CHANCE) {
			return;
		}

		$template = $this->pick($archive, $random, $layoutSeed, $index, $variant->houses);
		if ($template === null) {
			return;
		}

		$rotation = $side < 0 ? ($direction + 1) & 3 : ($direction + 3) & 3;
		[$sizeX, $sizeZ] = VillagePiece::footprint($template, $rotation);
		$box = $street->boundingBox;

		if (($direction & 1) === 0) {
			$originX = $box->x0;
			$originZ = $side < 0 ? $box->z0 - $sizeZ - self::HOUSE_GAP : $box->z1 + 1 + self::HOUSE_GAP;
		} else {
			$originZ = $box->z0;
			$originX = $side < 0 ? $box->x0 - $sizeX - self::HOUSE_GAP : $box->x1 + 1 + self::HOUSE_GAP;
		}

		$house = VillagePiece::create($template, $originX, self::SURFACE_Y, $originZ, $rotation);
		if ($this->collides($pieces, $house->boundingBox)) {
			return;
		}

		$pieces[] = $house;
	}

	/**
	 * Every piece draws from its own seed, so what it picks does not depend on how many pieces came before it.
	 *
	 * @param string[] $identifiers
	 */
	private function pick(TemplateArchive $archive, Random $random, int $layoutSeed, int &$index, array $identifiers) : ?StructureTemplate
	{
		$count = count($identifiers);
		if ($count === 0) {
			return null;
		}

		$random->setSeed($layoutSeed ^ ($index++ * 0x9E3779B1));

		return $archive->get($identifiers[$random->nextBoundedInt($count)]);
	}

	/**
	 * @param VillagePiece[] $pieces
	 */
	private function collides(array $pieces, BoundingBox $box) : bool
	{
		foreach ($pieces as $piece) {
			if ($piece->boundingBox->intersects($box)) {
				return true;
			}
		}

		return false;
	}
}
