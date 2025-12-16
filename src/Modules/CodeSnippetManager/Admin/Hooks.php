<?php
/**
 * Admin hooks for Code Snippets.
 *
 * @package Airygen\Modules\CodeSnippetManager\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\CodeSnippetManager\Admin;

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
