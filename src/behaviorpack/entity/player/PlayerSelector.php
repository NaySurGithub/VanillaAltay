<?php

declare(strict_types=1);

namespace behaviorpack\entity\player;

use Closure;
use pocketmine\math\AxisAlignedBB;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use function count;
use function explode;
use function is_numeric;
use function max;
use function min;
use function preg_match_all;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function trim;
use const PREG_SET_ORDER;

/**
 * Resolves the "@s" selectors of a command run as a player: each one is
 * replaced by the quoted player name when its conditions match the player.
 */
final class PlayerSelector{

	/**
	 * Tells whether a player has a tag.
	 *
	 * @var (Closure(Player, string) : bool)|null
	 */
	public static ?Closure $tagResolver = null;

	/**
	 * Returns the score of a player for an objective, or null.
	 *
	 * @var (Closure(Player, string) : ?int)|null
	 */
	public static ?Closure $scoreResolver = null;

	private function __construct(){
	}

	/**
	 * Returns the command with the "@s" selectors replaced, or null when one
	 * of them does not select the player.
	 */
	public static function apply(Player $player, string $command) : ?string{
		if(preg_match_all('/@s(\[(?:[^\[\]]|\{[^}]*\})*\])?/', $command, $matches, PREG_SET_ORDER) === false){
			return $command;
		}
		$quoted = "\"" . str_replace("\"", "", $player->getName()) . "\"";
		foreach($matches as $match){
			$arguments = isset($match[1]) ? substr($match[1], 1, -1) : "";
			if(!self::matches($player, $arguments)){
				return null;
			}
			$command = str_replace($match[0], $quoted, $command);
		}
		return $command;
	}

	public static function matches(Player $player, string $arguments) : bool{
		$values = self::parse($arguments);
		$position = $player->getPosition();
		$x = self::coordinate($values["x"] ?? null, $position->x);
		$y = self::coordinate($values["y"] ?? null, $position->y);
		$z = self::coordinate($values["z"] ?? null, $position->z);
		if(isset($values["dx"]) || isset($values["dy"]) || isset($values["dz"])){
			$dx = (float) ($values["dx"] ?? 0);
			$dy = (float) ($values["dy"] ?? 0);
			$dz = (float) ($values["dz"] ?? 0);
			$volume = new AxisAlignedBB(
				min($x, $x + $dx),
				min($y, $y + $dy),
				min($z, $z + $dz),
				max($x, $x + $dx) + 1,
				max($y, $y + $dy) + 1,
				max($z, $z + $dz) + 1
			);
			if(!$volume->intersectsWith($player->getBoundingBox())){
				return false;
			}
		}
		$distanceSquared = ($position->x - $x) ** 2 + ($position->y - $y) ** 2 + ($position->z - $z) ** 2;
		if(isset($values["r"]) && is_numeric($values["r"]) && $distanceSquared > ((float) $values["r"]) ** 2){
			return false;
		}
		if(isset($values["rm"]) && is_numeric($values["rm"]) && $distanceSquared < ((float) $values["rm"]) ** 2){
			return false;
		}
		if(isset($values["name"]) && !self::negatable($values["name"], fn(string $name) : bool => strtolower(trim($name, "\"")) === strtolower($player->getName()))){
			return false;
		}
		if(isset($values["m"]) && !self::negatable($values["m"], fn(string $mode) : bool => self::gameModeMatches($player, $mode))){
			return false;
		}
		if(isset($values["type"]) && !self::negatable($values["type"], fn(string $type) : bool => $type === "player" || $type === "minecraft:player")){
			return false;
		}
		foreach($values["tag"] ?? [] as $tag){
			if(!self::negatable($tag, fn(string $name) : bool => self::hasTag($player, $name))){
				return false;
			}
		}
		if(isset($values["scores"]) && !self::scoresMatch($player, $values["scores"])){
			return false;
		}
		return true;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function parse(string $arguments) : array{
		$values = [];
		$depth = 0;
		$current = "";
		$parts = [];
		$length = strlen($arguments);
		for($i = 0; $i < $length; $i++){
			$char = $arguments[$i];
			if($char === "{"){
				$depth++;
			}elseif($char === "}"){
				$depth--;
			}
			if($char === "," && $depth <= 0){
				$parts[] = $current;
				$current = "";
				continue;
			}
			$current .= $char;
		}
		$parts[] = $current;
		foreach($parts as $part){
			$pair = explode("=", $part, 2);
			if(count($pair) !== 2){
				continue;
			}
			$key = strtolower(trim($pair[0]));
			$value = trim($pair[1]);
			if($key === "tag"){
				$values["tag"][] = $value;
			}else{
				$values[$key] = $value;
			}
		}
		return $values;
	}

	private static function coordinate(mixed $value, float $current) : float{
		if($value === null){
			return $current;
		}
		$value = (string) $value;
		if(str_starts_with($value, "~") || str_starts_with($value, "^")){
			$offset = substr($value, 1);
			return $current + (is_numeric($offset) ? (float) $offset : 0.0);
		}
		return is_numeric($value) ? (float) $value : $current;
	}

	/**
	 * @param Closure(string) : bool $test
	 */
	private static function negatable(mixed $value, Closure $test) : bool{
		$value = (string) $value;
		if(str_starts_with($value, "!")){
			return !$test(substr($value, 1));
		}
		return $test($value);
	}

	private static function hasTag(Player $player, string $tag) : bool{
		if($tag === ""){
			return false;
		}
		return self::$tagResolver !== null && (self::$tagResolver)($player, $tag);
	}

	private static function gameModeMatches(Player $player, string $mode) : bool{
		$gameMode = $player->getGamemode();
		return match(strtolower($mode)){
			"0", "s", "survival" => $gameMode === GameMode::SURVIVAL,
			"1", "c", "creative" => $gameMode === GameMode::CREATIVE,
			"2", "a", "adventure" => $gameMode === GameMode::ADVENTURE,
			"6", "spectator" => $gameMode === GameMode::SPECTATOR,
			default => false
		};
	}

	private static function scoresMatch(Player $player, mixed $scores) : bool{
		$scores = trim((string) $scores, "{} ");
		if($scores === ""){
			return true;
		}
		foreach(explode(",", $scores) as $entry){
			$pair = explode("=", $entry, 2);
			if(count($pair) !== 2){
				continue;
			}
			$score = self::$scoreResolver !== null ? (self::$scoreResolver)($player, trim($pair[0])) : null;
			$range = trim($pair[1]);
			$negated = str_starts_with($range, "!");
			if($negated){
				$range = substr($range, 1);
			}
			$inside = $score !== null && self::inRange($score, $range);
			if($inside === $negated){
				return false;
			}
		}
		return true;
	}

	private static function inRange(int $score, string $range) : bool{
		if(!str_contains($range, "..")){
			return is_numeric($range) && $score === (int) $range;
		}
		[$low, $high] = explode("..", $range, 2);
		if($low !== "" && is_numeric($low) && $score < (int) $low){
			return false;
		}
		if($high !== "" && is_numeric($high) && $score > (int) $high){
			return false;
		}
		return true;
	}
}
