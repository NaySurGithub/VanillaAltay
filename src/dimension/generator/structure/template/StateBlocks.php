<?php

declare(strict_types=1);

namespace dimension\generator\structure\template;

use pocketmine\block\Block;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\nbt\tag\Tag;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use function array_key_exists;
use function array_merge;
use function ksort;
use function serialize;

/**
 * Converts Bedrock block states to server blocks, and builds block states
 * from an identifier plus the states to override. Results are cached per
 * thread. States the server does not know convert to null.
 */
final class StateBlocks{

	/** @var array<string, int> */
	private static array $stateIds = [];
	/** @var array<string, BlockStateData|null> */
	private static array $defaults = [];

	private function __construct(){
	}

	public static function toBlock(BlockStateData $state) : ?Block{
		$key = self::key($state);
		if(!isset(self::$stateIds[$key])){
			self::$stateIds[$key] = self::resolve($state);
		}
		$stateId = self::$stateIds[$key];
		if($stateId === -1){
			return null;
		}
		return RuntimeBlockStateRegistry::getInstance()->fromStateId($stateId);
	}

	/**
	 * State of $identifier with its default values, overridden by $states.
	 *
	 * @param array<string, Tag> $states
	 */
	public static function state(string $identifier, array $states = []) : ?BlockStateData{
		if(!array_key_exists($identifier, self::$defaults)){
			self::$defaults[$identifier] = self::resolveDefault($identifier);
		}
		$default = self::$defaults[$identifier];
		if($default === null){
			return null;
		}
		if($states === []){
			return $default;
		}
		return BlockStateData::current($identifier, array_merge($default->getStates(), $states));
	}

	public static function key(BlockStateData $state) : string{
		$values = [];
		foreach($state->getStates() as $name => $tag){
			$values[$name] = $tag->getValue();
		}
		ksort($values);
		return $state->getName() . serialize($values);
	}

	private static function resolve(BlockStateData $state) : int{
		try{
			return GlobalBlockStateHandlers::getDeserializer()->deserialize($state);
		}catch(\Exception){
			return -1;
		}
	}

	private static function resolveDefault(string $identifier) : ?BlockStateData{
		$upgrader = GlobalBlockStateHandlers::getUpgrader();
		try{
			$default = $upgrader->getBlockStateUpgrader()->upgrade($upgrader->upgradeStringIdMeta($identifier, 0));
		}catch(\Exception){
			return null;
		}
		if($default->getName() !== $identifier){
			return null;
		}
		return $default;
	}
}
