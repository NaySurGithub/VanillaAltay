<?php

declare(strict_types=1);

namespace behaviorpack\script\js;

use function array_key_last;
use function array_merge;
use function array_pop;
use function count;
use function in_array;
use function is_array;
use function is_string;

/**
 * Builds the syntax tree of a JavaScript module. Nodes are arrays with a
 * "type" key, close to ESTree. Functions are "Function" nodes wrapped by
 * FunctionDeclaration, FunctionExpression and ArrowFunctionExpression; they
 * carry the names of their var declarations ("vars") and whether they use
 * "arguments". Blocks, switch statements, function bodies and programs carry
 * their lexical declarations ("lex") and function declarations ("funcs").
 */
final class Parser{

	private const BINARY_PRECEDENCE = [
		"??" => 1,
		"||" => 2,
		"&&" => 3,
		"|" => 4,
		"^" => 5,
		"&" => 6,
		"==" => 7, "!=" => 7, "===" => 7, "!==" => 7,
		"<" => 8, ">" => 8, "<=" => 8, ">=" => 8, "instanceof" => 8, "in" => 8,
		"<<" => 9, ">>" => 9, ">>>" => 9,
		"+" => 10, "-" => 10,
		"*" => 11, "/" => 11, "%" => 11,
		"**" => 12
	];

	private const ASSIGNMENT_OPERATORS = ["=", "+=", "-=", "*=", "/=", "%=", "**=", "<<=", ">>=", ">>>=", "&=", "|=", "^=", "&&=", "||=", "??="];

	private const RESERVED = [
		"break", "case", "catch", "class", "const", "continue", "debugger", "default", "delete", "do", "else", "export",
		"extends", "finally", "for", "function", "if", "import", "in", "instanceof", "new", "return", "super", "switch",
		"this", "throw", "try", "typeof", "var", "void", "while", "with", "null", "true", "false", "enum"
	];

	/** @var list<array<string, mixed>> */
	private array $tokens;
	private int $index = 0;

	/** @var list<array{vars: array<string, true>, args: bool, arrow: bool, async: bool, generator: bool}> */
	private array $functions = [];

