<?php
/**
 * Admin integration for displaying link metrics in list tables.
 *
 * @package Airygen\Modules\LinkCounter\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkCounter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers admin-only hooks for the link counter feature.
 */
final class Hooks {

	/**
	 * Bootstrap admin hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		// Unified SEO list table column is registered from Airygen\Admin\PostListColumns\Hooks.
	}
}
