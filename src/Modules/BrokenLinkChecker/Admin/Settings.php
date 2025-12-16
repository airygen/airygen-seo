<?php
/**
 * Settings storage for the Broken Link Checker module.
 *
 * @package Airygen\Modules\BrokenLinkChecker\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\BrokenLinkChecker\Admin;

use Airygen\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persist and sanitize Broken Link Checker options.
 */
final class Settings {

	private const OPTION_NAME = Constants::OPTION_BROKEN_LINK_CHECKER;

	/**
	 * Default configuration.
	 *
	 * @var array<string, mixed>
	 */
	private const DEFAULTS = array(
		'enabled'                    => true,
		'enable_daily_alert'         => true,
		'check_interval_hours'       => 24,
		'max_requests_per_run'       => 10,
		'batch_delay_minutes'        => 5,
		'log_retention_days'         => 7,
		'link_types'                 => array( 'external' ),
		'connection_timeout_seconds' => 2,
		'operation_timeout_seconds'  => 5,
		'treat_redirects_as_warning' => true,
	);

	/**
	 * Ensure the option exists.
	 *
	 * @return void
	 */
	public static function ensure_exists(): void {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::DEFAULTS, '', 'no' );
		}
	}

	/**
	 * Retrieve sanitized settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$value = get_option( self::OPTION_NAME, self::DEFAULTS );
		return self::sanitize( $value );
	}

	/**
	 * Persist new settings.
	 *
	 * @param array<string, mixed> $value Raw option payload.
	 * @return void
	 */
	public static function update( array $value ): void {
		update_option( self::OPTION_NAME, self::sanitize( $value ), 'no' );
	}

	/**
	 * Sanitize option payload.
	 *
	 * @param mixed $value Raw value from the database.
	 * @return array<string, mixed>
	 */
	private static function sanitize( $value ): array {
		$config = self::DEFAULTS;

		if ( ! is_array( $value ) ) {
			return $config;
		}

		if ( array_key_exists( 'enabled', $value ) ) {
			$config['enabled'] = (bool) $value['enabled'];
		}
		if ( array_key_exists( 'enable_daily_alert', $value ) ) {
			$config['enable_daily_alert'] = (bool) $value['enable_daily_alert'];
		}

		$config['check_interval_hours'] = self::clamp_int(
			$value['check_interval_hours'] ?? $config['check_interval_hours'],
			1,
			168
		);

		$config['max_requests_per_run'] = self::clamp_int(
			$value['max_requests_per_run'] ?? $config['max_requests_per_run'],
			1,
			50
		);

		$config['batch_delay_minutes'] = self::clamp_int(
			$value['batch_delay_minutes'] ?? $config['batch_delay_minutes'],
			1,
			60
		);

		$config['log_retention_days'] = self::clamp_int(
			$value['log_retention_days'] ?? $config['log_retention_days'],
			1,
			365
		);

		if ( isset( $value['link_types'] ) && is_array( $value['link_types'] ) ) {
			$allowed = array( 'external', 'internal' );
			$choices = array_values(
				array_unique(
					array_filter(
						array_map(
							static function ( $type ) use ( $allowed ) {
								$type = is_string( $type ) ? strtolower( $type ) : '';
								return in_array( $type, $allowed, true ) ? $type : null;
							},
							$value['link_types']
						)
					)
				)
			);

			if ( ! empty( $choices ) ) {
				$config['link_types'] = $choices;
			}
		}

		if ( empty( $config['link_types'] ) ) {
			$config['link_types'] = self::DEFAULTS['link_types'];
		}

		$config['connection_timeout_seconds'] = self::clamp_int(
			$value['connection_timeout_seconds'] ?? $config['connection_timeout_seconds'],
			1,
			30
		);

		$config['operation_timeout_seconds'] = self::clamp_int(
			$value['operation_timeout_seconds'] ?? $config['operation_timeout_seconds'],
			1,
			120
		);

		if ( array_key_exists( 'treat_redirects_as_warning', $value ) ) {
			$config['treat_redirects_as_warning'] = (bool) $value['treat_redirects_as_warning'];
		}

		return $config;
	}

	/**
	 * Clamp an integer between provided bounds.
	 *
	 * @param mixed $value   Raw value to clamp.
	 * @param int   $minimum Minimum allowed value.
	 * @param int   $maximum Maximum allowed value.
	 * @return int
	 */
	private static function clamp_int( $value, int $minimum, int $maximum ): int {
		$number = (int) $value;
		if ( $number < $minimum ) {
			return $minimum;
		}
		if ( $number > $maximum ) {
			return $maximum;
		}

		return $number;
	}
}
