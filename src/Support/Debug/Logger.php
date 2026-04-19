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
 * Thin wrapper around error_log with consistent formatting.
 *
 * Output goes to PHP's configured error log destination. When the site admin
 * enables WP_DEBUG_LOG, WordPress redirects it to wp-content/debug.log;
 * otherwise PHP routes it to whichever sink the hosting environment
 * configured (server error log, syslog, etc.). The plugin itself never
 * writes log files, keeping the plugin footprint within WordPress.org policy.
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
	 * Write an informational log line.
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
	 * Write a warning log line.
	 *
	 * @param string       $channel Channel or component name.
	 * @param string|array $message Log message or structured context.
	 *
	 * @return void
	 */
	public static function warning( string $channel, $message ): void {
		self::write( 'warning', $channel, $message );
	}

	/**
	 * Write an error log line.
	 *
	 * @param string       $channel Channel or component name.
	 * @param string|array $message Log message or structured context.
	 *
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
	 *
	 * @return void
	 */
	private static function write( string $level, string $channel, $message ): void {
		if ( ! self::should_emit( $level ) ) {
			return;
		}

		$timestamp = function_exists( 'current_time' ) ? (string) current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );
		$formatted = sprintf(
			'[airygen-seo] [%s] [%s] [%s] %s',
			$timestamp,
			strtoupper( $level ),
			$channel,
			self::format_message( $message )
		);

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $formatted );
	}

	/**
	 * Decide whether a log line should reach error_log.
	 *
	 * The admin opts in through Airygen's debug toggle and picks a verbosity
	 * level; the destination of error_log output itself remains under the
	 * operator's PHP/WordPress configuration (php.ini, WP_DEBUG_LOG, syslog,
	 * etc.), so this helper does not inspect WP_DEBUG.
	 *
	 * @param string $level Requested level.
	 *
	 * @return bool
	 */
	private static function should_emit( string $level ): bool {
		$config = Settings::get_config();
		if ( empty( $config['enabled'] ) ) {
			return false;
		}

		$configured = isset( $config['level'] ) ? (string) $config['level'] : 'info';
		$current    = self::LEVELS[ $configured ] ?? self::LEVELS['info'];
		$incoming   = self::LEVELS[ $level ] ?? self::LEVELS['info'];

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