	public function __construct(string $source, private string $file, int $line = 1, private bool $module = true){
		$this->tokens = (new Lexer($source, $file, $line))->tokenize();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function parseProgram() : array{
		$this->functions[] = ["vars" => [], "args" => false, "arrow" => false, "async" => $this->module, "generator" => false];
		$body = [];
		while(!$this->isEof()){
			$body[] = $this->parseStatement(true);
		}
		$context = array_pop($this->functions);
		$program = ["type" => "Program", "body" => $body, "vars" => self::keys($context["vars"]), "line" => 1];
		return $this->annotate($program, $body);
	}

	/**
	 * Parses a lone expression, used for template substitutions.
	 *
	 * @param list<array{vars: array<string, true>, args: bool, arrow: bool, async: bool, generator: bool}> $functions
	 * @return array<string, mixed>
	 */
	public static function parseEmbeddedExpression(string $source, string $file, int $line, array &$functions) : array{
		$parser = new self($source, $file, $line);
		$parser->functions = $functions;
		$expression = $parser->parseExpression();
		if(!$parser->isEof()){
			throw $parser->error("Unexpected token in template substitution");
		}
		$functions = $parser->functions;
		return $expression;
	}

	/**
	 * @param array<string, true> $set
	 * @return list<string>
	 */
	private static function keys(array $set) : array{
		$keys = [];
		foreach($set as $key => $unused){
			$keys[] = (string) $key;
		}
		return $keys;
	}

	/**
	 * @param array<string, mixed>       $node
	 * @param list<array<string, mixed>> $statements
	 * @return array<string, mixed>
	 */
	private function annotate(array $node, array $statements) : array{
		$lex = [];
		$funcs = [];
		foreach($statements as $statement){
			$this->collectDeclarations($statement, $lex, $funcs);
		}
		$node["lex"] = $lex;
		$node["funcs"] = $funcs;
		return $node;
	}

	/**
	 * @param array<string, mixed>       $statement
	 * @param list<array{string, string}> $lex
	 * @param list<array<string, mixed>> $funcs
	 */
	private function collectDeclarations(array $statement, array &$lex, array &$funcs) : void{
		$type = $statement["type"];
		if($type === "ExportNamedDeclaration" || $type === "ExportDefaultDeclaration"){
			$declaration = $statement["declaration"] ?? null;
			if(is_array($declaration)){
				$this->collectDeclarations($declaration, $lex, $funcs);
			}
			return;
		}
		if($type === "VariableDeclaration" && $statement["kind"] !== "var"){
			foreach($statement["declarations"] as $declarator){
				foreach(self::patternNames($declarator["id"]) as $name){
					$lex[] = [$name, $statement["kind"]];
				}
			}
		}elseif($type === "ClassDeclaration" && $statement["class"]["id"] !== null){
			$lex[] = [$statement["class"]["id"], "let"];
		}elseif($type === "FunctionDeclaration"){
			$funcs[] = $statement;
		}
	}

	/**
	 * @param array<string, mixed> $pattern
	 * @return list<string>
	 */
	public static function patternNames(array $pattern) : array{
		switch($pattern["type"]){
			case "Identifier":
				return [$pattern["name"]];
			case "AssignmentPattern":
				return self::patternNames($pattern["left"]);
			case "RestElement":
				return self::patternNames($pattern["argument"]);
			case "ArrayPattern":
				$names = [];
				foreach($pattern["elements"] as $element){
					if($element !== null){
						$names = array_merge($names, self::patternNames($element));
					}
				}
				return $names;
			case "ObjectPattern":
				$names = [];
				foreach($pattern["properties"] as $property){
					$names = array_merge($names, self::patternNames($property["type"] === "RestElement" ? $property : $property["value"]));
				}
				return $names;
			default:
				return [];
		}
	}

	private function error(string $message, ?array $token = null) : SyntaxErrorException{
		$token ??= $this->peek();
		return new SyntaxErrorException($message . " (" . $this->file . ":" . $token["l"] . ")");
	}

	/**
	 * @return array<string, mixed>
	 */
	private function peek(int $offset = 0) : array{
		return $this->tokens[$this->index + $offset] ?? $this->tokens[array_key_last($this->tokens)];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function next() : array{
		$token = $this->peek();
		if($token["t"] !== "eof"){
			$this->index++;
		}
		return $token;
	}

	private function isEof() : bool{
		return $this->peek()["t"] === "eof";
	}

	private function is(string $punctuator, int $offset = 0) : bool{
		$token = $this->peek($offset);
		return $token["t"] === "punc" && $token["v"] === $punctuator;
	}

	private function isName(string $name, int $offset = 0) : bool{
		$token = $this->peek($offset);
		return $token["t"] === "name" && $token["v"] === $name;
	}

	private function eat(string $punctuator) : bool{
		if($this->is($punctuator)){
			$this->index++;
			return true;
		}
		return false;
	}

	private function expect(string $punctuator) : void{
		if(!$this->eat($punctuator)){
			$token = $this->peek();
			throw $this->error("Expected '" . $punctuator . "' but found '" . self::describe($token) . "'");
		}
	}

	private function expectName(string $name) : void{
		if(!$this->isName($name)){
			throw $this->error("Expected '" . $name . "' but found '" . self::describe($this->peek()) . "'");
		}
		$this->index++;
	}

	/**
	 * @param array<string, mixed> $token
	 */
	private static function describe(array $token) : string{
		if($token["t"] === "eof"){
			return "end of input";
		}
		return is_string($token["v"]) ? $token["v"] : $token["t"];
	}

	private function identifier() : string{
		$token = $this->peek();
		if($token["t"] !== "name" || in_array($token["v"], self::RESERVED, true)){
			throw $this->error("Unexpected token '" . self::describe($token) . "'");
		}
		$this->index++;
		return $token["v"];
	}

	private function propertyName() : string{
		$token = $this->next();
		if($token["t"] === "name"){
			return $token["v"];
		}
		throw $this->error("Expected a property name", $token);
	}

	private function consumeSemicolon() : void{
		if($this->eat(";")){
			return;
		}
		$token = $this->peek();
		if($token["t"] === "eof" || $token["n"] || ($token["t"] === "punc" && $token["v"] === "}")){
			return;
		}
		throw $this->error("Unexpected token '" . self::describe($token) . "'");
	}

	private function line() : int{
		return $this->peek()["l"];
	}

	private function &context() : array{
		return $this->functions[count($this->functions) - 1];
	}

	private function markArguments() : void{
		for($i = count($this->functions) - 1; $i >= 0; --$i){
			if(!$this->functions[$i]["arrow"]){
				$this->functions[$i]["args"] = true;
				return;
			}
		}
	}

	private function inAsync() : bool{
		return $this->functions[count($this->functions) - 1]["async"];
	}

	private function inGenerator() : bool{
		return $this->functions[count($this->functions) - 1]["generator"];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseStatement(bool $topLevel = false) : array{
		$token = $this->peek();
		$line = $token["l"];
		if($token["t"] === "punc"){
			switch($token["v"]){
				case "{":
					return $this->parseBlock();
				case ";":
					$this->index++;
					return ["type" => "EmptyStatement", "line" => $line];
			}
		}elseif($token["t"] === "name"){
			switch($token["v"]){
				case "var":
				case "const":
					$declaration = $this->parseVariableDeclaration(false);
					$this->consumeSemicolon();
					return $declaration;
				case "let":
					$next = $this->peek(1);
					if($next["t"] === "name" || ($next["t"] === "punc" && ($next["v"] === "[" || $next["v"] === "{"))){
						$declaration = $this->parseVariableDeclaration(false);
						$this->consumeSemicolon();
						return $declaration;
					}
					break;
				case "function":
					return ["type" => "FunctionDeclaration", "fn" => $this->parseFunction(false, true), "line" => $line];
				case "async":
					if($this->isName("function", 1) && !$this->peek(1)["n"]){
						$this->index++;
						return ["type" => "FunctionDeclaration", "fn" => $this->parseFunction(true, true), "line" => $line];
					}
					break;
				case "class":
					return ["type" => "ClassDeclaration", "class" => $this->parseClass(true), "line" => $line];
				case "if":
					return $this->parseIf();
				case "for":
					return $this->parseFor();
				case "while":
					$this->index++;
					$this->expect("(");
					$test = $this->parseExpression();
					$this->expect(")");
					return ["type" => "WhileStatement", "test" => $test, "body" => $this->parseStatement(), "line" => $line];
				case "do":
					$this->index++;
					$body = $this->parseStatement();
					$this->expectName("while");
					$this->expect("(");
					$test = $this->parseExpression();
					$this->expect(")");
					$this->eat(";");
					return ["type" => "DoWhileStatement", "test" => $test, "body" => $body, "line" => $line];
				case "return":
					$this->index++;
					$argument = null;
					if(!$this->is(";") && !$this->is("}") && !$this->peek()["n"] && !$this->isEof()){
						$argument = $this->parseExpression();
					}
					$this->consumeSemicolon();
					return ["type" => "ReturnStatement", "argument" => $argument, "line" => $line];
				case "break":
				case "continue":
					$this->index++;
					$label = null;
					if($this->peek()["t"] === "name" && !$this->peek()["n"] && !in_array($this->peek()["v"], self::RESERVED, true)){
						$label = $this->next()["v"];
					}
					$this->consumeSemicolon();
					return ["type" => $token["v"] === "break" ? "BreakStatement" : "ContinueStatement", "label" => $label, "line" => $line];
				case "throw":
					$this->index++;
					if($this->peek()["n"]){
						throw $this->error("Illegal newline after throw");
					}
					$argument = $this->parseExpression();
					$this->consumeSemicolon();
					return ["type" => "ThrowStatement", "argument" => $argument, "line" => $line];
				case "try":
					return $this->parseTry();
				case "switch":
					return $this->parseSwitch();
				case "debugger":
					$this->index++;
					$this->consumeSemicolon();
					return ["type" => "EmptyStatement", "line" => $line];
				case "import":
					if($topLevel && !$this->is("(", 1) && !$this->is(".", 1)){
						return $this->parseImport();
					}
					break;
				case "export":
					if($topLevel){
						return $this->parseExport();
					}
					throw $this->error("Unexpected export");
				default:
					if($this->is(":", 1) && !in_array($token["v"], self::RESERVED, true)){
						$this->index += 2;
						return ["type" => "LabeledStatement", "label" => $token["v"], "body" => $this->parseStatement(), "line" => $line];
					}
			}
		}
		$expression = $this->parseExpression();
		$this->consumeSemicolon();
		return ["type" => "ExpressionStatement", "expression" => $expression, "line" => $line];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseBlock() : array{
		$line = $this->line();
		$this->expect("{");
		$body = [];
		while(!$this->eat("}")){
			if($this->isEof()){
				throw $this->error("Unexpected end of input");
			}
			$body[] = $this->parseStatement();
		}
		return $this->annotate(["type" => "BlockStatement", "body" => $body, "line" => $line], $body);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseVariableDeclaration(bool $noIn) : array{
		$line = $this->line();
		$kind = $this->next()["v"];
		$declarations = [];
		do{
			$id = $this->parseBindingTarget();
			$init = null;
			if($this->eat("=")){
				$init = $this->parseAssign($noIn);
			}
			if($kind === "var"){
				$context = &$this->context();
				foreach(self::patternNames($id) as $name){
					$context["vars"][$name] = true;
				}
				unset($context);
			}
			$declarations[] = ["id" => $id, "init" => $init];
		}while($this->eat(","));
		return ["type" => "VariableDeclaration", "kind" => $kind, "declarations" => $declarations, "line" => $line];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseBindingTarget() : array{
		$line = $this->line();
		if($this->is("[")){
			return $this->parseArrayPattern();
		}
		if($this->is("{")){
			return $this->parseObjectPattern();
		}
		return ["type" => "Identifier", "name" => $this->identifier(), "line" => $line];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseBindingElement() : array{
		$target = $this->parseBindingTarget();
		if($this->eat("=")){
			return ["type" => "AssignmentPattern", "left" => $target, "right" => $this->parseAssign(false)];
		}
		return $target;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseArrayPattern() : array{
		$this->expect("[");
		$elements = [];
		while(!$this->eat("]")){
			if($this->is(",")){
				$this->index++;
				$elements[] = null;
				continue;
			}
			if($this->eat("...")){
				$elements[] = ["type" => "RestElement", "argument" => $this->parseBindingTarget()];
			}else{
				$elements[] = $this->parseBindingElement();
			}
			if(!$this->is("]")){
				$this->expect(",");
			}
		}
		return ["type" => "ArrayPattern", "elements" => $elements];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseObjectPattern() : array{
		$this->expect("{");
		$properties = [];
		while(!$this->eat("}")){
			if($this->eat("...")){
				$properties[] = ["type" => "RestElement", "argument" => $this->parseBindingTarget()];
			}else{
				[$key, $computed] = $this->parsePropertyKey();
				if($this->eat(":")){
					$value = $this->parseBindingElement();
				}else{
					if($computed || $key["type"] !== "Literal" || !is_string($key["value"])){
						throw $this->error("Invalid destructuring pattern");
					}
					$value = ["type" => "Identifier", "name" => $key["value"]];
					if($this->eat("=")){
						$value = ["type" => "AssignmentPattern", "left" => $value, "right" => $this->parseAssign(false)];
					}
				}
				$properties[] = ["type" => "Property", "key" => $key, "computed" => $computed, "value" => $value];
			}
			if(!$this->is("}")){
				$this->expect(",");
			}
		}
		return ["type" => "ObjectPattern", "properties" => $properties];
	}

	/**
	 * @return array{0: array<string, mixed>, 1: bool}
	 */
	private function parsePropertyKey() : array{
		$token = $this->next();
		switch($token["t"]){
			case "name":
				return [["type" => "Literal", "value" => $token["v"]], false];
			case "str":
				return [["type" => "Literal", "value" => $token["v"]], false];
			case "num":
				return [["type" => "Literal", "value" => $token["v"]], false];
			case "priv":
				return [["type" => "Literal", "value" => $token["v"], "private" => true], false];
			case "punc":
				if($token["v"] === "["){
					$key = $this->parseAssign(false);
					$this->expect("]");
					return [$key, true];
				}
		}
		throw $this->error("Unexpected token '" . self::describe($token) . "'", $token);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseIf() : array{
		$line = $this->line();
		$this->index++;
		$this->expect("(");
		$test = $this->parseExpression();
		$this->expect(")");
		$consequent = $this->parseStatement();
		$alternate = null;
		if($this->isName("else")){
			$this->index++;
			$alternate = $this->parseStatement();
		}
		return ["type" => "IfStatement", "test" => $test, "consequent" => $consequent, "alternate" => $alternate, "line" => $line];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseFor() : array{
		$line = $this->line();
		$this->index++;
		$await = false;
		if($this->isName("await")){
			$this->index++;
			$await = true;
		}
		$this->expect("(");
		$init = null;
		if($this->is(";")){
			$init = null;
		}elseif($this->isName("var") || $this->isName("const") || ($this->isName("let") && ($this->peek(1)["t"] === "name" || $this->is("[", 1) || $this->is("{", 1)))){
			$init = $this->parseVariableDeclaration(true);
		}else{
			$init = $this->parseExpression(true);
		}
		if($init !== null && ($this->isName("of") || $this->isName("in"))){
			$of = $this->next()["v"] === "of";
			if($init["type"] !== "VariableDeclaration"){
				$init = $this->toPattern($init);
			}
			$right = $of ? $this->parseAssign(false) : $this->parseExpression();
			$this->expect(")");
			$body = $this->parseStatement();
			return ["type" => $of ? "ForOfStatement" : "ForInStatement", "left" => $init, "right" => $right, "body" => $body, "await" => $await, "line" => $line];
		}
		$this->expect(";");
		$test = $this->is(";") ? null : $this->parseExpression();
		$this->expect(";");
		$update = $this->is(")") ? null : $this->parseExpression();
		$this->expect(")");
		$body = $this->parseStatement();
		return ["type" => "ForStatement", "init" => $init, "test" => $test, "update" => $update, "body" => $body, "line" => $line];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseTry() : array{
		$line = $this->line();
		$this->index++;
		$block = $this->parseBlock();
		$param = null;
		$handler = null;
		$finalizer = null;
		if($this->isName("catch")){
			$this->index++;
			if($this->eat("(")){
				$param = $this->parseBindingTarget();
				$this->expect(")");
			}
			$handler = $this->parseBlock();
		}
		if($this->isName("finally")){
			$this->index++;
			$finalizer = $this->parseBlock();
		}
		if($handler === null && $finalizer === null){
			throw $this->error("Missing catch or finally after try");
		}
		return ["type" => "TryStatement", "block" => $block, "param" => $param, "handler" => $handler, "finalizer" => $finalizer, "line" => $line];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseSwitch() : array{
		$line = $this->line();
		$this->index++;
		$this->expect("(");
		$discriminant = $this->parseExpression();
		$this->expect(")");
		$this->expect("{");
		$cases = [];
		$all = [];
		while(!$this->eat("}")){
			if($this->isName("case")){
				$this->index++;
				$test = $this->parseExpression();
			}else{
				$this->expectName("default");
				$test = null;
			}
			$this->expect(":");
			$consequent = [];
			while(!$this->isName("case") && !$this->isName("default") && !$this->is("}")){
				if($this->isEof()){
					throw $this->error("Unexpected end of input");
				}
				$statement = $this->parseStatement();
				$consequent[] = $statement;
				$all[] = $statement;
			}
			$cases[] = ["test" => $test, "consequent" => $consequent];
		}
		return $this->annotate(["type" => "SwitchStatement", "discriminant" => $discriminant, "cases" => $cases, "line" => $line], $all);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseImport() : array{
		$line = $this->line();
		$this->index++;
		$specifiers = [];
		if($this->peek()["t"] === "str"){
			$source = $this->next()["v"];
			$this->skipImportAttributes();
			$this->consumeSemicolon();
			return ["type" => "ImportDeclaration", "specifiers" => [], "source" => $source, "line" => $line];
		}
		if($this->peek()["t"] === "name" && !$this->isName("from")){
			$specifiers[] = ["kind" => "default", "imported" => "default", "local" => $this->identifier()];
			$this->eat(",");
		}elseif($this->isName("from") && ($this->is(",", 1) || $this->isName("from", 1))){
			$specifiers[] = ["kind" => "default", "imported" => "default", "local" => $this->identifier()];
			$this->eat(",");
		}
		if($this->eat("*")){
			$this->expectName("as");
			$specifiers[] = ["kind" => "namespace", "imported" => "*", "local" => $this->identifier()];
		}elseif($this->eat("{")){
			while(!$this->eat("}")){
				$token = $this->next();
				if($token["t"] !== "name" && $token["t"] !== "str"){
					throw $this->error("Invalid import specifier", $token);
				}
				$imported = $token["v"];
				$local = $imported;
				if($this->isName("as")){
					$this->index++;
					$local = $this->identifier();
				}
				$specifiers[] = ["kind" => "named", "imported" => $imported, "local" => $local];
				if(!$this->is("}")){
					$this->expect(",");
				}
			}
		}
		$this->expectName("from");
		$token = $this->next();
		if($token["t"] !== "str"){
			throw $this->error("Expected a module specifier", $token);
		}
		$this->skipImportAttributes();
		$this->consumeSemicolon();
		return ["type" => "ImportDeclaration", "specifiers" => $specifiers, "source" => $token["v"], "line" => $line];
	}

	private function skipImportAttributes() : void{
		if(($this->isName("with") || $this->isName("assert")) && !$this->peek()["n"] && $this->is("{", 1)){
			$this->index++;
			$this->parseObjectLiteral();
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseExport() : array{
		$line = $this->line();
		$this->index++;
		if($this->isName("default")){
			$this->index++;
			if($this->isName("function") || ($this->isName("async") && $this->isName("function", 1))){
				$async = $this->isName("async");
				if($async){
					$this->index++;
				}
				$fn = $this->parseFunction($async, true, true);
				return ["type" => "ExportDefaultDeclaration", "declaration" => $fn["id"] !== null ? ["type" => "FunctionDeclaration", "fn" => $fn, "line" => $line] : null, "expression" => $fn["id"] === null ? ["type" => "FunctionExpression", "fn" => $fn] : null, "line" => $line];
			}
			if($this->isName("class")){
				$class = $this->parseClass(false);
				return ["type" => "ExportDefaultDeclaration", "declaration" => $class["id"] !== null ? ["type" => "ClassDeclaration", "class" => $class, "line" => $line] : null, "expression" => $class["id"] === null ? ["type" => "ClassExpression", "class" => $class] : null, "line" => $line];
			}
			$expression = $this->parseAssign(false);
			$this->consumeSemicolon();
			return ["type" => "ExportDefaultDeclaration", "declaration" => null, "expression" => $expression, "line" => $line];
		}
		if($this->eat("*")){
			$exported = null;
			if($this->isName("as")){
				$this->index++;
				$token = $this->next();
				$exported = $token["v"];
			}
			$this->expectName("from");
			$source = $this->next()["v"];
			$this->skipImportAttributes();
			$this->consumeSemicolon();
			return ["type" => "ExportAllDeclaration", "source" => $source, "exported" => $exported, "line" => $line];
		}
		if($this->eat("{")){
			$specifiers = [];
			while(!$this->eat("}")){
				$token = $this->next();
				$local = $token["v"];
				$exported = $local;
				if($this->isName("as")){
					$this->index++;
					$exported = $this->next()["v"];
				}
				$specifiers[] = ["local" => $local, "exported" => $exported];
				if(!$this->is("}")){
					$this->expect(",");
				}
			}
			$source = null;
			if($this->isName("from")){
				$this->index++;
				$source = $this->next()["v"];
				$this->skipImportAttributes();
			}
			$this->consumeSemicolon();
			return ["type" => "ExportNamedDeclaration", "declaration" => null, "specifiers" => $specifiers, "source" => $source, "line" => $line];
		}
		$declaration = $this->parseStatement();
		if(!in_array($declaration["type"], ["VariableDeclaration", "FunctionDeclaration", "ClassDeclaration"], true)){
			throw $this->error("Unexpected export");
		}
		return ["type" => "ExportNamedDeclaration", "declaration" => $declaration, "specifiers" => [], "source" => null, "line" => $line];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseFunction(bool $async, bool $declaration, bool $optionalName = false) : array{
		$line = $this->line();
		$this->expectName("function");
		$generator = $this->eat("*");
		$id = null;
		if($this->peek()["t"] === "name" && !$this->is("(")){
			$id = $this->identifier();
		}elseif($declaration && !$optionalName){
			throw $this->error("Function name expected");
		}
		return $this->parseFunctionRest($id, $async, $generator, false, $line);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseFunctionRest(?string $id, bool $async, bool $generator, bool $method, int $line) : array{
		$this->functions[] = ["vars" => [], "args" => false, "arrow" => false, "async" => $async, "generator" => $generator];
		$this->expect("(");
		$params = $this->parseParameters();
		$body = $this->parseFunctionBody();
		$context = array_pop($this->functions);
		return [
			"type" => "Function",
			"id" => $id,
			"params" => $params,
			"body" => $body,
			"expression" => false,
			"async" => $async,
			"generator" => $generator,
			"arrow" => false,
			"method" => $method,
			"vars" => self::keys($context["vars"]),
			"args" => $context["args"],
			"line" => $line
		];
	}

	/**
	 * Parses parameters after the opening parenthesis, up to and including
	 * the closing one.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function parseParameters() : array{
		$params = [];
		while(!$this->eat(")")){
			if($this->eat("...")){
				$params[] = ["type" => "RestElement", "argument" => $this->parseBindingTarget()];
			}else{
				$params[] = $this->parseBindingElement();
			}
			if(!$this->is(")")){
				$this->expect(",");
			}
		}
		return $params;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseFunctionBody() : array{
		$line = $this->line();
		$this->expect("{");
		$body = [];
		while(!$this->eat("}")){
			if($this->isEof()){
				throw $this->error("Unexpected end of input");
			}
			$body[] = $this->parseStatement();
		}
		return $this->annotate(["type" => "BlockStatement", "body" => $body, "line" => $line], $body);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseClass(bool $requireName) : array{
		$line = $this->line();
		$this->expectName("class");
		$id = null;
		if($this->peek()["t"] === "name" && !$this->isName("extends") && !$this->is("{")){
			$id = $this->identifier();
		}elseif($requireName){
			throw $this->error("Class name expected");
		}
		$superClass = null;
		if($this->isName("extends")){
			$this->index++;
			$superClass = $this->parseLeftHandSide();
		}
		$this->expect("{");
		$members = [];
		while(!$this->eat("}")){
			if($this->eat(";")){
				continue;
			}
			$members[] = $this->parseClassMember();
		}
		return ["id" => $id, "superClass" => $superClass, "members" => $members, "line" => $line];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseClassMember() : array{
		$line = $this->line();
		$static = false;
		if($this->isName("static") && !$this->is("(", 1) && !$this->is("=", 1) && !$this->is(";", 1) && !$this->is("}", 1)){
			$this->index++;
			$static = true;
			if($this->is("{")){
				$this->functions[] = ["vars" => [], "args" => false, "arrow" => false, "async" => false, "generator" => false];
				$body = $this->parseFunctionBody();
				$context = array_pop($this->functions);
				$fn = ["type" => "Function", "id" => null, "params" => [], "body" => $body, "expression" => false, "async" => false, "generator" => false, "arrow" => false, "method" => true, "vars" => self::keys($context["vars"]), "args" => false, "line" => $line];
				return ["kind" => "block", "static" => true, "value" => $fn, "line" => $line];
			}
		}
		$kind = "method";
		$async = false;
		$generator = false;
		if($this->isName("async") && !$this->is("(", 1) && !$this->is("=", 1) && !$this->peek(1)["n"]){
			$this->index++;
			$async = true;
		}
		if($this->eat("*")){
			$generator = true;
		}
		if(($this->isName("get") || $this->isName("set")) && !$this->is("(", 1) && !$this->is("=", 1) && !$this->is(";", 1) && !$this->is("}", 1)){
			$kind = $this->next()["v"];
		}
		[$key, $computed] = $this->parsePropertyKey();
		if($this->is("(")){
			$name = !$computed && is_string($key["value"] ?? null) ? $key["value"] : null;
			if($kind === "method" && !$static && !$computed && $name === "constructor"){
				$kind = "constructor";
			}
			$fn = $this->parseFunctionRest($name, $async, $generator, true, $line);
			return ["kind" => $kind, "static" => $static, "key" => $key, "computed" => $computed, "value" => $fn, "line" => $line];
		}
		$value = null;
		if($this->eat("=")){
			$this->functions[] = ["vars" => [], "args" => false, "arrow" => true, "async" => false, "generator" => false];
			$value = $this->parseAssign(false);
			array_pop($this->functions);
		}
		$this->consumeSemicolon();
		return ["kind" => "field", "static" => $static, "key" => $key, "computed" => $computed, "value" => $value, "line" => $line];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function parseExpression(bool $noIn = false) : array{
		$line = $this->line();
		$expression = $this->parseAssign($noIn);
		if($this->is(",")){
			$expressions = [$expression];
			while($this->eat(",")){
				$expressions[] = $this->parseAssign($noIn);
			}
			return ["type" => "SequenceExpression", "expressions" => $expressions, "line" => $line];
		}
		return $expression;
	}

	private function isArrowAhead() : bool{
		$token = $this->peek();
		if($token["t"] === "name" && !in_array($token["v"], self::RESERVED, true) && $this->is("=>", 1)){
			return true;
		}
		if(!$this->is("(")){
			return false;
		}
		$depth = 0;
		for($i = $this->index, $count = count($this->tokens); $i < $count; ++$i){
			$current = $this->tokens[$i];
			if($current["t"] === "punc"){
				if($current["v"] === "(" || $current["v"] === "[" || $current["v"] === "{"){
					$depth++;
				}elseif($current["v"] === ")" || $current["v"] === "]" || $current["v"] === "}"){
					$depth--;
					if($depth === 0){
						$after = $this->tokens[$i + 1] ?? null;
						return $after !== null && $after["t"] === "punc" && $after["v"] === "=>" && !$after["n"];
					}
				}
			}elseif($current["t"] === "eof"){
				return false;
			}
		}
		return false;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseArrow(bool $async) : array{
		$line = $this->line();
		$this->functions[] = ["vars" => [], "args" => false, "arrow" => true, "async" => $async, "generator" => false];
		if($this->eat("(")){
			$params = $this->parseParameters();
		}else{
			$params = [["type" => "Identifier", "name" => $this->identifier(), "line" => $line]];
		}
		$this->expect("=>");
		if($this->is("{")){
			$body = $this->parseFunctionBody();
			$expression = false;
		}else{
			$body = $this->parseAssign(false);
			$expression = true;
		}
		$context = array_pop($this->functions);
		$fn = [
			"type" => "Function",
			"id" => null,
			"params" => $params,
			"body" => $body,
			"expression" => $expression,
			"async" => $async,
			"generator" => false,
			"arrow" => true,
			"method" => false,
			"vars" => self::keys($context["vars"]),
			"args" => false,
			"line" => $line
		];
		return ["type" => "ArrowFunctionExpression", "fn" => $fn, "line" => $line];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseAssign(bool $noIn = false) : array{
		$line = $this->line();
		if($this->isName("async") && !$this->peek(1)["n"]){
			$next = $this->peek(1);
			if(($next["t"] === "name" && $this->is("=>", 2)) || ($next["t"] === "punc" && $next["v"] === "(" && $this->isArrowAheadFrom($this->index + 1))){
				$this->index++;
				return $this->parseArrow(true);
			}
		}
		if($this->isArrowAhead()){
			return $this->parseArrow(false);
		}
		if($this->isName("yield") && $this->inGenerator()){
			$this->index++;
			$delegate = false;
			$argument = null;
			if(!$this->peek()["n"]){
				$delegate = $this->eat("*");
				$token = $this->peek();
				if($delegate || !($token["t"] === "eof" || ($token["t"] === "punc" && in_array($token["v"], [")", "]", "}", ",", ";", ":"], true)))){
					$argument = $this->parseAssign($noIn);
				}
			}
			return ["type" => "YieldExpression", "argument" => $argument, "delegate" => $delegate, "line" => $line];
		}
		$left = $this->parseConditional($noIn);
		$token = $this->peek();
		if($token["t"] === "punc" && in_array($token["v"], self::ASSIGNMENT_OPERATORS, true)){
			$this->index++;
			if($token["v"] === "="){
				$left = $this->toPattern($left);
			}elseif($left["type"] !== "Identifier" && $left["type"] !== "MemberExpression"){
				throw $this->error("Invalid assignment target");
			}
			$right = $this->parseAssign($noIn);
			return ["type" => "AssignmentExpression", "operator" => $token["v"], "left" => $left, "right" => $right, "line" => $line];
		}
		return $left;
	}

	private function isArrowAheadFrom(int $index) : bool{
		$saved = $this->index;
		$this->index = $index;
		$result = $this->isArrowAhead();
		$this->index = $saved;
		return $result;
	}

	/**
	 * Converts an expression parsed before "=" into an assignment target.
	 *
	 * @param array<string, mixed> $node
	 * @return array<string, mixed>
	 */
	private function toPattern(array $node) : array{
		switch($node["type"]){
			case "Identifier":
			case "MemberExpression":
			case "ObjectPattern":
			case "ArrayPattern":
			case "AssignmentPattern":
				return $node;
			case "ArrayExpression":
				$elements = [];
				foreach($node["elements"] as $element){
					if($element === null){
						$elements[] = null;
					}elseif($element["type"] === "SpreadElement"){
						$elements[] = ["type" => "RestElement", "argument" => $this->toPattern($element["argument"])];
					}else{
						$elements[] = $this->toPattern($element);
					}
				}
				return ["type" => "ArrayPattern", "elements" => $elements];
			case "ObjectExpression":
				$properties = [];
				foreach($node["properties"] as $property){
					if($property["kind"] === "spread"){
						$properties[] = ["type" => "RestElement", "argument" => $this->toPattern($property["value"])];
						continue;
					}
					$properties[] = ["type" => "Property", "key" => $property["key"], "computed" => $property["computed"], "value" => $this->toPattern($property["value"])];
				}
				return ["type" => "ObjectPattern", "properties" => $properties];
			case "AssignmentExpression":
				if($node["operator"] === "="){
					return ["type" => "AssignmentPattern", "left" => $this->toPattern($node["left"]), "right" => $node["right"]];
				}
		}
		throw $this->error("Invalid assignment target");
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseConditional(bool $noIn) : array{
		$line = $this->line();
		$test = $this->parseBinary(0, $noIn);
		if(!$this->eat("?")){
			return $test;
		}
		$consequent = $this->parseAssign(false);
		$this->expect(":");
		$alternate = $this->parseAssign($noIn);
		return ["type" => "ConditionalExpression", "test" => $test, "consequent" => $consequent, "alternate" => $alternate, "line" => $line];
	}

	private function binaryOperator(bool $noIn) : ?string{
		$token = $this->peek();
		if($token["t"] === "punc" && isset(self::BINARY_PRECEDENCE[$token["v"]])){
			return $token["v"];
		}
		if($token["t"] === "name" && ($token["v"] === "instanceof" || ($token["v"] === "in" && !$noIn))){
			return $token["v"];
		}
		return null;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseBinary(int $minPrecedence, bool $noIn) : array{
		$line = $this->line();
		if($this->peek()["t"] === "priv" && $this->isName("in", 1)){
			$name = $this->next()["v"];
			$this->index++;
			$left = ["type" => "PrivateIn", "name" => $name, "right" => $this->parseBinary(9, $noIn), "line" => $line];
		}else{
			$left = $this->parseUnary();
		}
		while(true){
			$operator = $this->binaryOperator($noIn);
			if($operator === null){
				return $left;
			}
			$precedence = self::BINARY_PRECEDENCE[$operator];
			if($precedence <= $minPrecedence){
				return $left;
			}
			$this->index++;
			$right = $this->parseBinary($operator === "**" ? $precedence - 1 : $precedence, $noIn);
			$type = ($operator === "&&" || $operator === "||" || $operator === "??") ? "LogicalExpression" : "BinaryExpression";
			$left = ["type" => $type, "operator" => $operator, "left" => $left, "right" => $right, "line" => $line];
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseUnary() : array{
		$token = $this->peek();
		$line = $token["l"];
		if($token["t"] === "punc"){
			if(in_array($token["v"], ["!", "~", "+", "-"], true)){
				$this->index++;
				return ["type" => "UnaryExpression", "operator" => $token["v"], "argument" => $this->parseUnary(), "line" => $line];
			}
			if($token["v"] === "++" || $token["v"] === "--"){
				$this->index++;
				$argument = $this->parseUnary();
				if($argument["type"] !== "Identifier" && $argument["type"] !== "MemberExpression"){
					throw $this->error("Invalid update target");
				}
				return ["type" => "UpdateExpression", "operator" => $token["v"], "prefix" => true, "argument" => $argument, "line" => $line];
			}
		}elseif($token["t"] === "name"){
			if(in_array($token["v"], ["typeof", "void", "delete"], true)){
				$this->index++;
				return ["type" => "UnaryExpression", "operator" => $token["v"], "argument" => $this->parseUnary(), "line" => $line];
			}
			if($token["v"] === "await" && $this->inAsync()){
				$this->index++;
				return ["type" => "AwaitExpression", "argument" => $this->parseUnary(), "line" => $line];
			}
		}
		$expression = $this->parseLeftHandSide();
		$next = $this->peek();
		if($next["t"] === "punc" && ($next["v"] === "++" || $next["v"] === "--") && !$next["n"]){
			if($expression["type"] !== "Identifier" && $expression["type"] !== "MemberExpression"){
				throw $this->error("Invalid update target");
			}
			$this->index++;
			return ["type" => "UpdateExpression", "operator" => $next["v"], "prefix" => false, "argument" => $expression, "line" => $line];
		}
		return $expression;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseLeftHandSide() : array{
		$line = $this->line();
		if($this->isName("new")){
			$expression = $this->parseNew();
		}else{
			$expression = $this->parsePrimary();
		}
		$chain = false;
		while(true){
			$token = $this->peek();
			if($token["t"] === "punc"){
				if($token["v"] === "."){
					$this->index++;
					$next = $this->next();
					if($next["t"] === "priv"){
						$expression = ["type" => "MemberExpression", "object" => $expression, "property" => ["type" => "Literal", "value" => $next["v"]], "computed" => false, "optional" => false, "private" => true, "line" => $line];
						continue;
					}
					if($next["t"] !== "name"){
						throw $this->error("Unexpected token '" . self::describe($next) . "'", $next);
					}
					$expression = ["type" => "MemberExpression", "object" => $expression, "property" => ["type" => "Literal", "value" => $next["v"]], "computed" => false, "optional" => false, "line" => $line];
					continue;
				}
				if($token["v"] === "?."){
					$this->index++;
					$chain = true;
					if($this->eat("(")){
						$expression = ["type" => "CallExpression", "callee" => $expression, "arguments" => $this->parseArguments(), "optional" => true, "line" => $line];
					}elseif($this->eat("[")){
						$property = $this->parseExpression();
						$this->expect("]");
						$expression = ["type" => "MemberExpression", "object" => $expression, "property" => $property, "computed" => true, "optional" => true, "line" => $line];
					}else{
						$next = $this->next();
						$private = $next["t"] === "priv";
						if($next["t"] !== "name" && !$private){
							throw $this->error("Unexpected token '" . self::describe($next) . "'", $next);
						}
						$expression = ["type" => "MemberExpression", "object" => $expression, "property" => ["type" => "Literal", "value" => $next["v"]], "computed" => false, "optional" => true, "private" => $private, "line" => $line];
					}
					continue;
				}
				if($token["v"] === "["){
					$this->index++;
					$property = $this->parseExpression();
					$this->expect("]");
					$expression = ["type" => "MemberExpression", "object" => $expression, "property" => $property, "computed" => true, "optional" => false, "line" => $line];
					continue;
				}
				if($token["v"] === "("){
					$this->index++;
					$expression = ["type" => "CallExpression", "callee" => $expression, "arguments" => $this->parseArguments(), "optional" => false, "line" => $line];
					continue;
				}
			}elseif($token["t"] === "tpl"){
				$this->index++;
				$expression = ["type" => "TaggedTemplateExpression", "tag" => $expression, "quasi" => $this->templateNode($token), "line" => $line];
				continue;
			}
			break;
		}
		if($chain){
			return ["type" => "ChainExpression", "expression" => $expression, "line" => $line];
		}
		return $expression;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function parseArguments() : array{
		$arguments = [];
		while(!$this->eat(")")){
			if($this->eat("...")){
				$arguments[] = ["type" => "SpreadElement", "argument" => $this->parseAssign(false)];
			}else{
				$arguments[] = $this->parseAssign(false);
			}
			if(!$this->is(")")){
				$this->expect(",");
			}
		}
		return $arguments;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseNew() : array{
		$line = $this->line();
		$this->expectName("new");
		if($this->eat(".")){
			$this->expectName("target");
			return ["type" => "NewTarget", "line" => $line];
		}
		if($this->isName("new")){
			$callee = $this->parseNew();
		}else{
			$callee = $this->parsePrimary();
		}
		while(true){
			if($this->eat(".")){
				$next = $this->next();
				$callee = ["type" => "MemberExpression", "object" => $callee, "property" => ["type" => "Literal", "value" => $next["v"]], "computed" => false, "optional" => false, "private" => $next["t"] === "priv", "line" => $line];
			}elseif($this->eat("[")){
				$property = $this->parseExpression();
				$this->expect("]");
				$callee = ["type" => "MemberExpression", "object" => $callee, "property" => $property, "computed" => true, "optional" => false, "line" => $line];
			}else{
				break;
			}
		}
		$arguments = [];
		if($this->eat("(")){
			$arguments = $this->parseArguments();
		}
		return ["type" => "NewExpression", "callee" => $callee, "arguments" => $arguments, "line" => $line];
	}

	/**
	 * @param array<string, mixed> $token
	 * @return array<string, mixed>
	 */
	private function templateNode(array $token) : array{
		$expressions = [];
		foreach($token["v"]["exprs"] as [$source, $line]){
			$expressions[] = self::parseEmbeddedExpression($source, $this->file, $line, $this->functions);
		}
		return ["type" => "TemplateLiteral", "cooked" => $token["v"]["cooked"], "raw" => $token["v"]["raw"], "expressions" => $expressions, "line" => $token["l"]];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parsePrimary() : array{
		$token = $this->next();
		$line = $token["l"];
		switch($token["t"]){
			case "num":
			case "str":
				return ["type" => "Literal", "value" => $token["v"], "line" => $line];
			case "tpl":
				return $this->templateNode($token);
			case "regex":
				return ["type" => "RegExpLiteral", "pattern" => $token["v"]["pattern"], "flags" => $token["v"]["flags"], "line" => $line];
			case "punc":
				switch($token["v"]){
					case "(":
						$expression = $this->parseExpression();
						$this->expect(")");
						return $expression;
					case "[":
						return $this->parseArrayLiteral($line);
					case "{":
						$this->index--;
						return $this->parseObjectLiteral();
				}
				break;
			case "name":
				switch($token["v"]){
					case "this":
						return ["type" => "ThisExpression", "line" => $line];
					case "super":
						return ["type" => "Super", "line" => $line];
					case "null":
						return ["type" => "Literal", "value" => null, "null" => true, "line" => $line];
					case "true":
						return ["type" => "Literal", "value" => true, "line" => $line];
					case "false":
						return ["type" => "Literal", "value" => false, "line" => $line];
					case "function":
						$this->index--;
						return ["type" => "FunctionExpression", "fn" => $this->parseFunction(false, false), "line" => $line];
					case "async":
						if($this->isName("function") && !$this->peek()["n"]){
							return ["type" => "FunctionExpression", "fn" => $this->parseFunction(true, false), "line" => $line];
						}
						return ["type" => "Identifier", "name" => "async", "line" => $line];
					case "class":
						$this->index--;
						return ["type" => "ClassExpression", "class" => $this->parseClass(false), "line" => $line];
					case "import":
						if($this->eat("(")){
							$source = $this->parseAssign(false);
							if($this->eat(",")){
								if(!$this->is(")")){
									$this->parseAssign(false);
								}
								$this->eat(",");
							}
							$this->expect(")");
							return ["type" => "ImportExpression", "source" => $source, "line" => $line];
						}
						if($this->eat(".")){
							$this->expectName("meta");
							return ["type" => "ImportMeta", "line" => $line];
						}
						break;
					default:
						if(in_array($token["v"], self::RESERVED, true)){
							break;
						}
						if($token["v"] === "arguments"){
							$this->markArguments();
						}
						return ["type" => "Identifier", "name" => $token["v"], "line" => $line];
				}
				break;
			case "priv":
				break;
		}
		throw $this->error("Unexpected token '" . self::describe($token) . "'", $token);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseArrayLiteral(int $line) : array{
		$elements = [];
		while(!$this->eat("]")){
			if($this->is(",")){
				$this->index++;
				$elements[] = null;
				continue;
			}
			if($this->eat("...")){
				$elements[] = ["type" => "SpreadElement", "argument" => $this->parseAssign(false)];
			}else{
				$elements[] = $this->parseAssign(false);
			}
			if(!$this->is("]")){
				$this->expect(",");
			}
		}
		return ["type" => "ArrayExpression", "elements" => $elements, "line" => $line];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseObjectLiteral() : array{
		$line = $this->line();
		$this->expect("{");
		$properties = [];
		while(!$this->eat("}")){
			if($this->eat("...")){
				$properties[] = ["kind" => "spread", "value" => $this->parseAssign(false)];
			}else{
				$properties[] = $this->parseObjectMember();
			}
			if(!$this->is("}")){
				$this->expect(",");
			}
		}
		return ["type" => "ObjectExpression", "properties" => $properties, "line" => $line];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseObjectMember() : array{
		$line = $this->line();
		$async = false;
		$generator = false;
		$kind = "init";
		if($this->isName("async") && !$this->is(",", 1) && !$this->is(":", 1) && !$this->is("(", 1) && !$this->is("}", 1) && !$this->is("=", 1)){
			$this->index++;
			$async = true;
		}
		if($this->eat("*")){
			$generator = true;
		}
		if(($this->isName("get") || $this->isName("set")) && !$this->is(",", 1) && !$this->is(":", 1) && !$this->is("(", 1) && !$this->is("}", 1) && !$this->is("=", 1)){
			$kind = $this->next()["v"];
		}
		$keyToken = $this->peek();
		[$key, $computed] = $this->parsePropertyKey();
		if($this->is("(")){
			$name = !$computed && is_string($key["value"] ?? null) ? $key["value"] : null;
			$fn = $this->parseFunctionRest($name, $async, $generator, true, $line);
			return ["kind" => $kind === "init" ? "init" : $kind, "key" => $key, "computed" => $computed, "value" => ["type" => "FunctionExpression", "fn" => $fn, "line" => $line], "method" => true];
		}
		if($this->eat(":")){
			return ["kind" => "init", "key" => $key, "computed" => $computed, "value" => $this->parseAssign(false), "method" => false];
		}
		if($computed || $keyToken["t"] !== "name"){
			throw $this->error("Unexpected token '" . self::describe($this->peek()) . "'");
		}
		if($keyToken["v"] === "arguments"){
			$this->markArguments();
		}
		$value = ["type" => "Identifier", "name" => $keyToken["v"], "line" => $line];
		if($this->is("=")){
			$this->index++;
			$value = ["type" => "AssignmentExpression", "operator" => "=", "left" => $value, "right" => $this->parseAssign(false), "line" => $line];
		}
		return ["kind" => "init", "key" => $key, "computed" => false, "value" => $value, "method" => false, "shorthand" => true];
	}
}
