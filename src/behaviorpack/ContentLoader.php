<?php

declare(strict_types=1);

namespace behaviorpack;

/**
 * Loads one kind of behavior pack content. Loaders run once, in the order
 * declared by BehaviorPackModule, after every pack has been discovered.
 */
interface ContentLoader{

	/**
	 * Short name used in the console, for example "custom items and blocks".
	 */
	public function getName() : string;

	/**
	 * Whether this loader needs the Customies plugin. Such loaders run when
	 * Customies enables, and are skipped with a warning when it is missing.
	 */
	public function requiresCustomies() : bool;

	/**
	 * Loads the content of every pack. Called on the main thread. A problem
	 * in one file must be logged and skipped, not thrown, so that one broken
	 * pack does not stop the others.
	 *
	 * @param list<BehaviorPack> $packs
	 */
	public function load(array $packs) : void;

	/**
	 * Releases what the loader holds, when the plugin is disabled.
	 */
	public function close() : void;
}
