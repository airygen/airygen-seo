<?php
/**
 * Admin hooks for Site Verification.
 *
 * @package Airygen\Modules\SiteVerification\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\SiteVerification\Admin;

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
