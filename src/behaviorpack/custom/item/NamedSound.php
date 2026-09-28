<?php

declare(strict_types=1);

namespace behaviorpack\custom\item;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\world\sound\Sound;

/**
 * A sound played by its resource pack name.
 */
final class NamedSound implements Sound{

	public function __construct(
		private string $name
	){
	}

	public function encode(Vector3 $pos) : array{
		return [PlaySoundPacket::create($this->name, $pos->x, $pos->y, $pos->z, 1.0, 1.0, 0, false, null, null)];
	}
}
