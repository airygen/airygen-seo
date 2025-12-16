<?php
/**
 * Stores configuration for Site Verification output.
 *
 * @package Airygen\Modules\SiteVerification\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\SiteVerification\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Persists admin-configurable webmaster verification tokens.
 */
final class Settings {

	private const OPTION = Constants::OPTION_SITE_VERIFICATION;

	/**
	 * Ensure the option exists with defaults.
	 *
	 * @return void
	 */
	public static function ensure_exists(): void {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::defaults(), '', 'no' );
			return;
		}

		self::update( (array) get_option( self::OPTION, array() ) );
	}

	/**
	 * Retrieve sanitized configuration.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		return self::sanitize( get_option( self::OPTION, array() ) );
	}

	/**
	 * Persist sanitized settings.
	 *
	 * @param array<string, mixed> $value Raw option payload.
	 *
	 * @return void
	 */
	public static function update( array $value ): void {
		update_option( self::OPTION, self::sanitize( $value ), 'no' );
	}

	/**
	 * Sanitize input values against defaults.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return array<string, mixed>
	 */
	private static function sanitize( $value ): array {
		$config    = self::defaults();
		$sanitized = is_array( $value ) ? $value : array();

		$config['google']    = self::sanitize_token( $sanitized['google'] ?? $config['google'] );
		$config['bing']      = self::sanitize_token( $sanitized['bing'] ?? $config['bing'] );
		$config['yandex']    = self::sanitize_token( $sanitized['yandex'] ?? $config['yandex'] );
		$config['baidu']     = self::sanitize_token( $sanitized['baidu'] ?? $config['baidu'] );
		$config['pinterest'] = self::sanitize_token( $sanitized['pinterest'] ?? $config['pinterest'] );

		return $config;
	}

	/**
	 * Default configuration.
	 *
	 * @return array<string, mixed>
	 */
	private static function defaults(): array {
		return array(
			'google'    => '',
			'bing'      => '',
			'yandex'    => '',
			'baidu'     => '',
			'pinterest' => '',
		);
	}

	/**
	 * Sanitize webmaster verification token.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	private static function sanitize_token( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return sanitize_text_field( trim( (string) $value ) );
	}
}
