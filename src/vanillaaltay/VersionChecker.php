<?php

declare(strict_types=1);

namespace vanillaaltay;

use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\AsyncTask;
use pocketmine\utils\Internet;
use function count;
use function explode;
use function is_array;
use function is_string;
use function json_decode;
use function max;
use function preg_match;
use function preg_replace;
use function preg_split;
use function str_starts_with;
use function strtolower;
use function substr;

/**
 * Checks the latest GitHub release when the plugin starts and warns in the
 * console when a newer version is published. The releases are always
 * published under the "latest" tag, so the version is read from the
 * release title ("Vanilla-Altay v1.2.3") or from the name of its phar.
 */
final class VersionChecker extends AsyncTask{

	private const LATEST_RELEASE = "https://api.github.com/repos/NaySurGithub/VanillaAltay/releases/latest";
	private const RELEASES_PAGE = "https://github.com/NaySurGithub/VanillaAltay/releases/latest";

	private const PLUGIN = "plugin";

	public function __construct(
		PluginBase $plugin,
		private string $current
	){
		$this->storeLocal(self::PLUGIN, $plugin);
	}

	public static function checkAsync(PluginBase $plugin) : void{
		$plugin->getServer()->getAsyncPool()->submitTask(new self($plugin, $plugin->getDescription()->getVersion()));
	}

	public function onRun() : void{
		$result = Internet::getURL(self::LATEST_RELEASE, 5, [
			"Accept: application/vnd.github+json",
			"User-Agent: Vanilla-Altay/" . $this->current
		]);
		if($result === null || $result->getCode() !== 200){
			$this->setResult(null);
			return;
		}
		$this->setResult($result->getBody());
	}

	public function onCompletion() : void{
		$plugin = $this->fetchLocal(self::PLUGIN);
		if(!$plugin instanceof PluginBase || !$plugin->isEnabled()){
			return;
		}
		$body = $this->getResult();
		if(!is_string($body)){
			$plugin->getLogger()->debug("Unable to check for Vanilla-Altay updates");
			return;
		}
		$release = json_decode($body, true);
		if(!is_array($release)){
			return;
		}
		$latest = self::releaseVersion($release);
		if($latest === null || self::compare($latest, self::stripPrefix($this->current)) <= 0){
			return;
		}
		$url = is_string($release["html_url"] ?? null) ? $release["html_url"] : self::RELEASES_PAGE;
		$plugin->getLogger()->warning("A new Vanilla-Altay version is available: v" . $latest . " (running v" . self::stripPrefix($this->current) . ") " . $url);
	}

	/**
	 * Reads the version of a release from its title, the name of its phar or
	 * its tag, in that order.
	 *
	 * @param array<mixed> $release
	 */
	private static function releaseVersion(array $release) : ?string{
		$candidates = [$release["name"] ?? null];
		foreach(is_array($release["assets"] ?? null) ? $release["assets"] : [] as $asset){
			if(is_array($asset)){
				$candidates[] = $asset["name"] ?? null;
			}
		}
		$candidates[] = $release["tag_name"] ?? null;
		foreach($candidates as $candidate){
			if(is_string($candidate) && preg_match('/(?:^|[^0-9.])v?(\d+(?:\.\d+)+)/i', $candidate, $matches) === 1){
				return $matches[1];
			}
		}
		return null;
	}

	private static function compare(string $first, string $second) : int{
		$left = explode(".", (preg_split('/[-+]/', $first, 2) ?: [$first])[0]);
		$right = explode(".", (preg_split('/[-+]/', $second, 2) ?: [$second])[0]);
		for($index = 0, $count = max(count($left), count($right)); $index < $count; $index++){
			$leftPart = self::number($left[$index] ?? "0");
			$rightPart = self::number($right[$index] ?? "0");
			if($leftPart !== $rightPart){
				return $leftPart <=> $rightPart;
			}
		}
		return 0;
	}

	private static function number(string $part) : int{
		$digits = (string) preg_replace('/[^0-9].*$/', "", $part);
		return $digits === "" ? 0 : (int) $digits;
	}

	private static function stripPrefix(string $version) : string{
		return str_starts_with(strtolower($version), "v") ? substr($version, 1) : $version;
	}
}
