<?php

declare(strict_types=1);

namespace behaviorpack\script\js;

/**
 * A JavaScript function, either defined by a script or native.
 */
abstract class JsCallable extends JsObject{

	public string $name = "";

	public int $length = 0;

	public function __construct(?JsObject $proto){
		parent::__construct($proto);
		$this->className = "Function";
	}
}
