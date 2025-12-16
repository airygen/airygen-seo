<?php
/**
 * Admin hooks for hreflang configuration.
 *
 * @package Airygen\Modules\Hreflang\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\Hreflang\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers admin integrations.
 */
final class Hooks {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		Settings::ensure_exists();
	}
}
