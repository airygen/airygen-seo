<?php
/**
 * Admin bootstrap for the Image SEO module.
 *
 * @package Airygen\Modules\ImageSeo\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\ImageSeo\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ensures Image SEO settings are registered.
 */
final class Hooks {

	/**
	 * Register admin-side integrations.
	 *
	 * @return void
	 */
	public static function register(): void {
		Settings::ensure_exists();
	}
}
