<?php

declare(strict_types=1);

namespace dimension\generator;

use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use const JSON_THROW_ON_ERROR;

/**
 * The generator preset of the nether and end worlds. Generators run on worker
 * threads and cannot reach the plugin, so the plugin resource folder, where
 * the structure templates live, travels inside the preset string saved with
 * the world.
 */
final class GeneratorOptions{

	public static function encode(string $resourceFolder) : string{
		return json_encode(["resources" => $resourceFolder], JSON_THROW_ON_ERROR);
	}

	public static function resourceFolder(string $preset) : string{
		$decoded = json_decode($preset, true);
		if(is_array($decoded) && is_string($decoded["resources"] ?? null)){
			return $decoded["resources"];
		}
		return "";
	}
}
