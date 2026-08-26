<?php

declare(strict_types=1);

namespace VanillaAltay\entity\spawn;

enum SpawnCategory
{
	case MONSTER;
	case CREATURE;
	case WATER_CREATURE;
	case AMBIENT;
}
