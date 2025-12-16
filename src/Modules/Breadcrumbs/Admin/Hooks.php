<?php
/**
 * Admin bootstrap for breadcrumbs.
 *
 * @package Airygen\Modules\Breadcrumbs\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\Breadcrumbs\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers breadcrumbs settings on boot.
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
