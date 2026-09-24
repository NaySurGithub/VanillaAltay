<?php

declare(strict_types=1);

namespace dimension\generator\structure\jigsaw;

use dimension\generator\structure\BoundingBox;
use dimension\generator\structure\template\JigsawConnector;
use dimension\generator\structure\template\StructureTemplate;

/**
 * A template placed during jigsaw assembly, at a position relative to the
 * structure origin.
 */
final class JigsawPiece{

	/**
	 * @param list<JigsawConnector> $sourceJigsaws jigsaws of the unrotated template
	 * @param list<JigsawConnector> $placedJigsaws the same jigsaws after rotation, relative to the piece
	 */
	public function __construct(
		public readonly string $structureName,
		public readonly StructureTemplate $source,
		public readonly int $x,
		public readonly int $y,
		public readonly int $z,
		public readonly int $rotation,
		public readonly BoundingBox $boundingBox,
		public readonly array $sourceJigsaws,
		public readonly array $placedJigsaws
	){}
}
