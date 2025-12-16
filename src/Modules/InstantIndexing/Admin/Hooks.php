<?php
/**
 * Admin bootstrapper for the IndexNow module.
 *
 * @package Airygen\Modules\InstantIndexing\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\InstantIndexing\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers admin-only integrations for Instant Indexing.
 */
final class Hooks {

	/**
	 * Ensure options exist and REST endpoints are ready.
	 *
	 * @return void
	 */
	public static function register(): void {
		Settings::ensure_exists();
	}
}
