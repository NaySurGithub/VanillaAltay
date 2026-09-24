<?php

declare(strict_types=1);

namespace behaviorpack\custom;

use customiesdevs\customies\block\component\BlockComponent;
use pocketmine\nbt\tag\CompoundTag;
use function round;

/**
 * The minecraft:transformation block component, which Customies only builds
 * inside RotatableTrait.
 */
final class TransformationComponent implements BlockComponent{

	/**
	 * @param array{float, float, float} $rotation
	 * @param array{float, float, float} $translation
	 * @param array{float, float, float} $scale
	 */
	public function __construct(
		private array $rotation,
		private array $translation,
		private array $scale
	){
	}

	public function getName() : string{
		return "minecraft:transformation";
	}

	public function getValue() : CompoundTag{
		return CompoundTag::create()
			->setInt("RX", self::quarterTurns($this->rotation[0]))
			->setInt("RY", self::quarterTurns($this->rotation[1]))
			->setInt("RZ", self::quarterTurns($this->rotation[2]))
			->setFloat("SX", $this->scale[0])
			->setFloat("SY", $this->scale[1])
			->setFloat("SZ", $this->scale[2])
			->setFloat("TX", $this->translation[0])
			->setFloat("TY", $this->translation[1])
			->setFloat("TZ", $this->translation[2]);
	}

	private static function quarterTurns(float $degrees) : int{
		return (((int) round($degrees / 90)) % 4 + 4) % 4;
	}
}
