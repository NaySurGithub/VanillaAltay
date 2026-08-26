<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class TraderLlama extends Llama
{
	protected const NETWORK_ID = EntityIds::TRADER_LLAMA;

	protected const NAME = "Trader Llama";
}
