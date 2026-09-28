<?php

declare(strict_types=1);

namespace behaviorpack\script\binding;

use behaviorpack\script\js\Interpreter;
use behaviorpack\script\js\JsArray;
use behaviorpack\script\js\JsObject;
use behaviorpack\script\ScriptRuntime;
use Closure;
use function count;
use function implode;
use function in_array;
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

	/** @var array<int, array{player: int, promise: \behaviorpack\script\js\JsPromise, bindings: array<string, Closure(mixed, string) : void>, watched: list<array{0: JsObject, 1: string, 2: string}>, form: JsObject, pack: ?string}> */
	private array $screens = [];

	private int $nextScreen = 1;

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
		$this->defineObservables();
		$this->defineCustomForm();
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

	/**
	 * Observable values bound to the fields of a CustomForm: the form reads
	 * them when shown and writes the values the player submitted.
	 */
	private function defineObservables() : void{
		$js = $this->js;
		$observable = null;
		foreach(["Observable", "ObservableString", "ObservableNumber", "ObservableBoolean"] as $name){
			$class = $this->defineObservableClass($name, $observable);
			if($observable === null){
				$observable = $class;
				$this->f->method($class, "getData", function(mixed $thisValue, array $args) : mixed{
					return $this->f->host($thisValue, "observable")["value"];
				});
				$this->f->method($class, "setData", function(mixed $thisValue, array $args) : mixed{
					$this->setObservable($thisValue, $args[0] ?? null);
					return null;
				}, 1);
				$this->f->method($class, "subscribe", function(mixed $thisValue, array $args) use ($js) : mixed{
					$this->f->host($thisValue, "observable");
					$callback = $args[0] ?? null;
					if(!$callback instanceof \behaviorpack\script\js\JsCallable){
						$js->throwError("TypeError", "The callback must be a function");
					}
					$thisValue->host["subscribers"][] = $callback;
					return $callback;
				}, 1);
				$this->f->method($class, "unsubscribe", function(mixed $thisValue, array $args) : mixed{
					$this->f->host($thisValue, "observable");
					$kept = [];
					$removed = false;
					foreach($thisValue->host["subscribers"] as $subscriber){
						if($subscriber === ($args[0] ?? null)){
							$removed = true;
							continue;
						}
						$kept[] = $subscriber;
					}
					$thisValue->host["subscribers"] = $kept;
					return $removed;
				}, 1);
				$this->f->staticMethod($class, "create", function(mixed $thisValue, array $args) use ($js) : mixed{
					$constructor = $thisValue instanceof JsObject ? $thisValue : $this->exports["Observable"];
					return $js->construct($constructor, $args);
				}, 2);
			}
			$this->exports[$name] = $class->constructor;
		}
	}

	private function defineObservableClass(string $name, ?HostClass $parent) : HostClass{
		$js = $this->js;
		$class = null;
		$class = $this->f->define($name, $parent, function(array $args, JsObject $newTarget) use ($js, &$class) : JsObject{
			$options = $args[1] ?? null;
			$instance = $this->f->instance($class, [
				"kind" => "observable",
				"value" => $args[0] ?? null,
				"writable" => $options instanceof JsObject && $js->toBoolean($js->get($options, "clientWritable")),
				"subscribers" => [],
				"listeners" => []
			]);
			$instance->proto = $js->prototypeFor($newTarget, $class->prototype);
			return $instance;
		}, 2);
		return $class;
	}

	/**
	 * Changes an observable, notifies the script subscribers and the open
	 * screens bound to it, except the one the change came from.
	 */
	private function setObservable(JsObject $observable, mixed $value, ?string $origin = null) : void{
		$this->f->host($observable, "observable");
		if($observable->host["value"] === $value){
			return;
		}
		$observable->host["value"] = $value;
		foreach($observable->host["listeners"] as $key => $listener){
			if($key !== $origin){
				$listener($value);
			}
		}
		foreach($observable->host["subscribers"] as $subscriber){
			$this->js->call($subscriber, null, [$value]);
		}
	}

	private function observableValue(mixed $value) : mixed{
		if($value instanceof JsObject && ($value->host["kind"] ?? null) === "observable"){
			return $value->host["value"];
		}
		return $value;
	}

	private function defineCustomForm() : void{
		$js = $this->js;
		$class = $this->f->define("CustomForm", null, function(array $args, JsObject $newTarget) use ($js, &$class) : JsObject{
			$player = $args[0] ?? null;
			if(!$player instanceof JsObject || ($player->host["typeId"] ?? null) !== "minecraft:player"){
				$js->throwError("TypeError", "Forms can only be shown to players");
			}
			$form = $this->f->instance($class, ["kind" => "CustomForm", "player" => $player->host["id"], "title" => $this->server->text($this->observableValue($args[1] ?? "")), "titleSource" => $args[1] ?? null, "elements" => [], "showing" => false]);
			$form->proto = $js->prototypeFor($newTarget, $class->prototype);
			return $form;
		}, 2);
		$this->f->staticMethod($class, "create", function(mixed $thisValue, array $args) use ($js, $class) : mixed{
			return $js->construct($class->constructor, $args);
		}, 2);
		$element = function(string $method, string $type, int $length, \Closure $build) use ($class) : void{
			$this->builder($class, $method, function(mixed $thisValue, array $args) use ($type, $build) : void{
				$thisValue->host["elements"][] = ["type" => $type, "source" => $args[0] ?? null] + $build($args);
			}, $length);
		};
		$description = function(mixed $options) : string{
			if(!$options instanceof JsObject){
				return "";
			}
			$value = $this->js->get($options, "description");
			return $value === null ? "" : $this->server->text($value);
		};
		$element("label", "label", 1, fn(array $args) => ["text" => $this->server->text($this->observableValue($args[0] ?? ""))]);
		$element("header", "header", 1, fn(array $args) => ["text" => $this->server->text($this->observableValue($args[0] ?? ""))]);
		$element("divider", "divider", 0, fn(array $args) => ["text" => ""]);
		$element("spacer", "spacer", 0, fn(array $args) => ["text" => ""]);
		$element("button", "button", 3, fn(array $args) => ["text" => $this->server->text($this->observableValue($args[0] ?? "")), "callback" => $args[1] ?? null, "tooltip" => $description($args[2] ?? null)]);
		$element("closeButton", "close", 1, fn(array $args) => ["text" => ($args[0] ?? null) === null ? "" : $this->server->text($this->observableValue($args[0])), "callback" => null]);
		$element("textField", "input", 3, fn(array $args) => ["text" => $this->server->text($this->observableValue($args[0] ?? "")), "value" => $args[1] ?? null, "description" => $description($args[2] ?? null)]);
		$element("toggle", "toggle", 3, fn(array $args) => ["text" => $this->server->text($this->observableValue($args[0] ?? "")), "value" => $args[1] ?? null, "description" => $description($args[2] ?? null)]);
		$element("slider", "slider", 5, fn(array $args) => ["text" => $this->server->text($this->observableValue($args[0] ?? "")), "value" => $args[1] ?? null, "min" => $args[2] ?? 0, "max" => $args[3] ?? 100, "options" => $args[4] ?? null]);
		$element("dropdown", "dropdown", 4, fn(array $args) => ["text" => $this->server->text($this->observableValue($args[0] ?? "")), "value" => $args[1] ?? null, "items" => $args[2] ?? null, "description" => $description($args[3] ?? null)]);

		$this->f->method($class, "isShowing", function(mixed $thisValue, array $args) : mixed{
			return $this->f->host($thisValue, "CustomForm")["showing"];
		});
		$this->f->method($class, "close", function(mixed $thisValue, array $args) : mixed{
			$host = $this->f->host($thisValue, "CustomForm");
			$formId = $host["formId"] ?? null;
			if($host["showing"] && is_int($formId)){
				$this->runtime->api->handle("x.ddui.close", [$host["player"], $formId]);
				$this->closeScreen($formId);
			}
			return null;
		});
		$this->f->method($class, "show", function(mixed $thisValue, array $args) : mixed{
			$host = $this->f->host($thisValue, "CustomForm");
			$formId = $this->nextScreen++;
			$promise = $this->js->newPromise();
			[$state, $bindings, $watched] = $this->compileScreen($host);
			$this->screens[$formId] = [
				"player" => $host["player"],
				"promise" => $promise,
				"bindings" => $bindings,
				"watched" => $watched,
				"form" => $thisValue,
				"pack" => $this->js->context
			];
			$thisValue->host["showing"] = true;
			$thisValue->host["formId"] = $formId;
			foreach($watched as [$observable, $path, $type]){
				$player = $host["player"];
				$observable->host["listeners"][$formId . ":" . $path] = function(mixed $value) use ($player, $path, $type, $formId) : void{
					if(isset($this->screens[$formId])){
						$this->runtime->api->handle("x.ddui.update", [$player, [[$path, self::screenValue($type, $value)]]]);
					}
				};
			}
			try{
				$this->runtime->api->handle("x.ddui.show", [$host["player"], $formId, $state]);
			}catch(\behaviorpack\script\ScriptException $e){
				$this->closeScreen($formId);
				$this->js->rejectPromise($promise, $this->js->makeError("Error", $e->getMessage()));
			}
			return $promise;
		});
		$this->exports["CustomForm"] = $class->constructor;
		$this->runtime->screenHandler = function(array $data) : void{
			$this->handleScreenMessage($data);
		};
	}

	private static function screenValue(string $type, mixed $value) : mixed{
		return match($type){
			"bool" => $value === true,
			"number" => is_int($value) || is_float($value) ? (int) $value : 0,
			default => is_string($value) ? $value : (is_int($value) || is_float($value) ? (string) $value : "")
		};
	}

	/**
	 * Builds the initial state of a data driven custom form, the actions
	 * bound to the paths the client writes, and the observables to follow.
	 *
	 * @param array<string, mixed> $host
	 * @return array{0: array<string, mixed>, 1: array<string, \Closure(mixed, string) : void>, 2: list<array{0: JsObject, 1: string, 2: string}>}
	 */
	private function compileScreen(array $host) : array{
		$bindings = [];
		$watched = [];
		$watch = function(mixed $source, string $path, string $type) use (&$watched) : void{
			if($source instanceof JsObject && ($source->host["kind"] ?? null) === "observable"){
				$watched[] = [$source, $path, $type];
			}
		};
		$layout = [];
		$state = ["title" => $host["title"]];
		$watch($host["titleSource"] ?? null, "title", "string");
		$index = 0;
		foreach($host["elements"] as $element){
			$type = $element["type"];
			if($type === "close"){
				$state["closeButton"] = ["label" => $element["text"], "visible" => true, "button_visible" => true, "onClick" => 0];
				$bindings["closeButton.onClick"] = function(mixed $value, string $key) use ($host) : void{
					$formId = (int) \explode(":", $key)[0];
					$this->runtime->api->handle("x.ddui.close", [$host["player"], $formId]);
					$this->closeScreen($formId);
				};
				continue;
			}
			$path = "layout[" . $index . "]";
			$data = match($type){
				"label" => ["text" => $element["text"], "visible" => true, "label_visible" => true],
				"header" => ["text" => $element["text"], "visible" => true, "header_visible" => true],
				"divider" => ["visible" => true, "divider_visible" => true],
				"spacer" => ["visible" => true, "spacer_visible" => true],
				"button" => ["label" => $element["text"], "visible" => true, "button_visible" => true, "disabled" => false, "tooltip" => $element["tooltip"] ?? "", "onClick" => 0],
				"input" => ["label" => $element["text"], "description" => $element["description"], "visible" => true, "textfield_visible" => true, "disabled" => false, "text" => self::screenValue("string", $this->observableValue($element["value"]))],
				"toggle" => ["label" => $element["text"], "description" => $element["description"], "visible" => true, "toggle_visible" => true, "disabled" => false, "toggled" => $this->observableValue($element["value"]) === true],
				"slider" => [
					"label" => $element["text"],
					"description" => "",
					"visible" => true,
					"slider_visible" => true,
					"disabled" => false,
					"minValue" => (int) $this->js->toNumber($this->observableValue($element["min"])),
					"maxValue" => (int) $this->js->toNumber($this->observableValue($element["max"])),
					"step" => 1,
					"value" => self::screenValue("number", $this->observableValue($element["value"]))
				],
				"dropdown" => [
					"label" => $element["text"],
					"description" => $element["description"],
					"visible" => true,
					"dropdown_visible" => true,
					"disabled" => false,
					"value" => self::screenValue("number", $this->observableValue($element["value"])),
					"items" => $this->dropdownItems($element["items"])
				],
				default => ["visible" => true]
			};
			$layout[(string) $index] = $data;
			if($type === "label" || $type === "header"){
				$watch($element["source"], $path . ".text", "string");
			}elseif($type === "button"){
				$callback = $element["callback"];
				$bindings[$path . ".onClick"] = function(mixed $value, string $key) use ($callback) : void{
					if($callback instanceof \behaviorpack\script\js\JsCallable){
						$this->js->call($callback, null, []);
					}
				};
			}elseif($type === "input" || $type === "toggle" || $type === "slider" || $type === "dropdown"){
				$field = match($type){
					"input" => "text",
					"toggle" => "toggled",
					default => "value"
				};
				$valueType = match($type){
					"input" => "string",
					"toggle" => "bool",
					default => "number"
				};
				$observable = $element["value"];
				$watch($observable, $path . "." . $field, $valueType);
				$bindings[$path . "." . $field] = function(mixed $value, string $key) use ($observable, $valueType) : void{
					if(!$observable instanceof JsObject || ($observable->host["kind"] ?? null) !== "observable"){
						return;
					}
					$converted = match($valueType){
						"bool" => $value === true,
						"number" => is_int($value) || is_float($value) ? Interpreter::intOrFloat((float) $value) : 0,
						default => is_string($value) ? $value : ""
					};
					$this->setObservable($observable, $converted, $key);
				};
			}
			$index++;
		}
		$layout["length"] = $index;
		$state["layout"] = $layout;
		return [$state, $bindings, $watched];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function dropdownItems(mixed $items) : array{
		$result = [];
		$count = 0;
		if($items instanceof JsArray){
			foreach($items->items as $item){
				if($item instanceof JsObject && !$item instanceof JsArray){
					$value = $this->js->get($item, "value");
					$description = $this->js->get($item, "description");
					$result[(string) $count] = [
						"label" => $this->server->text($this->js->get($item, "label") ?? ""),
						"description" => $description === null ? "" : $this->server->text($description),
						"value" => is_int($value) || is_float($value) ? (int) $value : $count
					];
				}else{
					$result[(string) $count] = ["label" => $this->server->text($item), "description" => "", "value" => $count];
				}
				$count++;
			}
		}
		$result["length"] = $count;
		return $result;
	}

	/**
	 * Handles what the client sent about a data driven screen: a written
	 * value, the screen being closed, or the player leaving.
	 *
	 * @param array<string, mixed> $data
	 */
	private function handleScreenMessage(array $data) : void{
		$player = (int) ($data["p"] ?? 0);
		if(isset($data["quit"])){
			foreach($this->screens as $formId => $screen){
				if($screen["player"] === $player){
					$this->closeScreen($formId);
				}
			}
			return;
		}
		if(isset($data["closed"])){
			$this->closeScreen((int) $data["closed"]);
			return;
		}
		$path = (string) ($data["path"] ?? "");
		foreach($this->screens as $formId => $screen){
			if($screen["player"] !== $player || !isset($screen["bindings"][$path])){
				continue;
			}
			$binding = $screen["bindings"][$path];
			$value = $data["v"] ?? null;
			$this->runtime->run($screen["pack"], function() use ($binding, $value, $formId, $path) : void{
				$binding($value, $formId . ":" . $path);
			});
			return;
		}
	}

	private function closeScreen(int $formId) : void{
		$screen = $this->screens[$formId] ?? null;
		if($screen === null){
			return;
		}
		unset($this->screens[$formId]);
		foreach($screen["watched"] as [$observable, $path]){
			unset($observable->host["listeners"][$formId . ":" . $path]);
		}
		$screen["form"]->host["showing"] = false;
		$this->runtime->run($screen["pack"], function() use ($screen) : void{
			$this->js->resolvePromise($screen["promise"], null);
		});
	}

}
