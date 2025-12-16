<?php
/**
 * Registers Hreflang settings for manual mapping.
 *
 * @package Airygen\Modules\Hreflang\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\Hreflang\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Handles option storage for hreflang defaults.
 */
final class Settings {

	private const OPTION_NAME = Constants::OPTION_HREFLANG;

	/**
	 * Ensure option exists.
	 *
	 * @return void
	 */
	public static function ensure_exists(): void {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::default_config(), '', 'no' );
		}
	}

	/**
	 * Retrieve sanitized configuration.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		return self::sanitize( get_option( self::OPTION_NAME, array() ) );
	}

	/**
	 * Persist hreflang configuration.
	 *
	 * @param array<string, mixed> $value Raw value.
	 *
	 * @return void
	 */
	public static function update( array $value ): void {
		update_option( self::OPTION_NAME, self::sanitize( $value ), 'no' );
	}

	/**
	 * Sanitize option payload.
	 *
	 * @param mixed $value Raw option value.
	 *
	 * @return array<string, mixed>
	 */
	public static function sanitize( $value ): array {
		if ( ! is_array( $value ) ) {
			return self::default_config();
		}

		$config = self::default_config();

		if ( isset( $value['manual_map'] ) && is_array( $value['manual_map'] ) ) {
			$map = array();
			foreach ( $value['manual_map'] as $key => $entry ) {
				if ( is_array( $entry ) ) {
					$code = isset( $entry['code'] ) ? sanitize_text_field( (string) $entry['code'] ) : '';
					$url  = isset( $entry['url'] ) ? esc_url_raw( (string) $entry['url'] ) : '';
				} else {
					$code = sanitize_text_field( (string) $key );
					$url  = esc_url_raw( (string) $entry );
				}

				if ( '' === $code || '' === $url ) {
					continue;
				}

				$map[ $code ] = $url;
			}

			$config['manual_map'] = $map;
		}

		if ( isset( $value['include_x_default'] ) ) {
			$config['include_x_default'] = (bool) $value['include_x_default'];
		}

		return $config;
	}

	/**
	 * Default configuration.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_config(): array {
		return array(
			'manual_map'        => array(),
			'include_x_default' => true,
		);
	}
}
