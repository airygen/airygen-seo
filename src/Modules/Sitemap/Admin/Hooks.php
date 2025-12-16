<?php
/**
 * Admin hooks for the sitemap feature.
 *
 * @package Airygen\Modules\Sitemap\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\Sitemap\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers admin integrations.
 */
final class Hooks {

	/**
	 * Register sitemap admin hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		Settings::ensure_exists();
	}
}
