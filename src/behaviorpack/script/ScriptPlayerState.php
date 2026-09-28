<?php

declare(strict_types=1);

namespace behaviorpack\script;

use pocketmine\math\Vector2;

/**
 * What the scripts track per player: the last input received from the
 * client, the input permissions, the hidden HUD elements, the waypoints of
 * the locator bar and the item being used.
 */
final class ScriptPlayerState{

	/** @var array<int, array{jump: bool, sneak: bool, move: Vector2, mode: string}> */
	private array $input = [];

	/** @var array<int, int> */
	private array $locks = [];

	/** @var array<int, array<int, true>> */
	private array $hiddenHud = [];

	/** @var array<int, array<string, array{uuid: \Ramsey\Uuid\UuidInterface, data: array<string, mixed>}>> */
	private array $waypoints = [];

	/** @var array<int, array{item: array<string, mixed>|null, tick: int, duration: int}> */
	private array $using = [];

	public function forget(int $player) : void{
		unset($this->input[$player], $this->locks[$player], $this->hiddenHud[$player], $this->waypoints[$player], $this->using[$player]);
	}

	public function setInput(int $player, bool $jump, bool $sneak, Vector2 $move, string $mode) : void{
		$this->input[$player] = ["jump" => $jump, "sneak" => $sneak, "move" => $move, "mode" => $mode];
	}

	/**
	 * @return array{jump: bool, sneak: bool, move: Vector2, mode: string}
	 */
	public function getInput(int $player) : array{
		return $this->input[$player] ?? ["jump" => false, "sneak" => false, "move" => new Vector2(0, 0), "mode" => "KeyboardAndMouse"];
	}

	public function getLocks(int $player) : int{
		return $this->locks[$player] ?? 0;
	}

	public function setLocks(int $player, int $locks) : void{
		$this->locks[$player] = $locks;
	}

	/**
	 * @return array<int, true>
	 */
	public function getHiddenHud(int $player) : array{
		return $this->hiddenHud[$player] ?? [];
	}

	/**
	 * @param array<int, true> $elements
	 */
	public function setHiddenHud(int $player, array $elements) : void{
		$this->hiddenHud[$player] = $elements;
	}

	/**
	 * @return array<string, array{uuid: \Ramsey\Uuid\UuidInterface, data: array<string, mixed>}>
	 */
	public function getWaypoints(int $player) : array{
		return $this->waypoints[$player] ?? [];
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function setWaypoint(int $player, string $key, \Ramsey\Uuid\UuidInterface $uuid, array $data) : void{
		$this->waypoints[$player][$key] = ["uuid" => $uuid, "data" => $data];
	}

	public function removeWaypoint(int $player, string $key) : void{
		unset($this->waypoints[$player][$key]);
	}

	/**
	 * @return list<int>
	 */
	public function playersWithWaypoint(string $key) : array{
		$players = [];
		foreach($this->waypoints as $player => $waypoints){
			if(isset($waypoints[$key])){
				$players[] = $player;
			}
		}
		return $players;
	}

	/**
	 * @param array<string, mixed>|null $item
	 */
	public function startUsing(int $player, ?array $item, int $tick, int $duration) : void{
		$this->using[$player] = ["item" => $item, "tick" => $tick, "duration" => $duration];
	}

	/**
	 * @return array{item: array<string, mixed>|null, tick: int, duration: int}|null
	 */
	public function stopUsing(int $player) : ?array{
		$using = $this->using[$player] ?? null;
		unset($this->using[$player]);
		return $using;
	}

	public function isUsing(int $player) : bool{
		return isset($this->using[$player]);
	}
}
