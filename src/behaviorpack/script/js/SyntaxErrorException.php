<?php

declare(strict_types=1);

namespace behaviorpack\script\js;

use RuntimeException;

/**
 * Invalid JavaScript source, raised by the lexer and the parser.
 */
final class SyntaxErrorException extends RuntimeException{

}
