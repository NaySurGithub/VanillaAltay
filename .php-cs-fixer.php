<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
	->in(__DIR__ . "/src");

return (new PhpCsFixer\Config())
	->setRiskyAllowed(true)
	->setRules([
		"@PER-CS2x0" => true,
		"array_indentation" => true,
		"array_syntax" => ["syntax" => "short"],
		"binary_operator_spaces" => ["default" => "single_space"],
		"blank_line_after_namespace" => true,
		"blank_line_after_opening_tag" => true,
		"cast_spaces" => ["space" => "single"],
		"class_attributes_separation" => [
			"elements" => [
				"const" => "one",
				"method" => "one",
				"property" => "one",
				"trait_import" => "none",
			],
		],
		"concat_space" => ["spacing" => "one"],
		"declare_strict_types" => true,
		"fully_qualified_strict_types" => true,
		"global_namespace_import" => [
			"import_classes" => null,
			"import_constants" => true,
			"import_functions" => true,
		],
		"indentation_type" => true,
		"native_constant_invocation" => ["scope" => "namespaced"],
		"native_function_invocation" => [
			"scope" => "namespaced",
			"include" => ["@all"],
		],
		"no_unused_imports" => true,
		"ordered_imports" => [
			"imports_order" => ["class", "function", "const"],
			"sort_algorithm" => "alpha",
		],
		"return_type_declaration" => ["space_before" => "one"],
		"single_import_per_statement" => true,
		"single_class_element_per_statement" => true,
		"single_line_empty_body" => false,
		"strict_param" => true,
	])
	->setFinder($finder)
	->setIndent("\t")
	->setLineEnding("\n");
