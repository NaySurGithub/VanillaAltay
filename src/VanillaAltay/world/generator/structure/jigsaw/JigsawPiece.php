<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\jigsaw;

use VanillaAltay\world\generator\structure\mineshaft\BoundingBox;
use VanillaAltay\world\generator\structure\template\StructureTemplate;

/**
 * One template of an assembled structure, placed relative to the structure's origin.
 */
final class JigsawPiece{

	public function __construct(
		public StructureTemplate $template,
		public int $x,
		public int $y,
		public int $z,
		public int $rotation,
		public BoundingBox $boundingBox
	){}
}
