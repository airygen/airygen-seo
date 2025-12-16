<?php
/**
 * Runtime wiring for Link Suggestions background jobs.
 *
 * @package Airygen\Modules\LinkSuggestions\Runtime
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkSuggestions\Runtime;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Action Scheduler jobs for link suggestions.
 */
class Hooks {

	/**
	 * Register runtime hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		Jobs::register();
	}
}
