<?php

declare(strict_types=1);

namespace dimension\generator\math;

use function pack;
use function unpack;

/**
 * Single precision (IEEE 754 binary32) rounding. Every single precision
 * operation is computed in double precision then rounded with of(), which
 * gives the correctly rounded binary32 result for +, -, *, / and sqrt.
 */
final class Float32{

	private function __construct(){
	}

	public static function of(float $value) : float{
		$unpacked = unpack("g", pack("g", $value));
		return $unpacked === false ? $value : (float) $unpacked[1];
	}
}
