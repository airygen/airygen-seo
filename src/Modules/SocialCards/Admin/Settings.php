<?php
/**
 * Registers Social Cards settings for defaults.
 *
 * @package Airygen\Modules\SocialCards\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\SocialCards\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Settings registration (no UI yet).
 */
final class Settings {

	private const OPTION_NAME = Constants::OPTION_SOCIAL;

	/**
	 * Ensure the option exists with defaults.
	 *
	 * @return void
	 */
	public static function ensure_exists(): void {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::default_config(), '', 'no' );
			return;
		}

		// Lightweight migration: make sure required keys exist.
		$current  = get_option( self::OPTION_NAME, array() );
		$migrated = self::sanitize( $current );

		if ( $migrated !== $current ) {
			update_option( self::OPTION_NAME, $migrated, 'no' );
		}
	}

	/**
	 * Retrieve sanitized settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$value = get_option( self::OPTION_NAME, array() );

		return self::sanitize( $value );
	}

	/**
	 * Persist sanitized settings.
	 *
	 * @param array<string, mixed> $value Raw values.
	 *
	 * @return void
	 */
	public static function update( array $value ): void {
		update_option( self::OPTION_NAME, self::sanitize( $value ), 'no' );
	}

	/**
	 * Sanitize option values.
	 *
	 * @param mixed $value Raw option value.
	 *
	 * @return array<string, mixed>
	 */
	public static function sanitize( $value ): array {
		if ( ! is_array( $value ) ) {
			return self::default_config();
		}

		$config   = self::default_config();
		$og_input = array();
		$tw_input = array();

		if ( isset( $value['og'] ) && is_array( $value['og'] ) ) {
			$og_input = array_merge( $og_input, $value['og'] );
		}

		if ( isset( $value['twitter'] ) && is_array( $value['twitter'] ) ) {
			$tw_input = array_merge( $tw_input, $value['twitter'] );
		}

		$config['og']      = self::sanitize_og( $og_input, $config['og'] );
		$config['twitter'] = self::sanitize_twitter( $tw_input, $config['twitter'], $config['og']['enabled'] );

		return $config;
	}

	/**
	 * Default configuration values.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_config(): array {
		return array(
			'og'      => array(
				'enabled'             => true,
				'default_image_id'    => 0,
				'default_image_url'   => '',
				'image_width'         => 1200,
				'image_height'        => 630,
				'fb_app_id'           => '',
				'fb_admins'           => '',
				'publisher_url'       => '',
				'domain_verification' => '',
			),
			'twitter' => array(
				'enabled'           => true,
				'card_type'         => 'summary_large_image',
				'site_handle'       => '',
				'creator_handle'    => '',
				'inherit_og_image'  => true,
				'default_image_id'  => 0,
				'default_image_url' => '',
			),
		);
	}

	/**
	 * Sanitize Open Graph config.
	 *
	 * @param array<string, mixed> $input    Raw OG values.
	 * @param array<string, mixed> $defaults Default OG config.
	 *
	 * @return array<string, mixed>
	 */
	private static function sanitize_og( array $input, array $defaults ): array {
		$config = $defaults;

		$config['enabled'] = isset( $input['enabled'] ) ? self::to_bool( $input['enabled'] ) : $defaults['enabled'];

		if ( isset( $input['default_image_id'] ) ) {
			$config['default_image_id'] = self::sanitize_id( $input['default_image_id'] );
		}

		if ( isset( $input['default_image_url'] ) ) {
			$config['default_image_url'] = self::sanitize_url( $input['default_image_url'] );
		}

		if ( isset( $input['image_width'] ) ) {
			$config['image_width'] = self::sanitize_dimension( $input['image_width'], $defaults['image_width'] );
		}

		if ( isset( $input['image_height'] ) ) {
			$config['image_height'] = self::sanitize_dimension( $input['image_height'], $defaults['image_height'] );
		}

		if ( isset( $input['fb_app_id'] ) ) {
			$config['fb_app_id'] = sanitize_text_field( (string) $input['fb_app_id'] );
		}

		if ( isset( $input['fb_admins'] ) ) {
			$config['fb_admins'] = self::sanitize_fb_admins( $input['fb_admins'] );
		}

		if ( isset( $input['publisher_url'] ) ) {
			$config['publisher_url'] = self::sanitize_url( $input['publisher_url'] );
		}

		if ( isset( $input['domain_verification'] ) ) {
			$config['domain_verification'] = sanitize_text_field( (string) $input['domain_verification'] );
		}

		return $config;
	}

	/**
	 * Sanitize Twitter config.
	 *
	 * @param array<string, mixed> $input     Raw Twitter values.
	 * @param array<string, mixed> $defaults  Default Twitter config.
	 * @param bool                 $og_state  Whether OG is enabled.
	 *
	 * @return array<string, mixed>
	 */
	private static function sanitize_twitter( array $input, array $defaults, bool $og_state ): array {
		$config = $defaults;

		$config['enabled'] = isset( $input['enabled'] ) ? self::to_bool( $input['enabled'] ) : $defaults['enabled'];

		if ( isset( $input['card_type'] ) && in_array( $input['card_type'], array( 'summary', 'summary_large_image' ), true ) ) {
			$config['card_type'] = $input['card_type'];
		}

		if ( isset( $input['site_handle'] ) ) {
			$config['site_handle'] = self::sanitize_handle( $input['site_handle'] );
		}

		if ( isset( $input['creator_handle'] ) ) {
			$config['creator_handle'] = self::sanitize_handle( $input['creator_handle'] );
		}

		if ( isset( $input['inherit_og_image'] ) ) {
			$config['inherit_og_image'] = $og_state ? self::to_bool( $input['inherit_og_image'] ) : false;
		} elseif ( ! $og_state ) {
			$config['inherit_og_image'] = false;
		}

		if ( isset( $input['default_image_id'] ) ) {
			$config['default_image_id'] = self::sanitize_id( $input['default_image_id'] );
		}

		if ( isset( $input['default_image_url'] ) ) {
			$config['default_image_url'] = self::sanitize_url( $input['default_image_url'] );
		}

		return $config;
	}

	/**
	 * Coerce to boolean.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return bool
	 */
	private static function to_bool( $value ): bool {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) ?? false;
	}

	/**
	 * Sanitize attachment ID.
	 *
	 * @param mixed $value Value to sanitize.
	 *
	 * @return int
	 */
	private static function sanitize_id( $value ): int {
		$id = absint( $value );
		return $id > 0 ? $id : 0;
	}

	/**
	 * Sanitize URL or return empty string.
	 *
	 * @param mixed $value Value to sanitize.
	 *
	 * @return string
	 */
	private static function sanitize_url( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$url = trim( $value );
		if ( '' === $url ) {
			return '';
		}

		$sanitized = esc_url_raw( $url );
		if ( ! $sanitized ) {
			return '';
		}

		$scheme = wp_parse_url( $sanitized, PHP_URL_SCHEME );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return '';
		}

		return $sanitized;
	}

	/**
	 * Sanitize dimensions (width/height).
	 *
	 * @param mixed $value   Raw value.
	 * @param int   $fallback Default value.
	 *
	 * @return int
	 */
	private static function sanitize_dimension( $value, int $fallback ): int {
		$dimension = absint( $value );
		if ( 0 === $dimension ) {
			return 0;
		}

		if ( $dimension < 1 || $dimension > 4096 ) {
			return $fallback;
		}

		return $dimension;
	}

	/**
	 * Normalize Facebook admins string.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	private static function sanitize_fb_admins( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$parts = array_filter(
			array_map(
				static function ( string $part ): string {
					$digits = preg_replace( '/[^0-9]/', '', $part );
					return $digits ?? '';
				},
				explode( ',', $value )
			)
		);

		return implode( ',', $parts );
	}

	/**
	 * Sanitize Twitter handles (without @).
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	private static function sanitize_handle( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$handle = ltrim( trim( $value ), '@' );

		if ( '' === $handle ) {
			return '';
		}

		if ( ! preg_match( '/^[A-Za-z0-9_]{1,15}$/', $handle ) ) {
			return '';
		}

		return $handle;
	}
}
