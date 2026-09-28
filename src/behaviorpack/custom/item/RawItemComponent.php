<?php

declare(strict_types=1);

namespace behaviorpack\custom\item;

use customiesdevs\customies\item\component\ItemComponent;

/**
 * An item component sent to the client as a prebuilt value, for components
 * Customies has no class for.
 */
final class RawItemComponent implements ItemComponent{

	public function __construct(
		private string $name,
		private mixed $value,
		private bool $property = false
	){
	}

	public function getName() : string{
		return $this->name;
	}

	public function getValue() : mixed{
		return $this->value;
	}

	public function isProperty() : bool{
		return $this->property;
	}
}
