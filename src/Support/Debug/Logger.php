<?php
/**
 * Lightweight logger for Airygen debugging.
 *
 * @package Airygen\Support\Debug
 */

declare(strict_types=1);

namespace Airygen\Support\Debug;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Support\Debug\Settings;

/**
 * Simple helper wrapping error_log with consistent formatting.
 */
final class Logger {

	/**
	 * Numeric level map (higher = more verbose).
	 *
	 * @var array<string,int>
	 */
	private const LEVELS = array(
		'error'   => 1,
		'warning' => 2,
		'info'    => 3,
	);

	/**
	 * Write a log line when WP_DEBUG_LOG is enabled.
	 *
	 * @param string       $channel Channel or component name.
	 * @param string|array $message Log message or structured context.
	 *
	 * @return void
	 */
	public static function log( string $channel, $message ): void {
		self::write( 'info', $channel, $message );
	}

	/**
	 * Write warning level logs.
	 *
	 * @param string       $channel Channel or component name.
	 * @param string|array $message Log message or structured context.
	 * @return void
	 */
	public static function warning( string $channel, $message ): void {
		self::write( 'warning', $channel, $message );
	}

	/**
	 * Write error level logs.
	 *
	 * @param string       $channel Channel or component name.
	 * @param string|array $message Log message or structured context.
	 * @return void
	 */
	public static function error( string $channel, $message ): void {
		self::write( 'error', $channel, $message );
	}

	/**
	 * Core writer with level filtering.
	 *
	 * @param string       $level   Log level (error|warning|info).
	 * @param string       $channel Channel or component name.
	 * @param string|array $message Log message or structured context.
	 * @return void
	 */
	private static function write( string $level, string $channel, $message ): void {
		$config = Settings::get_config();
		if ( empty( $config['enabled'] ) ) {
			return;
		}
		if ( ! self::should_log( $level, isset( $config['level'] ) ? (string) $config['level'] : 'info' ) ) {
			return;
		}

		$log_file = Settings::resolve_log_file();

		$timestamp = function_exists( 'current_time' ) ? (string) current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );
		$formatted = sprintf(
			'[%s] [%s] [%s] %s',
			$timestamp,
			strtoupper( $level ),
			$channel,
			self::format_message( $message )
		);

		if ( $log_file && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents,WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,WordPress.PHP.NoSilencedErrors.Discouraged
			@file_put_contents( $log_file, $formatted . PHP_EOL, FILE_APPEND | LOCK_EX );
			if ( 'error' !== $level ) {
				return;
			}
		}

		if ( 'error' === $level ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $formatted );
			return;
		}

		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $formatted );
		}
	}

	/**
	 * Check if current level should be recorded.
	 *
	 * @param string $level Requested level.
	 * @param string $configured Configured level.
	 * @return bool
	 */
	private static function should_log( string $level, string $configured ): bool {
		$current  = self::LEVELS[ $configured ] ?? self::LEVELS['info'];
		$incoming = self::LEVELS[ $level ] ?? self::LEVELS['info'];
		return $incoming <= $current;
	}

	/**
	 * Normalize log data into a string.
	 *
	 * @param string|array $message Message or structured context.
	 *
	 * @return string
	 */
	private static function format_message( $message ): string {
		if ( is_string( $message ) ) {
			return $message;
		}

		if ( is_array( $message ) ) {
			$parts = array();
			foreach ( $message as $key => $value ) {
				$parts[] = sprintf( '%s=%s', $key, self::stringify_value( $value ) );
			}
			return implode( ' ', $parts );
		}

		return (string) $message;
	}

	/**
	 * Cast arbitrary value to a log-safe string.
	 *
	 * @param mixed $value Value to stringify.
	 *
	 * @return string
	 */
	private static function stringify_value( $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( is_scalar( $value ) || null === $value ) {
			return (string) $value;
		}

		return wp_json_encode( $value );
	}
}
