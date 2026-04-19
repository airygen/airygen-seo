<?php
/**
 * Handles persistence of debug logging configuration.
 *
 * @package Airygen\Support\Debug
 */

declare(strict_types=1);

namespace Airygen\Support\Debug;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Stores the debug logging toggle, verbosity, and classic-editor override.
 *
 * The plugin does not manage a log directory of its own; all log output is
 * delegated to PHP's error_log via {@see Logger}. This class only persists
 * the admin-controlled preferences.
 */
final class Settings {

	private const OPTION = Constants::OPTION_DEBUG;

	/**
	 * Retrieve the current debug configuration.
	 *
	 * @return array{enabled:bool,force_classic:bool,level:string}
	 */
	public static function get_config(): array {
		$value = get_option( self::OPTION, array() );
		if ( ! is_array( $value ) ) {
			$value = array();
		}

		return array(
			'enabled'       => ! empty( $value['enabled'] ),
			'force_classic' => ! empty( $value['force_classic'] ),
			'level'         => self::normalize_level( isset( $value['level'] ) ? (string) $value['level'] : 'info' ),
		);
	}

	/**
	 * Determine whether classic editor is forced for testing.
	 *
	 * @return bool
	 */
	public static function is_classic_forced(): bool {
		$config = self::get_config();
		return ! empty( $config['force_classic'] );
	}

	/**
	 * Enable/disable forcing classic editor.
	 *
	 * @param bool $enabled Whether classic editor is forced.
	 *
	 * @return void
	 */
	public static function set_force_classic( bool $enabled ): void {
		$value = get_option( self::OPTION, array() );
		if ( ! is_array( $value ) ) {
			$value = array();
		}

		$value['force_classic'] = $enabled ? 1 : 0;
		update_option( self::OPTION, $value, 'no' );
	}

	/**
	 * Set debug log level.
	 *
	 * @param string $level Debug level (error|warning|info).
	 *
	 * @return void
	 */
	public static function set_level( string $level ): void {
		$value = get_option( self::OPTION, array() );
		if ( ! is_array( $value ) ) {
			$value = array();
		}

		$value['level'] = self::normalize_level( $level );
		update_option( self::OPTION, $value, 'no' );
	}

	/**
	 * Enable debug logging.
	 *
	 * @return array<string,mixed>
	 */
	public static function enable(): array {
		$current = self::get_config();

		update_option(
			self::OPTION,
			array(
				'enabled'       => true,
				'force_classic' => $current['force_classic'] ? 1 : 0,
				'level'         => $current['level'],
			)
		);

		return array(
			'enabled' => true,
		);
	}

	/**
	 * Disable debug logging.
	 *
	 * @return array<string,mixed>
	 */
	public static function disable(): array {
		$current = self::get_config();

		update_option(
			self::OPTION,
			array(
				'enabled'       => false,
				'force_classic' => $current['force_classic'] ? 1 : 0,
				'level'         => $current['level'],
			)
		);

		return array(
			'enabled' => false,
		);
	}

	/**
	 * Normalize level value.
	 *
	 * @param string $level Raw level.
	 *
	 * @return string
	 */
	private static function normalize_level( string $level ): string {
		$level = strtolower( sanitize_key( $level ) );
		if ( in_array( $level, array( 'error', 'warning', 'info' ), true ) ) {
			return $level;
		}

		return 'info';
	}
}
