<?php
/**
 * Public hooks bootstrap for 404 Manager.
 *
 * @package Airygen\Modules\NotFoundManager\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\NotFoundManager\Public;

use Airygen\Modules\NotFoundManager\Admin\Settings;
use Airygen\Modules\NotFoundManager\Runtime\Hooks as RuntimeHooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Entry point for public runtime hooks.
 */
final class Hooks {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		Settings::ensure_exists();
		RuntimeHooks::register();
	}
}
