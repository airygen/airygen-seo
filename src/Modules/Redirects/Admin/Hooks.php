<?php
/**
 * Admin hooks for redirects feature.
 *
 * @package Airygen\Modules\Redirects\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\Redirects\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers admin-side integrations.
 */
final class Hooks {

	/**
	 * Ensure options exist for SPA consumption.
	 *
	 * @return void
	 */
	public static function register(): void {
		Settings::ensure_exists();
	}
}
