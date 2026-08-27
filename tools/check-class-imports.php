<?php

declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$altayRoot = $argv[1] ?? dirname($pluginRoot, 2);
$autoloadPath = $altayRoot . "/vendor/autoload.php";

if (!is_file($autoloadPath)) {
	fwrite(STDERR, "Altay autoloader not found: $autoloadPath\n");
	exit(2);
}

require $autoloadPath;

spl_autoload_register(static function (string $class) use ($pluginRoot) : void {
	$prefix = "VanillaAltay\\";
	if (!str_starts_with($class, $prefix)) {
		return;
	}

	$relativePath = str_replace("\\", DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
	$file = $pluginRoot . "/src/VanillaAltay/" . $relativePath . ".php";
	if (is_file($file)) {
		require_once $file;
	}
});

$sourceRoot = $pluginRoot . "/src";
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceRoot));
$errors = [];
$checked = 0;

foreach ($iterator as $file) {
	if (!$file->isFile() || $file->getExtension() !== "php") {
		continue;
	}

	$contents = file_get_contents($file->getPathname());
	if ($contents === false) {
		$errors[] = $file->getPathname() . ": unable to read file";
		continue;
	}

	preg_match_all('/^use\s+(?!function\s|const\s)([^;]+);/m', $contents, $matches, PREG_OFFSET_CAPTURE);
	foreach ($matches[1] as [$import, $offset]) {
		$class = preg_replace('/\s+as\s+.+$/i', '', trim($import));
		if ($class === null || $class === "") {
			continue;
		}

		++$checked;
		if (
			class_exists($class) ||
			interface_exists($class) ||
			trait_exists($class) ||
			(function_exists("enum_exists") && enum_exists($class))
		) {
			continue;
		}

		$line = substr_count($contents, "\n", 0, $offset) + 1;
		$relativeFile = str_replace($pluginRoot . DIRECTORY_SEPARATOR, "", $file->getPathname());
		$errors[] = "$relativeFile:$line: imported class does not exist: $class";
	}
}

if ($errors !== []) {
	fwrite(STDERR, implode("\n", $errors) . "\n");
	exit(1);
}

fwrite(STDOUT, "Checked $checked class imports: all valid.\n");
