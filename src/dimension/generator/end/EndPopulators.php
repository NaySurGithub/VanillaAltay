<?php

declare(strict_types=1);

namespace dimension\generator\end;

use dimension\generator\end\populator\ChorusFlowerPopulator;
use dimension\generator\end\populator\EndGatewayPopulator;
use dimension\generator\end\populator\EndIslandPopulator;
use dimension\generator\end\populator\ExitPortalPopulator;
use dimension\generator\end\populator\ObsidianPillarPopulator;
use dimension\generator\Populator;
use dimension\generator\structure\EndCityPopulator;

/**
 * The end population pass, in execution order.
 */
final class EndPopulators{

	private function __construct(){
	}

	/**
	 * @return list<Populator>
	 */
	public static function create(int $seed, string $resourceFolder) : array{
		return [
			new ObsidianPillarPopulator(),
			new ExitPortalPopulator(),
			new ChorusFlowerPopulator(),
			new EndCityPopulator($seed, $resourceFolder),
			new EndGatewayPopulator(),
			new EndIslandPopulator(),
		];
	}
}
