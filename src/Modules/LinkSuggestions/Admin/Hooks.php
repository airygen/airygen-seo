<?php
/**
 * Registers admin-side hooks for Link Suggestions.
 *
 * @package Airygen\Modules\LinkSuggestions\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkSuggestions\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Entry point for admin hook registration.
 */
class Hooks {

	/**
	 * Register all admin hooks for this module.
	 *
	 * @return void
	 */
	public static function register(): void {
		ContentChangeWatcher::register();
		add_filter( Constants::HOOK_EDITOR_CONFIG, array( __CLASS__, 'extend_editor_config' ) );
	}

	/**
	 * Surface Link Suggestions API config to the editor bundle.
	 *
	 * @param array<string,mixed> $config Editor config.
	 *
	 * @return array<string,mixed>
	 */
	public static function extend_editor_config( array $config ): array {
		$api = RestController::get_editor_config();

		if ( ! empty( $api ) ) {
			$config['linkSuggestions'] = $api;
		}

		return $config;
	}
}
