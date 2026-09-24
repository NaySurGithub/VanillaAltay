<?php

declare(strict_types=1);

namespace behaviorpack\script\binding;

use behaviorpack\script\js\Interpreter;
use behaviorpack\script\js\JsArray;
use behaviorpack\script\js\JsObject;
use behaviorpack\script\ScriptRuntime;
use Closure;
use function count;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function str_starts_with;

/**
 * The @minecraft/server-ui module: ActionFormData, MessageFormData and
 * ModalFormData, shown with the server form API. Headers, labels and
 * dividers are not buttons: an action form shows them in its body.
 */
final class UiModule{

	private Interpreter $js;

	private HostClass $formResponse;
	private HostClass $actionResponse;
	private HostClass $messageResponse;
	private HostClass $modalResponse;

	/** @var array<string, mixed> */
	private array $exports = [];

	public function __construct(
		private ScriptRuntime $runtime,
		private ClassFactory $f,
		private ServerModule $server
	){
		$this->js = $runtime->js;
		$this->defineResponses();
		$this->defineActionForm();
		$this->defineMessageForm();
		$this->defineModalForm();
		$this->exports["FormCancelationReason"] = $f->enum("FormCancelationReason", ["UserBusy" => "UserBusy", "UserClosed" => "UserClosed"]);
		$this->exports["FormRejectReason"] = $f->enum("FormRejectReason", ["MalformedResponse" => "MalformedResponse", "PlayerQuit" => "PlayerQuit", "ServerShutdown" => "ServerShutdown"]);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function exports() : array{
		return $this->exports;
	}

	private function defineResponses() : void{
		$f = $this->f;
		$this->formResponse = $f->define("FormResponse");
		$this->actionResponse = $f->define("ActionFormResponse", $this->formResponse);
		$this->messageResponse = $f->define("MessageFormResponse", $this->formResponse);
		$this->modalResponse = $f->define("ModalFormResponse", $this->formResponse);
		foreach([$this->formResponse, $this->actionResponse, $this->messageResponse, $this->modalResponse] as $class){
			$this->exports[$class->name] = $class->constructor;
		}
	}

	/**
	 * @param array<string, mixed> $values
	 */
	private function response(HostClass $class, array $values) : JsObject{
		$response = $this->f->instance($class, ["kind" => "response"]);
		foreach($values as $key => $value){
			$response->props[$key] = $value;
		}
		return $response;
	}

	private function canceled(HostClass $class) : JsObject{
		return $this->response($class, ["canceled" => true, "cancelationReason" => "UserClosed"]);
	}

	/**
	 * @param array<string, mixed>          $initial
	 * @param Closure(array<string, mixed>) : array<string, mixed> $build
	 * @param Closure(mixed, array<string, mixed>) : JsObject      $map
	 */
	private function defineForm(string $name, array $initial, Closure $build, Closure $map) : HostClass{
		$js = $this->js;
		$class = $this->f->define($name, null, function(array $args, JsObject $newTarget) use ($js, $initial, $name, &$class) : JsObject{
			$form = $this->f->instance($class, ["kind" => $name] + $initial);
			$form->proto = $js->prototypeFor($newTarget, $class->prototype);
			return $form;
		});
		$this->f->method($class, "show", function(mixed $thisValue, array $args) use ($name, $build, $map) : mixed{
			$host = $this->f->host($thisValue, $name);
			$player = $args[0] ?? null;
			if(!$player instanceof JsObject || ($player->host["kind"] ?? null) !== "entity" || ($player->host["typeId"] ?? null) !== "minecraft:player"){
				$this->js->throwError("TypeError", "Forms can only be shown to players");
			}
			return $this->runtime->showForm($player->host["id"], $build($host), function(mixed $raw) use ($map, $host) : JsObject{
				return $map($raw, $host);
			});
		}, 1);
		$this->exports[$name] = $class->constructor;
		return $class;
	}

	/**
	 * @param Closure(mixed, list<mixed>) : void $mutate
	 */
	private function builder(HostClass $class, string $method, Closure $mutate, int $length = 1) : void{
		$this->f->method($class, $method, function(mixed $thisValue, array $args) use ($class, $mutate) : mixed{
			$this->f->host($thisValue, $class->name);
			$mutate($thisValue, $args);
			return $thisValue;
		}, $length);
	}

	private function defineActionForm() : void{
		$class = $this->defineForm("ActionFormData", ["title" => "", "body" => "", "buttons" => [], "extra" => []], function(array $host) : array{
			$content = $host["body"];
			foreach($host["extra"] as $line){
				$content .= ($content === "" ? "" : "\n") . $line;
			}
			return ["type" => "form", "title" => $host["title"], "content" => $content, "buttons" => $host["buttons"]];
		}, function(mixed $raw, array $host) : JsObject{
			if(!is_int($raw) || $raw < 0 || $raw >= count($host["buttons"])){
				return $this->canceled($this->actionResponse);
			}
			return $this->response($this->actionResponse, ["canceled" => false, "selection" => $raw]);
		});
		$this->builder($class, "title", function(mixed $thisValue, array $args) : void{
			$thisValue->host["title"] = $this->server->text($args[0] ?? "");
		});
		$this->builder($class, "body", function(mixed $thisValue, array $args) : void{
			$thisValue->host["body"] = $this->server->text($args[0] ?? "");
		});
		$this->builder($class, "button", function(mixed $thisValue, array $args) : void{
			$button = ["text" => $this->server->text($args[0] ?? "")];
			$icon = $args[1] ?? null;
			if(is_string($icon) && $icon !== ""){
				$button["image"] = ["type" => str_starts_with($icon, "http") ? "url" : "path", "data" => $icon];
			}
			$thisValue->host["buttons"][] = $button;
		}, 2);
		$this->builder($class, "header", function(mixed $thisValue, array $args) : void{
			$thisValue->host["extra"][] = "§l" . $this->server->text($args[0] ?? "") . "§r";
		});
		$this->builder($class, "label", function(mixed $thisValue, array $args) : void{
			$thisValue->host["extra"][] = $this->server->text($args[0] ?? "");
		});
		$this->builder($class, "divider", function(mixed $thisValue, array $args) : void{
			$thisValue->host["extra"][] = "";
		}, 0);
	}

	private function defineMessageForm() : void{
		$class = $this->defineForm("MessageFormData", ["title" => "", "body" => "", "button1" => "", "button2" => ""], function(array $host) : array{
			return ["type" => "modal", "title" => $host["title"], "content" => $host["body"], "button1" => $host["button1"], "button2" => $host["button2"]];
		}, function(mixed $raw) : JsObject{
			if(!is_bool($raw)){
				return $this->canceled($this->messageResponse);
			}
			return $this->response($this->messageResponse, ["canceled" => false, "selection" => $raw ? 0 : 1]);
		});
		foreach(["title", "body", "button1", "button2"] as $field){
			$this->builder($class, $field, function(mixed $thisValue, array $args) use ($field) : void{
				$thisValue->host[$field] = $this->server->text($args[0] ?? "");
			});
		}
	}

	private function option(mixed $options, string $key) : mixed{
		return $options instanceof JsObject && !$options instanceof JsArray ? $this->js->get($options, $key) : null;
	}

	private function defineModalForm() : void{
		$js = $this->js;
		$class = $this->defineForm("ModalFormData", ["title" => "", "content" => [], "kinds" => [], "submit" => null], function(array $host) : array{
			$form = ["type" => "custom_form", "title" => $host["title"], "content" => $host["content"]];
			if($host["submit"] !== null){
				$form["submit"] = $host["submit"];
			}
			return $form;
		}, function(mixed $raw, array $host) use ($js) : JsObject{
			if(!is_array($raw)){
				return $this->canceled($this->modalResponse);
			}
			$values = [];
			foreach($host["kinds"] as $index => $kind){
				$value = $raw[$index] ?? null;
				$values[] = match($kind){
					"toggle" => is_bool($value) ? $value : null,
					"slider" => is_int($value) || is_float($value) ? Interpreter::intOrFloat((float) $value) : null,
					"dropdown" => is_int($value) ? $value : null,
					"input" => is_string($value) ? $value : null,
					default => null
				};
			}
			return $this->response($this->modalResponse, ["canceled" => false, "formValues" => $js->newArray($values)]);
		});
		$this->builder($class, "title", function(mixed $thisValue, array $args) : void{
			$thisValue->host["title"] = $this->server->text($args[0] ?? "");
		});
		$this->builder($class, "submitButton", function(mixed $thisValue, array $args) : void{
			$thisValue->host["submit"] = $this->server->text($args[0] ?? "");
		});
		$add = function(JsObject $form, string $kind, array $element) : void{
			$form->host["content"][] = $element;
			$form->host["kinds"][] = $kind;
		};
		$this->builder($class, "textField", function(mixed $thisValue, array $args) use ($add) : void{
			$options = $args[2] ?? null;
			$default = is_string($options) ? $options : $this->option($options, "defaultValue");
			$add($thisValue, "input", [
				"type" => "input",
				"text" => $this->server->text($args[0] ?? ""),
				"placeholder" => $this->server->text($args[1] ?? ""),
				"default" => $default === null ? "" : $this->server->text($default)
			]);
		}, 3);
		$this->builder($class, "toggle", function(mixed $thisValue, array $args) use ($add, $js) : void{
			$options = $args[1] ?? null;
			$default = is_bool($options) ? $options : $this->option($options, "defaultValue");
			$add($thisValue, "toggle", ["type" => "toggle", "text" => $this->server->text($args[0] ?? ""), "default" => $js->toBoolean($default)]);
		}, 2);
		$this->builder($class, "slider", function(mixed $thisValue, array $args) use ($add, $js) : void{
			$minimum = $js->toNumber($args[1] ?? 0);
			$maximum = $js->toNumber($args[2] ?? 0);
			$options = $args[3] ?? null;
			if(is_int($options) || is_float($options)){
				$step = $options;
				$default = $args[4] ?? null;
			}else{
				$step = $this->option($options, "valueStep");
				$default = $this->option($options, "defaultValue");
			}
			$add($thisValue, "slider", [
				"type" => "slider",
				"text" => $this->server->text($args[0] ?? ""),
				"min" => $minimum,
				"max" => $maximum,
				"step" => $step === null ? 1 : $js->toNumber($step),
				"default" => $default === null ? $minimum : $js->toNumber($default)
			]);
		}, 4);
		$this->builder($class, "dropdown", function(mixed $thisValue, array $args) use ($add, $js) : void{
			$items = [];
			$list = $args[1] ?? null;
			if($list instanceof JsArray){
				foreach($list->items as $item){
					$items[] = $this->server->text($item);
				}
			}
			$options = $args[2] ?? null;
			$default = is_int($options) ? $options : $this->option($options, "defaultValueIndex");
			$add($thisValue, "dropdown", [
				"type" => "dropdown",
				"text" => $this->server->text($args[0] ?? ""),
				"options" => $items,
				"default" => $default === null ? 0 : (int) $js->toNumber($default)
			]);
		}, 3);
		$this->builder($class, "label", function(mixed $thisValue, array $args) use ($add) : void{
			$add($thisValue, "label", ["type" => "label", "text" => $this->server->text($args[0] ?? "")]);
		});
		$this->builder($class, "header", function(mixed $thisValue, array $args) use ($add) : void{
			$add($thisValue, "label", ["type" => "label", "text" => "§l" . $this->server->text($args[0] ?? "") . "§r"]);
		});
		$this->builder($class, "divider", function(mixed $thisValue, array $args) use ($add) : void{
			$add($thisValue, "label", ["type" => "label", "text" => ""]);
		}, 0);
	}
}
