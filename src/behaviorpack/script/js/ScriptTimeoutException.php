<?php

declare(strict_types=1);

namespace behaviorpack\script\js;

use RuntimeException;

/**
 * Raised when a script runs longer than the watchdog allows. Scripts cannot
 * catch it.
 */
final class ScriptTimeoutException extends RuntimeException{

}
