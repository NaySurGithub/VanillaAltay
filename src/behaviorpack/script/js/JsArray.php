<?php

declare(strict_types=1);

namespace behaviorpack\script\js;

/**
 * A JavaScript array: its elements are kept as a PHP list in $items, holes
 * being undefined.
 */
final class JsArray extends JsObject{

	/** @var list<mixed> */
	public array $items = [];

	public bool $frozen = false;

	/**
	 * @param list<mixed> $items
	 */
	public function __construct(?JsObject $proto, array $items = []){
		parent::__construct($proto);
		$this->items = $items;
		$this->className = "Array";
	}
}
