<?php

declare(strict_types=1);

namespace behaviorpack\script\binding;

use behaviorpack\script\js\Interpreter;
use behaviorpack\script\js\JsObject;
use Closure;
use function in_array;
use function is_array;
use function str_starts_with;

/**
 * Builds the host classes of the Minecraft modules. Reading a member that a
 * host class does not have is reported as an unimplemented API.
 */
final class ClassFactory{

	private const IGNORED = [
		"then", "catch", "finally", "toJSON", "inspect", "asymmetricMatch", "\$\$typeof", "nodeType", "tagName",
		"length", "name", "prototype", "valueOf", "toString", "constructor", "@@__IMMUTABLE_ITERABLE__@@"
	];

	/**
	 * @param Closure(string) : void $recordMissing
	 */
	public function __construct(
		private Interpreter $js,
		private Closure $recordMissing
	){}

	public function isIgnored(string $key) : bool{
		return in_array($key, self::IGNORED, true) || str_starts_with($key, "_") || str_starts_with($key, "#");
	}

	/**
	 * Returns a miss handler that records "$owner.$key" as unimplemented.
	 *
	 * @return Closure(JsObject, string) : mixed
	 */
	public function tracker(string $owner) : Closure{
		$record = $this->recordMissing;
		return function(JsObject $receiver, string $key) use ($owner, $record) : mixed{
			if(!$this->isIgnored($key)){
				$record($owner . "." . $key);
			}
			return null;
		};
	}

	/**
	 * Defines a class. Without $construct, "new" throws "Illegal constructor"
	 * as for the classes that scripts cannot instantiate.
	 *
	 * @param (Closure(list<mixed>, JsObject) : JsObject)|null $construct
	 */
	public function define(string $name, ?HostClass $parent = null, ?Closure $construct = null, int $length = 0) : HostClass{
		$js = $this->js;
		$prototype = new JsObject($parent?->prototype ?? $js->objectPrototype);
		$prototype->className = $name;
		$prototype->miss = $this->tracker($name);
		$construct ??= function(array $args, JsObject $newTarget) use ($js) : JsObject{
			$js->throwError("TypeError", "Illegal constructor");
		};
		$constructor = $js->makeClass($name, $prototype, $construct, null, $length, $parent?->constructor);
		$constructor->miss = $this->tracker($name);
		return new HostClass($name, $constructor, $prototype);
	}

	/**
	 * @param Closure(mixed, list<mixed>) : mixed $call
	 */
	public function method(HostClass $class, string $name, Closure $call, int $length = 0) : void{
		$this->js->defineMethod($class->prototype, $name, $length, $call);
	}

	/**
	 * @param Closure(mixed, list<mixed>) : mixed $call
	 */
	public function staticMethod(HostClass $class, string $name, Closure $call, int $length = 0) : void{
		$this->js->defineMethod($class->constructor, $name, $length, $call);
	}

	/**
	 * @param Closure(mixed) : mixed              $get
	 * @param (Closure(mixed, mixed) : void)|null $set
	 */
	public function getter(HostClass $class, string $name, Closure $get, ?Closure $set = null) : void{
		$this->js->defineGetter($class->prototype, $name, $get, $set);
	}

	public function staticValue(HostClass $class, string $name, mixed $value) : void{
		$this->js->defineHidden($class->constructor, $name, $value);
		$class->constructor->locked[$name] = true;
	}

	/**
	 * @param array<string, mixed> $host
	 */
	public function instance(HostClass $class, array $host) : JsObject{
		$object = new JsObject($class->prototype);
		$object->className = $class->name;
		$object->host = $host;
		return $object;
	}

	/**
	 * Returns the host data of $value when it is an object of the given
	 * kind, or throws a TypeError.
	 *
	 * @return array<string, mixed>
	 */
	public function host(mixed $value, string $kind) : array{
		if($value instanceof JsObject && is_array($value->host) && ($value->host["kind"] ?? null) === $kind){
			return $value->host;
		}
		$this->js->throwError("TypeError", "Illegal invocation: expected " . $kind);
	}

	/**
	 * @param array<string, mixed> $values
	 */
	public function enum(string $name, array $values) : JsObject{
		$object = $this->js->newObject();
		foreach($values as $key => $value){
			$object->props[$key] = $value;
			$object->locked[$key] = true;
		}
		$object->extensible = false;
		$object->miss = $this->tracker($name);
		return $object;
	}
}
