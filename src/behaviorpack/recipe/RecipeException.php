<?php

declare(strict_types=1);

namespace behaviorpack\recipe;

/**
 * Thrown when a recipe file is malformed or references something the server
 * cannot provide. The loader logs it and skips the recipe.
 */
final class RecipeException extends \RuntimeException{

}
