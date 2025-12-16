<?php
/**
 * Registers Robots settings.
 *
 * @package Airygen\Modules\Robots\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\Robots\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Handles option storage for robots defaults.
 */
final class Settings {

	private const OPTION_NAME = Constants::OPTION_ROBOTS;

	/**
	 * Ensure option exists.
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
	 * Persist configuration.
	 *
	 * @param array<string, mixed> $value Raw value.
	 *
	 * @return void
	 */
	public static function update( array $value ): void {
		update_option( self::OPTION_NAME, self::sanitize( $value ), 'no' );
	}

	/**
	 * Sanitize option input.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return array<string, mixed>
	 */
	public static function sanitize( $value ): array {
		if ( ! is_array( $value ) ) {
			return self::default_config();
		}

		$config = self::default_config();

		if ( isset( $value['default_directive'] ) ) {
			$config['default_directive'] = sanitize_text_field( (string) $value['default_directive'] );
		}

		if ( isset( $value['additional_rules'] ) ) {
			if ( is_array( $value['additional_rules'] ) ) {
				$raw_lines = $value['additional_rules'];
			} else {
				$raw_lines = preg_split( '/\r\n|\r|\n/', (string) $value['additional_rules'] );
				if ( ! is_array( $raw_lines ) ) {
					$raw_lines = array();
				}
			}

			$lines = array_filter(
				array_map(
					static function ( $line ): string {
						return trim( (string) $line );
					},
					$raw_lines
				)
			);

			$config['additional_rules'] = array_values( $lines );
		}

		if ( isset( $value['enable_default_meta'] ) ) {
			$config['enable_default_meta'] = (bool) $value['enable_default_meta'];
		} elseif ( isset( $value['suppress_default_meta'] ) ) {
			$config['enable_default_meta'] = ! (bool) $value['suppress_default_meta'];
		}

		return $config;
	}

	/**
	 * Default configuration structure.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_config(): array {
		return array(
			'default_directive'   => '',
			'additional_rules'    => array(),
			'enable_default_meta' => true,
		);
	}
}
