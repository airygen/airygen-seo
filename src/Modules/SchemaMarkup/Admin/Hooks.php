<?php
/**
 * Admin hooks for schema markup feature.
 *
 * @package Airygen\Modules\SchemaMarkup\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\SchemaMarkup\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Registers admin integration points.
 */
final class Hooks {

	/**
	 * Register required hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		Settings::ensure_exists();
		add_filter( Constants::HOOK_EDITOR_CONFIG, array( __CLASS__, 'extend_editor_config' ) );
	}

	/**
	 * Append schema defaults to the editor configuration payload.
	 *
	 * @param array<string, mixed> $config Editor configuration.
	 *
	 * @return array<string, mixed>
	 */
	public static function extend_editor_config( array $config ): array {
		$config['schemaMarkup'] = Settings::get();
		return $config;
	}
}
