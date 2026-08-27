<?php

declare(strict_types=1);

namespace VanillaAltay\world\sound;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\world\sound\Sound;

final class CreeperFuseSound implements Sound
{
	public function encode(Vector3 $pos) : array
	{
		return [LevelSoundEventPacket::nonActorSound(LevelSoundEvent::FUSE, $pos, false)];
	}
}
