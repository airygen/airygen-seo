<?php
/**
 * Admin hooks for Related Posts.
 *
 * @package Airygen\Modules\RelatedPosts\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\RelatedPosts\Admin;

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
