<?php
/**
 * Admin hooks for robots feature.
 *
 * @package Airygen\Modules\Robots\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\Robots\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

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
		add_filter( Constants::HOOK_EDITOR_CONFIG, array( __CLASS__, 'extend_editor_config' ) );
	}

	/**
	 * Append robots defaults to the editor config.
	 *
	 * @param array<string, mixed> $config Editor config.
	 *
	 * @return array<string, mixed>
	 */
	public static function extend_editor_config( array $config ): array {
		$config['robots'] = Settings::get();
		return $config;
	}
}
