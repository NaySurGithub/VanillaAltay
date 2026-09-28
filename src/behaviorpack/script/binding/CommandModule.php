<?php

declare(strict_types=1);

namespace behaviorpack\script\binding;

use behaviorpack\script\js\Interpreter;
use behaviorpack\script\js\JsArray;
use behaviorpack\script\js\JsCallable;
use behaviorpack\script\js\JsObject;
use behaviorpack\script\ScriptException;
use behaviorpack\script\ScriptRuntime;
use function is_array;
use function is_float;
use function is_int;
use function is_string;
use function strtolower;

/**
 * The CustomCommandRegistry of @minecraft/server: registerEnum and
 * registerCommand, and the delivery of executed custom commands ("__cmd")
 * to their script callbacks.
 */
final class CommandModule{

	private Interpreter $js;

	/** @var array<string, array{callback: JsCallable, pack: ?string, types: list<string>}> */
	private array $commands = [];

	public function __construct(
		private ScriptRuntime $runtime,
		private ClassFactory $f,
		private ServerModule $s
	){
		$this->js = $runtime->js;
	}

	public function define() : void{
		$f = $this->f;
		$js = $this->js;
		$registry = $this->s->commandRegistry;

		$f->method($registry, "registerEnum", function(mixed $thisValue, array $args) use ($js) : mixed{
			$name = $js->toString($args[0] ?? "");
			$list = $args[1] ?? null;
			if(!$list instanceof JsArray){
				$js->throwError("TypeError", "Enum values must be an array of strings");
			}
			$values = [];
			foreach($list->items as $item){
				$values[] = $js->toString($item);
			}
			try{
				$this->s->raw("cmd.enum", $name, $values);
			}catch(ScriptException $e){
				$js->throwError("Error", $e->getMessage());
			}
			return null;
		}, 2);

		$f->method($registry, "registerCommand", function(mixed $thisValue, array $args) use ($js) : mixed{
			$definition = $args[0] ?? null;
			$callback = $args[1] ?? null;
			if(!$definition instanceof JsObject || $definition instanceof JsArray){
				$js->throwError("TypeError", "Expected a CustomCommand");
			}
			if(!$callback instanceof JsCallable){
				$js->throwError("TypeError", "Expected a callback function");
			}
			$data = $this->s->fromJs($definition);
			if(!is_array($data)){
				$js->throwError("TypeError", "Expected a CustomCommand");
			}
			$types = [];
			foreach(["mandatoryParameters", "optionalParameters"] as $key){
				foreach(is_array($data[$key] ?? null) ? $data[$key] : [] as $parameter){
					$types[] = is_array($parameter) ? (string) ($parameter["type"] ?? "") : "";
				}
			}
			try{
				$this->s->raw("cmd.register", $data);
			}catch(ScriptException $e){
				$js->throwError("Error", $e->getMessage());
			}
			$this->commands[strtolower((string) ($data["name"] ?? ""))] = ["callback" => $callback, "pack" => $js->context, "types" => $types];
			return null;
		}, 2);

		$this->runtime->queuedHandlers["__cmd"] = function(array $data) : void{
			$this->dispatch($data);
		};
	}

	/**
	 * Runs the callback of an executed custom command and sends its result
	 * message back to the sender.
	 *
	 * @param array<string, mixed> $data
	 */
	private function dispatch(array $data) : void{
		$token = (int) ($data["token"] ?? 0);
		$command = $this->commands[(string) ($data["name"] ?? "")] ?? null;
		if($command === null){
			$this->s->raw("cmd.reply", $token, 0, null);
			return;
		}
		$this->runtime->run($command["pack"], function() use ($command, $data, $token) : void{
			$status = 0;
			$message = null;
			try{
				$arguments = [$this->origin(is_array($data["origin"] ?? null) ? $data["origin"] : [])];
				foreach(is_array($data["args"] ?? null) ? $data["args"] : [] as $index => $value){
					$arguments[] = $this->argument($command["types"][$index] ?? "String", $value);
				}
				$result = $this->js->call($command["callback"], null, $arguments);
				if($result instanceof JsObject && !$result instanceof JsArray){
					$status = (int) $this->js->toNumber($this->js->get($result, "status") ?? 0);
					$text = $this->js->get($result, "message");
					if($text !== null){
						$message = $this->s->text($text);
					}
				}
			}finally{
				$this->s->raw("cmd.reply", $token, $status, $message);
			}
		});
	}

	/**
	 * @param array<string, mixed> $origin
	 */
	private function origin(array $origin) : JsObject{
		$object = $this->js->newObject();
		$object->props["sourceType"] = is_string($origin["sourceType"] ?? null) ? $origin["sourceType"] : "Server";
		foreach(["sourceEntity", "initiator"] as $key){
			if(is_array($origin[$key] ?? null)){
				$object->props[$key] = $this->s->entityObject($origin[$key]);
			}
		}
		if(is_array($origin["sourceBlock"] ?? null)){
			$object->props["sourceBlock"] = $this->s->toJs($origin["sourceBlock"]);
		}
		return $object;
	}

	private function argument(string $type, mixed $value) : mixed{
		switch($type){
			case "EntitySelector":
			case "PlayerSelector":
				$entities = [];
				foreach(is_array($value) ? $value : [] as $ref){
					if(is_array($ref)){
						$entities[] = $this->s->entityObject($ref);
					}
				}
				return $this->js->newArray($entities);
			case "Location":
				return $this->s->toJs(is_array($value) ? $value : ["x" => 0, "y" => 0, "z" => 0]);
			case "ItemType":
				return $this->f->instance($this->s->itemType, ["kind" => "type", "id" => (string) $value]);
			case "BlockType":
				return $this->f->instance($this->s->blockType, ["kind" => "type", "id" => (string) $value]);
			case "EntityType":
				return $this->f->instance($this->s->entityType, ["kind" => "type", "id" => (string) $value]);
			case "Integer":
				return is_int($value) ? $value : (int) $value;
			case "Float":
				return is_int($value) || is_float($value) ? Interpreter::intOrFloat((float) $value) : 0;
		}
		return $value;
	}
}
