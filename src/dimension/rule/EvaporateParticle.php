<?php

declare(strict_types=1);

namespace dimension\rule;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\world\particle\Particle;

/**
 * The puff of steam shown when water evaporates in the nether.
 */
final class EvaporateParticle implements Particle{

	public function encode(Vector3 $pos) : array{
		return [LevelEventPacket::create(LevelEvent::PARTICLE_EVAPORATE, 0, $pos)];
	}
}
