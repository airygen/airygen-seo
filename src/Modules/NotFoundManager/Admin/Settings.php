<?php
/**
 * Stores 404 Manager settings.
 *
 * @package Airygen\Modules\NotFoundManager\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\NotFoundManager\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Option repository for 404 Manager settings.
 */
final class Settings {

	private const OPTION_NAME = Constants::OPTION_404_MANAGER_SETTINGS;

	/**
	 * Ensure option exists.
	 *
	 * @return void
	 */
	public static function ensure_exists(): void {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::defaults(), '', 'no' );
		}
	}

	/**
	 * Get settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		return self::sanitize( get_option( self::OPTION_NAME, array() ) );
	}

	/**
	 * Persist settings.
	 *
	 * @param array<string,mixed> $value Raw settings.
	 * @return void
	 */
	public static function update( array $value ): void {
		update_option( self::OPTION_NAME, self::sanitize( $value ), 'no' );
	}

	/**
	 * Defaults.
	 *
	 * @return array<string,mixed>
	 */
	private static function defaults(): array {
		return array(
			'monitor_mode'             => 'simple',
			'enable_daily_alert'       => true,
			'ignore_query_params'      => true,
			'log_limit'                => 1000,
			'retention_days'           => 30,
			'exclude_patterns'         => array(
				'/wp-admin/*',
				'/wp-json/*',
			),
			'fallback_redirect_mode'   => 'off',
			'fallback_redirect_target' => '',
			'fallback_redirect_code'   => 301,
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param mixed $value Raw option value.
	 * @return array<string,mixed>
	 */
	private static function sanitize( $value ): array {
		$defaults = self::defaults();
		if ( ! is_array( $value ) ) {
			return $defaults;
		}

		$monitor_mode = isset( $value['monitor_mode'] ) ? (string) $value['monitor_mode'] : $defaults['monitor_mode'];
		if ( ! in_array( $monitor_mode, array( 'simple', 'advanced' ), true ) ) {
			$monitor_mode = 'simple';
		}

		$ignore_query = isset( $value['ignore_query_params'] ) ? (bool) $value['ignore_query_params'] : (bool) $defaults['ignore_query_params'];
		$log_limit    = isset( $value['log_limit'] ) ? (int) $value['log_limit'] : (int) $defaults['log_limit'];
		$log_limit    = max( 100, min( 100000, $log_limit ) );

		$retention_days = isset( $value['retention_days'] ) ? (int) $value['retention_days'] : (int) $defaults['retention_days'];
		$retention_days = max( 1, min( 3650, $retention_days ) );

		$exclude_patterns = array();
		if ( isset( $value['exclude_patterns'] ) && is_array( $value['exclude_patterns'] ) ) {
			foreach ( $value['exclude_patterns'] as $pattern ) {
				$pattern = trim( (string) $pattern );
				if ( '' === $pattern ) {
					continue;
				}
				$exclude_patterns[] = $pattern;
			}
		}
		if ( empty( $exclude_patterns ) ) {
			$exclude_patterns = $defaults['exclude_patterns'];
		}

		$fallback_mode = isset( $value['fallback_redirect_mode'] ) ? (string) $value['fallback_redirect_mode'] : (string) $defaults['fallback_redirect_mode'];
		if ( ! in_array( $fallback_mode, array( 'off', 'home', 'custom' ), true ) ) {
			$fallback_mode = 'off';
		}

		$fallback_code = isset( $value['fallback_redirect_code'] ) ? (int) $value['fallback_redirect_code'] : (int) $defaults['fallback_redirect_code'];
		if ( ! in_array( $fallback_code, array( 301, 302, 307, 410, 451 ), true ) ) {
			$fallback_code = 301;
		}

		return array(
			'monitor_mode'             => $monitor_mode,
			'enable_daily_alert'       => isset( $value['enable_daily_alert'] ) ? (bool) $value['enable_daily_alert'] : (bool) $defaults['enable_daily_alert'],
			'ignore_query_params'      => $ignore_query,
			'log_limit'                => $log_limit,
			'retention_days'           => $retention_days,
			'exclude_patterns'         => array_values( array_unique( $exclude_patterns ) ),
			'fallback_redirect_mode'   => $fallback_mode,
			'fallback_redirect_target' => isset( $value['fallback_redirect_target'] ) ? esc_url_raw( (string) $value['fallback_redirect_target'] ) : '',
			'fallback_redirect_code'   => $fallback_code,
		);
	}
}
