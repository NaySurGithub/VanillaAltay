<?php

declare(strict_types=1);

namespace behaviorpack\script;

use RuntimeException;

/**
 * A script request that cannot be served. Its message is thrown back to the
 * script as an Error.
 */
final class ScriptException extends RuntimeException{

}
