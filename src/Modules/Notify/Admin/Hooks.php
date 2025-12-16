<?php
/**
 * Admin hooks for Notify module.
 *
 * @package Airygen\Modules\Notify\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\Notify\Admin;

use Airygen\Modules\Notify\Infrastructure\LogRepository;
use Airygen\Modules\Notify\Runtime\Hooks as RuntimeHooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstraps Notify defaults and scheduler.
 */
final class Hooks {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'bootstrap' ) );
	}

	/**
	 * Bootstrap settings and runtime hooks.
	 *
	 * @return void
	 */
	public static function bootstrap(): void {
		Settings::ensure_exists();
		( new LogRepository() )->ensure_table();
		RuntimeHooks::register();
	}
}
