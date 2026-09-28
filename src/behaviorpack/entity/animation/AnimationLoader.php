<?php

declare(strict_types=1);

namespace behaviorpack\entity\animation;

use behaviorpack\BehaviorPack;
use behaviorpack\ContentLoader;
use pocketmine\plugin\PluginBase;
use Throwable;
use function is_array;
use function is_string;

/**
 * Loads the server-side animation controllers (animation_controllers/) and
 * animations (animations/) of the behavior packs.
 */
final class AnimationLoader implements ContentLoader{

	public function __construct(
		private PluginBase $plugin
	){
	}

	public function getName() : string{
		return "animation controllers";
	}

	public function requiresCustomies() : bool{
		return false;
	}

	public function load(array $packs) : void{
		$logger = $this->plugin->getLogger();
		$controllers = 0;
		$animations = 0;
		foreach($packs as $pack){
			foreach($pack->listFiles("animation_controllers", "json") as $file){
				try{
					$controllers += $this->loadSection($file, "animation_controllers", true);
				}catch(Throwable $e){
					$logger->warning("Behavior packs: skipped animation controllers " . $pack->getName() . "/" . $pack->relativePath($file) . ": " . $e->getMessage());
				}
			}
			foreach($pack->listFiles("animations", "json") as $file){
				try{
					$animations += $this->loadSection($file, "animations", false);
				}catch(Throwable $e){
					$logger->warning("Behavior packs: skipped animations " . $pack->getName() . "/" . $pack->relativePath($file) . ": " . $e->getMessage());
				}
			}
		}
		if($controllers > 0 || $animations > 0){
			$logger->info("Behavior packs: $controllers animation controllers, $animations animations");
		}
	}

	private function loadSection(string $file, string $key, bool $controllers) : int{
		$data = BehaviorPack::readJson($file);
		$section = $data[$key] ?? null;
		if(!is_array($section)){
			return 0;
		}
		$count = 0;
		foreach($section as $name => $entry){
			if(!is_string($name) || !is_array($entry)){
				continue;
			}
			if($controllers){
				AnimationRegistry::registerController($name, $entry);
			}else{
				AnimationRegistry::registerAnimation($name, $entry);
			}
			$count++;
		}
		return $count;
	}

	public function close() : void{
		AnimationRegistry::clear();
	}
}
