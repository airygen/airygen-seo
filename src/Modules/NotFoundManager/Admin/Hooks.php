<?php
/**
 * Admin hooks for 404 Manager module.
 *
 * @package Airygen\Modules\NotFoundManager\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\NotFoundManager\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ensure defaults for admin runtime.
 */
final class Hooks {

	/**
	 * Register module hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'bootstrap' ) );
	}

	/**
	 * Bootstrap defaults.
	 *
	 * @return void
	 */
	public static function bootstrap(): void {
		Settings::ensure_exists();
	}
}
