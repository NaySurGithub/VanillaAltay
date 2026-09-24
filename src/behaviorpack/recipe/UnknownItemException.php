<?php

declare(strict_types=1);

namespace behaviorpack\recipe;

/**
 * Thrown when a recipe references an item identifier that no registered item
 * matches, vanilla or custom.
 */
final class UnknownItemException extends \RuntimeException{

	public function __construct(
		private string $identifier
	){
		parent::__construct("Unknown item \"$identifier\"");
	}

	public function getIdentifier() : string{
		return $this->identifier;
	}
}
