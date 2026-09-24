<?php

declare(strict_types=1);

namespace behaviorpack\script;

use Closure;
use pocketmine\form\Form;
use pocketmine\player\Player;

/**
 * A form built by a script with @minecraft/server-ui. The response is handed
 * back to the script runtime as is, or null when the form was closed.
 */
final class ScriptForm implements Form{

	/**
	 * @param array<string, mixed>          $data
	 * @param Closure(Player, mixed) : void $onResponse
	 */
	public function __construct(
		private array $data,
		private Closure $onResponse
	){}

	public function handleResponse(Player $player, $data) : void{
		($this->onResponse)($player, $data);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize() : array{
		return $this->data;
	}
}
