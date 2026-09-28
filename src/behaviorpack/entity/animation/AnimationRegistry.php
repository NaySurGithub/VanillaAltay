<?php

declare(strict_types=1);

namespace behaviorpack\entity\animation;

/**
 * Holds the behavior pack animation controllers and animations, by name.
 */
final class AnimationRegistry{

	/** @var array<string, array<mixed>> */
	private static array $controllers = [];

	/** @var array<string, array<mixed>> */
	private static array $animations = [];

	private function __construct(){
	}

	/**
	 * @param array<mixed> $controller
	 */
	public static function registerController(string $name, array $controller) : void{
		self::$controllers[$name] = $controller;
	}

	/**
	 * @param array<mixed> $animation
	 */
	public static function registerAnimation(string $name, array $animation) : void{
		self::$animations[$name] = $animation;
	}

	/**
	 * @return array<mixed>|null
	 */
	public static function controller(string $name) : ?array{
		return self::$controllers[$name] ?? null;
	}

	/**
	 * @return array<mixed>|null
	 */
	public static function animation(string $name) : ?array{
		return self::$animations[$name] ?? null;
	}

	public static function clear() : void{
		self::$controllers = [];
		self::$animations = [];
	}
}
