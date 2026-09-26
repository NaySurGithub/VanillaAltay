<?php

declare(strict_types=1);

namespace dummy;

use dummy\block\DummyBlocks;
use dummy\item\DummyItems;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\AsyncTask;
use function count;
use function is_array;
use function serialize;
use function unserialize;

/**
 * Fills every vanilla block and item the server and the other modules do not
 * implement with a dummy, read from the vanilla data bundled with the server.
 * It must be enabled after every other module.
 */
final class DummyModule{

	public function __construct(
		private PluginBase $plugin
	){}

	public function enable() : void{
		$typeIds = DummyBlocks::findMissing();
		DummyBlocks::register($typeIds);
		$items = DummyItems::register();
		DummyCreativeInventory::rebuild();

		$pool = $this->plugin->getServer()->getAsyncPool();
		$payload = serialize($typeIds);
		$pool->addWorkerStartHook(function(int $worker) use($pool, $payload) : void{
			$pool->submitTaskToWorker(new class($payload) extends AsyncTask{
				public function __construct(
					private string $payload
				){
				}

				public function onRun() : void{
					$typeIds = unserialize($this->payload, ["allowed_classes" => false]);
					if(is_array($typeIds)){
						DummyBlocks::register($typeIds);
					}
				}
			}, $worker);
		});

		$this->plugin->getLogger()->info("Dummy content: " . count($typeIds) . " blocks, " . $items . " items");
	}
}
