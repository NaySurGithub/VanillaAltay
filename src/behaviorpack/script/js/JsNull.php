<?php

declare(strict_types=1);

namespace behaviorpack\script\js;

/**
 * The JavaScript null value. The PHP null value stands for undefined.
 */
final class JsNull{

	private static ?JsNull $instance = null;

	private function __construct(){

	}

	public static function get() : JsNull{
		return self::$instance ??= new JsNull();
	}
}
