<?php
/**
 * Option storage for Image SEO preferences.
 *
 * @package Airygen\Modules\ImageSeo\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\ImageSeo\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Handles persistence for Image SEO settings.
 */
final class Settings {

	private const OPTION_NAME         = Constants::OPTION_IMAGE_SEO;
	private const MAX_TEMPLATE_LENGTH = 160;

	/**
	 * Ensure the option exists and is normalized.
	 *
	 * @return void
	 */
	public static function ensure_exists(): void {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::default_config(), '', 'no' );
			return;
		}

		$current  = get_option( self::OPTION_NAME, array() );
		$migrated = self::sanitize( $current );

		if ( $migrated !== $current ) {
			update_option( self::OPTION_NAME, $migrated, 'no' );
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
	 * Persist sanitized configuration.
	 *
	 * @param array<string, mixed> $value Raw configuration.
	 *
	 * @return void
	 */
	public static function update( array $value ): void {
		update_option( self::OPTION_NAME, self::sanitize( $value ), 'no' );
	}

	/**
	 * Sanitize raw option input.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return array<string, mixed>
	 */
	public static function sanitize( $value ): array {
		$config = self::default_config();

		if ( ! is_array( $value ) ) {
			return $config;
		}

		if ( isset( $value['alt'] ) && is_array( $value['alt'] ) ) {
			$config['alt'] = self::sanitize_group( $value['alt'], $config['alt'] );
		}

		if ( isset( $value['title'] ) && is_array( $value['title'] ) ) {
			$config['title'] = self::sanitize_group( $value['title'], $config['title'] );
		}

		if ( isset( $value['separator'] ) ) {
			$config['separator'] = self::sanitize_separator( (string) $value['separator'], $config['separator'] );
		}

		if ( isset( $value['custom_tokens'] ) ) {
			$config['custom_tokens'] = self::sanitize_custom_tokens(
				$value['custom_tokens'],
				$config['custom_tokens']
			);
		}

		return $config;
	}

	/**
	 * Default configuration values.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_config(): array {
		return array(
			'alt'           => array(
				'enabled' => true,
				'format'  => '%filename%',
			),
			'title'         => array(
				'enabled' => true,
				'format'  => '%title% %counter%',
			),
			'separator'     => '–',
			'custom_tokens' => array(
				'custom_1' => '',
				'custom_2' => '',
				'custom_3' => '',
			),
		);
	}

	/**
	 * Sanitize an attribute template group.
	 *
	 * @param array<string, mixed> $input    Raw group input.
	 * @param array<string, mixed> $defaults Default values.
	 *
	 * @return array<string, mixed>
	 */
	private static function sanitize_group( array $input, array $defaults ): array {
		$config = $defaults;

		if ( isset( $input['enabled'] ) ) {
			$config['enabled'] = self::to_bool( $input['enabled'], $defaults['enabled'] );
		}

		if ( isset( $input['format'] ) ) {
			$config['format'] = self::sanitize_template( (string) $input['format'], $defaults['format'] );
		}

		return $config;
	}

	/**
	 * Convert arbitrary input to boolean.
	 *
	 * @param mixed $value    Raw value.
	 * @param bool  $fallback Default value.
	 *
	 * @return bool
	 */
	private static function to_bool( $value, bool $fallback ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return 1 === (int) $value;
		}

		if ( is_string( $value ) ) {
			$normalized = strtolower( trim( $value ) );
			if ( in_array( $normalized, array( '1', 'true', 'yes', 'on' ), true ) ) {
				return true;
			}

			if ( in_array( $normalized, array( '0', 'false', 'no', 'off', '' ), true ) ) {
				return false;
			}
		}

		return $fallback;
	}

	/**
	 * Sanitize a format template string.
	 *
	 * @param string $template Raw template string.
	 * @param string $fallback Fallback template.
	 *
	 * @return string
	 */
	private static function sanitize_template( string $template, string $fallback ): string {
		$value = trim( wp_strip_all_tags( $template ) );
		$value = preg_replace( '/\s+/', ' ', $value );
		$value = is_string( $value ) ? $value : '';

		if ( '' === $value ) {
			return $fallback;
		}

		if ( function_exists( 'mb_substr' ) ) {
			$value = mb_substr( $value, 0, self::MAX_TEMPLATE_LENGTH );
		} else {
			$value = substr( $value, 0, self::MAX_TEMPLATE_LENGTH );
		}

		return trim( $value );
	}

	/**
	 * Sanitize separator string.
	 *
	 * @param string $separator Raw separator.
	 * @param string $fallback  Default separator.
	 *
	 * @return string
	 */
	private static function sanitize_separator( string $separator, string $fallback ): string {
		$clean = trim( wp_strip_all_tags( $separator ) );
		if ( '' === $clean ) {
			return $fallback;
		}

		if ( function_exists( 'mb_substr' ) ) {
			$clean = mb_substr( $clean, 0, 10 );
		} else {
			$clean = substr( $clean, 0, 10 );
		}

		return $clean;
	}

	/**
	 * Sanitize custom token values.
	 *
	 * @param mixed                $tokens   Raw custom token values.
	 * @param array<string, mixed> $defaults Default custom token values.
	 *
	 * @return array<string, string>
	 */
	private static function sanitize_custom_tokens( $tokens, array $defaults ): array {
		$tokens = is_array( $tokens ) ? $tokens : array();

		$custom_1 = isset( $tokens['custom_1'] ) ? sanitize_text_field( (string) $tokens['custom_1'] ) : (string) $defaults['custom_1'];
		$custom_2 = isset( $tokens['custom_2'] ) ? sanitize_text_field( (string) $tokens['custom_2'] ) : (string) $defaults['custom_2'];
		$custom_3 = isset( $tokens['custom_3'] ) ? sanitize_text_field( (string) $tokens['custom_3'] ) : (string) $defaults['custom_3'];

		if ( function_exists( 'mb_substr' ) ) {
			$custom_1 = mb_substr( $custom_1, 0, self::MAX_TEMPLATE_LENGTH );
			$custom_2 = mb_substr( $custom_2, 0, self::MAX_TEMPLATE_LENGTH );
			$custom_3 = mb_substr( $custom_3, 0, self::MAX_TEMPLATE_LENGTH );
		} else {
			$custom_1 = substr( $custom_1, 0, self::MAX_TEMPLATE_LENGTH );
			$custom_2 = substr( $custom_2, 0, self::MAX_TEMPLATE_LENGTH );
			$custom_3 = substr( $custom_3, 0, self::MAX_TEMPLATE_LENGTH );
		}

		return array(
			'custom_1' => $custom_1,
			'custom_2' => $custom_2,
			'custom_3' => $custom_3,
		);
	}
}
