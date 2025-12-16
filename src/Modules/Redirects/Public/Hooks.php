<?php
/**
 * Public hooks for redirects feature.
 *
 * @package Airygen\Modules\Redirects\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\Redirects\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers public runtime integrations.
 */
final class Hooks {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		ApplyRedirects::register();
	}
}
