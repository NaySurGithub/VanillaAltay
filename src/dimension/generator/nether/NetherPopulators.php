<?php

declare(strict_types=1);

namespace dimension\generator\nether;

use dimension\generator\nether\populator\AncientDebrisLargePopulator;
use dimension\generator\nether\populator\AncientDebrisSmallPopulator;
use dimension\generator\nether\populator\BasaltDeltaLavaPopulator;
use dimension\generator\nether\populator\BasaltDeltaMagmaPopulator;
use dimension\generator\nether\populator\BasaltDeltaPillarPopulator;
use dimension\generator\nether\populator\CrimsonFungiTreePopulator;
use dimension\generator\nether\populator\CrimsonGrassesPopulator;
use dimension\generator\nether\populator\CrimsonWeepingVinesPopulator;
use dimension\generator\nether\populator\FirePopulator;
use dimension\generator\nether\populator\GlowstonePopulator;
use dimension\generator\nether\populator\LavaOrePopulator;
use dimension\generator\nether\populator\LavaPopulator;
use dimension\generator\nether\populator\MagmaPopulator;
use dimension\generator\nether\populator\NetherBlackstonePopulator;
use dimension\generator\nether\populator\NetherGoldOrePopulator;
use dimension\generator\nether\populator\NetherGravelPopulator;
use dimension\generator\nether\populator\NetherQuartzPopulator;
use dimension\generator\nether\populator\SoulsandPopulator;
use dimension\generator\nether\populator\WarpedFungiTreePopulator;
use dimension\generator\nether\populator\WarpedGrassesPopulator;
use dimension\generator\nether\populator\WarpedTwistingVinesPopulator;
use dimension\generator\Populator;
use dimension\generator\structure\BastionRemnantPopulator;
use dimension\generator\structure\NetherFortressPopulator;
use dimension\generator\structure\NetherFossilPopulator;
use dimension\generator\structure\RuinedPortalPopulator;

/**
 * The nether population pass, in execution order.
 */
final class NetherPopulators{

	private function __construct(){
	}

	/**
	 * @return list<Populator>
	 */
	public static function create(int $seed, string $resourceFolder) : array{
		return [
			new GlowstonePopulator(),
			new SoulsandPopulator(),
			new MagmaPopulator(),
			new LavaOrePopulator(),
			new FirePopulator(),
			new LavaPopulator(),
			new NetherGoldOrePopulator(),
			new AncientDebrisSmallPopulator(),
			new AncientDebrisLargePopulator(),
			new NetherQuartzPopulator(),
			new BasaltDeltaLavaPopulator(),
			new BasaltDeltaPillarPopulator(),
			new BasaltDeltaMagmaPopulator(),
			new CrimsonFungiTreePopulator(),
			new CrimsonGrassesPopulator(),
			new CrimsonWeepingVinesPopulator(),
			new WarpedFungiTreePopulator(),
			new WarpedGrassesPopulator(),
			new WarpedTwistingVinesPopulator(),
			new NetherBlackstonePopulator(),
			new NetherGravelPopulator(),
			new BastionRemnantPopulator($seed, $resourceFolder),
			new NetherFortressPopulator($seed, $resourceFolder),
			new RuinedPortalPopulator($seed, $resourceFolder),
			new NetherFossilPopulator($seed, $resourceFolder),
		];
	}
}
