<?php
/**
 * Stores dashboard panel visibility preferences.
 *
 * @package Airygen\Admin\Panels
 */

declare(strict_types=1);

namespace Airygen\Admin\Panels;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Persists visibility toggles for editor sidebar/metabox panels.
 */
final class Visibility {

	private const OPTION_NAME = Constants::OPTION_PANEL_VISIBILITY;

	/**
	 * Cached visibility map.
	 *
	 * @var array<string, bool>|null
	 */
	private static $cache = null;

	/**
	 * Ensure the visibility option exists.
	 *
	 * @return void
	 */
	public static function ensure_exists(): void {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			$default = self::default_visibility();
			add_option( self::OPTION_NAME, $default, '', 'no' );
			self::$cache = $default;
			return;
		}

		$current   = get_option( self::OPTION_NAME, array() );
		$sanitized = self::sanitize( $current );

		if ( $current !== $sanitized ) {
			update_option( self::OPTION_NAME, $sanitized, 'no' );
		}

		self::$cache = $sanitized;
	}

	/**
	 * Retrieve stored visibility map.
	 *
	 * @return array<string, bool>
	 */
	public static function get(): array {
		if ( is_array( self::$cache ) ) {
			return self::$cache;
		}

		$value = get_option( self::OPTION_NAME, array() );

		self::$cache = self::sanitize( $value );

		return self::$cache;
	}

	/**
	 * Persist panel visibility settings.
	 *
	 * @param array<string, mixed> $value Raw visibility input.
	 *
	 * @return void
	 */
	public static function update( array $value ): void {
		$sanitized = self::sanitize( $value );
		update_option( self::OPTION_NAME, $sanitized, 'no' );
		self::$cache = $sanitized;
	}

	/**
	 * Normalize visibility map.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return array<string, bool>
	 */
	private static function sanitize( $value ): array {
		$defaults = self::default_visibility();

		if ( ! is_array( $value ) ) {
			return $defaults;
		}

		$normalized = $defaults;
		foreach ( array_keys( $defaults ) as $key ) {
			if ( array_key_exists( $key, $value ) ) {
				$normalized[ $key ] = (bool) $value[ $key ];
			}
		}

		return $normalized;
	}

	/**
	 * Default visibility for each panel.
	 *
	 * @return array<string, bool>
	 */
	private static function default_visibility(): array {
		$default = array();
		foreach ( Order::keys() as $key ) {
			$default[ $key ] = true;
		}

		return $default;
	}
}
