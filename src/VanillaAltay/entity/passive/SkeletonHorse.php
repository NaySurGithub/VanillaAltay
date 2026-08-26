<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class SkeletonHorse extends Horse
{
	protected const NETWORK_ID = EntityIds::SKELETON_HORSE;

	protected const NAME = "Skeleton Horse";

	protected const HEALTH = 15;
}
