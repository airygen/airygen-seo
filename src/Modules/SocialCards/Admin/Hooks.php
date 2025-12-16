<?php
/**
 * Admin hooks for Social Cards defaults.
 *
 * @package Airygen\Modules\SocialCards\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\SocialCards\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Registers admin integration points.
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
	 * Inject defaults into the editor configuration.
	 *
	 * @param array<string, mixed> $config Editor config.
	 *
	 * @return array<string, mixed>
	 */
	public static function extend_editor_config( array $config ): array {
		$config['socialCards'] = Settings::get();
		return $config;
	}
}
