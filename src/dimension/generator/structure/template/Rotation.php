<?php

declare(strict_types=1);

namespace dimension\generator\structure\template;

/**
 * Quarter turns around the Y axis, stored as 0 to 3.
 */
final class Rotation{

	public const NONE = 0;
	public const ROTATE_90 = 1;
	public const ROTATE_180 = 2;
	public const ROTATE_270 = 3;

	private function __construct(){
	}

	public static function add(int $base, int $other) : int{
		return ($base + $other) & 3;
	}

	public static function inverse(int $rotation) : int{
		return match($rotation){
			self::ROTATE_90 => self::ROTATE_270,
			self::ROTATE_270 => self::ROTATE_90,
			default => $rotation
		};
	}
}
