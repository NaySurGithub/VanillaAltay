<?php

declare(strict_types=1);

namespace behaviorpack\script\js;

/**
 * Marks a let, const or class binding that is not initialized yet, and the
 * this value of a derived constructor before super() is called.
 */
final class Tdz{

	private static ?Tdz $instance = null;

	private function __construct(){

	}

	public static function get() : Tdz{
		return self::$instance ??= new Tdz();
	}
}
