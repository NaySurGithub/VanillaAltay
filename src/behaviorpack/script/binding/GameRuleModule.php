<?php

declare(strict_types=1);

namespace behaviorpack\script\binding;

use behaviorpack\script\api\GameRuleApi;
use behaviorpack\script\js\Interpreter;
use behaviorpack\script\js\JsArray;
use behaviorpack\script\js\JsObject;
use behaviorpack\script\ScriptException;
use behaviorpack\script\ScriptRuntime;
use function is_bool;

/**
 * world.gameRules, the music methods of World and Player, and
 * ScreenDisplay.clearTitle().
 */
final class GameRuleModule{

	private Interpreter $js;

	public function __construct(
		private ScriptRuntime $runtime,
		private ClassFactory $f,
		private ServerModule $s
	){
		$this->js = $runtime->js;
	}

	public function define() : void{
		$this->defineGameRules();
		$this->defineMusic();
		$this->defineScreen();
	}

	private function call(string $method, mixed ...$args) : mixed{
		try{
			return $this->s->raw($method, ...$args);
		}catch(ScriptException $e){
			$this->js->throwError("Error", $e->getMessage());
		}
	}

	private function defineGameRules() : void{
		$f = $this->f;
		$js = $this->js;
		$rules = $f->define("GameRules");
		foreach(GameRuleApi::DEFAULTS as $name => $default){
			$f->getter($rules, $name, function(mixed $thisValue) use ($name) : mixed{
				$this->f->host($thisValue, "gamerules");
				return $this->call("rule.get", $name);
			}, function(mixed $thisValue, mixed $value) use ($name, $default, $js) : void{
				$this->f->host($thisValue, "gamerules");
				if(is_bool($default)){
					if(!is_bool($value)){
						$js->throwError("TypeError", "Game rule " . $name . " expects a boolean");
					}
					$this->call("rule.set", $name, $value);
					return;
				}
				$number = $js->toNumber($value);
				if($number !== $number || $number < 0){
					$js->throwError("RangeError", "Game rule " . $name . " expects a non-negative number");
				}
				$this->call("rule.set", $name, (int) $number);
			});
		}
		$this->s->export("GameRules", $rules);
		$this->s->worldObject->props["gameRules"] = $f->instance($rules, ["kind" => "gamerules"]);
		$this->s->worldObject->locked["gameRules"] = true;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function musicOptions(mixed $value) : array{
		if(!$value instanceof JsObject || $value instanceof JsArray){
			return [];
		}
		$options = [];
		foreach(["fade", "volume"] as $key){
			$option = $this->js->get($value, $key);
			if($option !== null){
				$options[$key] = $this->js->toNumber($option);
			}
		}
		$loop = $this->js->get($value, "loop");
		if($loop !== null){
			$options["loop"] = $this->js->toBoolean($loop);
		}
		return $options;
	}

	private function defineMusic() : void{
		$f = $this->f;
		$playerId = function(mixed $thisValue) : int{
			return $this->f->host($thisValue, "entity")["id"];
		};
		foreach(["playMusic" => "rule.music.play", "queueMusic" => "rule.music.queue"] as $name => $method){
			$f->method($this->s->world, $name, function(mixed $thisValue, array $args) use ($method) : mixed{
				$this->call($method, null, $this->js->toString($args[0] ?? ""), $this->musicOptions($args[1] ?? null));
				return null;
			}, 2);
			$f->method($this->s->player, $name, function(mixed $thisValue, array $args) use ($method, $playerId) : mixed{
				$this->call($method, $playerId($thisValue), $this->js->toString($args[0] ?? ""), $this->musicOptions($args[1] ?? null));
				return null;
			}, 2);
		}
		$f->method($this->s->world, "stopMusic", function(mixed $thisValue, array $args) : mixed{
			$this->call("rule.music.stop", null);
			return null;
		});
		$f->method($this->s->player, "stopMusic", function(mixed $thisValue, array $args) use ($playerId) : mixed{
			$this->call("rule.music.stop", $playerId($thisValue));
			return null;
		});
	}

	private function defineScreen() : void{
		$this->f->method($this->s->screenDisplay, "clearTitle", function(mixed $thisValue, array $args) : mixed{
			$this->call("rule.clearTitle", $this->f->host($thisValue, "screen")["player"]);
			return null;
		});
	}
}
