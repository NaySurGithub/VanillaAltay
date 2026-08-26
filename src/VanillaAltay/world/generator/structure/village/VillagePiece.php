<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\village;

use pocketmine\world\ChunkManager;
use VanillaAltay\world\generator\structure\mineshaft\BoundingBox;
use VanillaAltay\world\generator\structure\template\StructureTemplate;

final class VillagePiece
{
	private function __construct(
		public readonly StructureTemplate $template,
		public readonly int $originX,
		public readonly int $originY,
		public readonly int $originZ,
		public readonly int $rotation,
		public readonly BoundingBox $boundingBox,
	) {}

	public static function create(StructureTemplate $template, int $originX, int $originY, int $originZ, int $rotation) : self
	{
		[$sizeX, $sizeZ] = self::footprint($template, $rotation);

		return new self($template, $originX, $originY, $originZ, $rotation, new BoundingBox(
			$originX,
			$originY,
			$originZ,
			$originX + $sizeX - 1,
			$originY + $template->getSizeY() - 1,
			$originZ + $sizeZ - 1,
		));
	}

	/**
	 * A quarter turn swaps the two horizontal sizes; the origin stays the lowest corner either way.
	 *
	 * @phpstan-return array{int, int}
	 */
	public static function footprint(StructureTemplate $template, int $rotation) : array
	{
		return ($rotation & 1) === 0
			? [$template->getSizeX(), $template->getSizeZ()]
			: [$template->getSizeZ(), $template->getSizeX()];
	}

	public function place(ChunkManager $world) : void
	{
		$this->template->place($world, $this->originX, $this->originY, $this->originZ, $this->rotation);
	}
}
