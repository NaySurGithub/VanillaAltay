<?php

declare(strict_types=1);

namespace behaviorpack\entity\animation;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\script\ScriptCommandSender;
use function array_is_list;
use function count;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function ltrim;
use function preg_replace;
use function str_replace;

/**
 * Animation host of a custom behavior pack entity. Variables are kept in
 * the entity data store so that they survive reloads.
 */
final class EntityAnimationHost implements AnimationHost{

	private const DATA_VARIABLES = "molang_vars";
	private const DATA_INITIALIZED = "molang_initialized";

	private static ?ScriptCommandSender $commandSender = null;

	public function __construct(
		private BehaviorEntity $entity
	){
	}

	public function getAnimationDefinition() : array{
		return $this->entity->getDefinition();
	}

	public function evaluateMolang(mixed $value) : float{
		return $this->entity->molang($value);
	}

	public function triggerAnimationEvent(string $event) : void{
		$this->entity->triggerEvent($event);
	}

	public function getMolangVariables() : array{
		return self::variablesOf($this->entity);
	}

	public function setMolangVariable(string $name, float $value) : void{
		$variables = $this->entity->getData(self::DATA_VARIABLES, []);
		$variables = is_array($variables) ? $variables : [];
		$variables[$name] = $value;
		$this->entity->setData(self::DATA_VARIABLES, $variables);
	}

	public function isAnimationInitialized() : bool{
		return $this->entity->getData(self::DATA_INITIALIZED) === true;
	}

	public function markAnimationInitialized() : void{
		$this->entity->setData(self::DATA_INITIALIZED, true);
	}

	public function runAnimationCommand(string $command) : void{
		$server = $this->entity->getWorld()->getServer();
		$target = $this->entity->getNameTag() !== "" ? $this->entity->getNameTag() : $this->entity->getName();
		$command = preg_replace('/@s(\[[^\]]*\])?/', "\"" . str_replace("\"", "", $target) . "\"", ltrim($command, "/ ")) ?? $command;
		self::$commandSender ??= new ScriptCommandSender($server, $server->getLanguage());
		$server->dispatchCommand(self::$commandSender, $command);
	}

	public function isAnimationHostClosed() : bool{
		return $this->entity->isClosed();
	}

	/**
	 * Returns the Molang variables stored on an entity.
	 *
	 * @return array<string, float|int|bool>
	 */
	public static function variablesOf(BehaviorEntity $entity) : array{
		$stored = $entity->getData(self::DATA_VARIABLES, []);
		if(!is_array($stored) || (array_is_list($stored) && count($stored) > 0)){
			return [];
		}
		$variables = [];
		foreach($stored as $name => $value){
			if(is_string($name) && (is_int($value) || is_float($value) || is_bool($value))){
				$variables[$name] = $value;
			}
		}
		return $variables;
	}
}
