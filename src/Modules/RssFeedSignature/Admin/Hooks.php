<?php
/**
 * Admin hooks for RSS Feed Signature.
 *
 * @package Airygen\Modules\RssFeedSignature\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\RssFeedSignature\Admin;

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
