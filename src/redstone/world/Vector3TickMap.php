<?php

declare(strict_types=1);

namespace redstone\world;

use Generator;
use pocketmine\world\World;

final class Vector3TickMap{

	/** @var array<int, array{int, int, int, int}> */
	public array $vector_tick_map = [];

	/** @var array<int, array<int, array{int, int, int}>> */
	private array $tick_vector_map = [];

	public function __construct(){
	}

	public function push(int $x, int $y, int $z, int $tick, bool $override) : void{
		if(isset($this->vector_tick_map[$hash = World::blockHash($x, $y, $z)])){
			if(!$override){
				return;
			}
			unset($this->tick_vector_map[$this->vector_tick_map[$hash][3]][$hash], $this->vector_tick_map[$hash]);
		}
		$this->vector_tick_map[$hash] = [$x, $y, $z, $tick];
		$this->tick_vector_map[$tick][$hash] = [$x, $y, $z];
	}

	/**
	 * @param int $tick
	 * @return Generator<array{int, int, int}>
	 */
	public function pop(int $tick) : Generator{
		if(isset($this->tick_vector_map[$tick])){
			$map = $this->tick_vector_map[$tick];
			unset($this->tick_vector_map[$tick]);
			foreach($map as $hash => $entry){
				unset($this->vector_tick_map[$hash]);
				yield $entry;
			}
		}
	}
}