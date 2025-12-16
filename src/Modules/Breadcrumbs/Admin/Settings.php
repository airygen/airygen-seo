<?php
/**
 * Stores configuration for breadcrumb output.
 *
 * @package Airygen\Modules\Breadcrumbs\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\Breadcrumbs\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Persists admin-configurable breadcrumb settings.
 */
final class Settings {

	private const OPTION = Constants::OPTION_BREADCRUMBS;

	/**
	 * Ensure the option exists with defaults.
	 *
	 * @return void
	 */
	public static function ensure_exists(): void {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::defaults(), '', 'no' );
			return;
		}

		self::update( (array) get_option( self::OPTION, array() ) );
	}

	/**
	 * Retrieve sanitized configuration.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		return self::sanitize( get_option( self::OPTION, array() ) );
	}

	/**
	 * Persist sanitized settings.
	 *
	 * @param array<string, mixed> $value Raw option payload.
	 *
	 * @return void
	 */
	public static function update( array $value ): void {
		update_option( self::OPTION, self::sanitize( $value ), 'no' );
	}

	/**
	 * Sanitize input values against defaults.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return array<string, mixed>
	 */
	private static function sanitize( $value ): array {
		$config    = self::defaults();
		$sanitized = is_array( $value ) ? $value : array();

		$config['manual_output_enabled']  = self::to_bool( $sanitized['manual_output_enabled'] ?? $config['manual_output_enabled'] );
		$config['auto_injection_enabled'] = self::to_bool( $sanitized['auto_injection_enabled'] ?? $config['auto_injection_enabled'] );

		if ( isset( $sanitized['injection_position'] ) ) {
			$position = (string) $sanitized['injection_position'];
			if ( in_array( $position, array( 'before_content', 'after_content' ), true ) ) {
				$config['injection_position'] = $position;
			}
		}

		$config['separator'] = self::sanitize_separator( $sanitized['separator'] ?? $config['separator'] );
		$config['prefix']    = self::sanitize_string( $sanitized['prefix'] ?? $config['prefix'], 80 );

		if ( isset( $sanitized['home'] ) && is_array( $sanitized['home'] ) ) {
			$config['home'] = array(
				'display' => self::to_bool( $sanitized['home']['display'] ?? $config['home']['display'] ),
				'label'   => self::sanitize_string( $sanitized['home']['label'] ?? $config['home']['label'], 80 ),
				'url'     => trailingslashit( home_url() ),
			);
		}

		if ( isset( $sanitized['labels'] ) && is_array( $sanitized['labels'] ) ) {
			$config['labels'] = array(
				'archive' => self::sanitize_string( $sanitized['labels']['archive'] ?? $config['labels']['archive'], 120 ),
				'search'  => self::sanitize_string( $sanitized['labels']['search'] ?? $config['labels']['search'], 120 ),
				'error'   => self::sanitize_string( $sanitized['labels']['error'] ?? $config['labels']['error'], 120 ),
			);
		}

		if ( isset( $sanitized['display'] ) && is_array( $sanitized['display'] ) ) {
			$config['display'] = array(
				'showCurrent'    => self::to_bool( $sanitized['display']['showCurrent'] ?? $config['display']['showCurrent'] ),
				'showAncestors'  => self::to_bool( $sanitized['display']['showAncestors'] ?? $config['display']['showAncestors'] ),
				'showBlog'       => self::to_bool( $sanitized['display']['showBlog'] ?? $config['display']['showBlog'] ),
				'showPagination' => self::to_bool( $sanitized['display']['showPagination'] ?? $config['display']['showPagination'] ),
				'hideTaxonomy'   => self::to_bool( $sanitized['display']['hideTaxonomy'] ?? $config['display']['hideTaxonomy'] ),
			);
		}

		if ( isset( $sanitized['style'] ) && is_array( $sanitized['style'] ) ) {
			$config['style'] = array(
				'fontSize'       => self::sanitize_font_size( $sanitized['style']['fontSize'] ?? $config['style']['fontSize'] ),
				'textColor'      => self::sanitize_color_or_transparent( $sanitized['style']['textColor'] ?? $config['style']['textColor'], $config['style']['textColor'] ),
				'linkColor'      => self::sanitize_color_or_transparent( $sanitized['style']['linkColor'] ?? $config['style']['linkColor'], $config['style']['linkColor'] ),
				'underlineLinks' => self::to_bool( $sanitized['style']['underlineLinks'] ?? $config['style']['underlineLinks'] ),
				'borderWidth'    => self::sanitize_border_width( $sanitized['style']['borderWidth'] ?? $config['style']['borderWidth'] ),
				'borderColor'    => self::sanitize_color_or_transparent( $sanitized['style']['borderColor'] ?? $config['style']['borderColor'], $config['style']['borderColor'] ),
				'padding'        => self::sanitize_padding( $sanitized['style']['padding'] ?? $config['style']['padding'] ),
				'bgColor'        => self::sanitize_color_or_transparent( $sanitized['style']['bgColor'] ?? $config['style']['bgColor'], $config['style']['bgColor'] ),
			);
		}

		return $config;
	}

	/**
	 * Default configuration.
	 *
	 * @return array<string, mixed>
	 */
	private static function defaults(): array {
		return array(
			'manual_output_enabled'  => true,
			'auto_injection_enabled' => true,
			'injection_position'     => 'before_content',
			'separator'              => '›',
			'prefix'                 => '',
			'home'                   => array(
				'display' => true,
				'label'   => 'Home',
				'url'     => trailingslashit( home_url() ),
			),
			'labels'                 => array(
				'archive' => 'Archives for %s',
				'search'  => 'Results for %s',
				'error'   => '404: Page not found',
			),
			'display'                => array(
				'showCurrent'    => true,
				'showAncestors'  => false,
				'showBlog'       => false,
				'showPagination' => true,
				'hideTaxonomy'   => false,
			),
			'style'                  => array(
				'fontSize'       => 14,
				'textColor'      => '#1f2937',
				'linkColor'      => '#2563eb',
				'underlineLinks' => false,
				'borderWidth'    => 1,
				'borderColor'    => '#e2e8f0',
				'padding'        => 12,
				'bgColor'        => '#ffffff',
			),
		);
	}

	/**
	 * Normalize boolean-ish values.
	 *
	 * @param mixed $value Input.
	 *
	 * @return bool
	 */
	private static function to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			$value = strtolower( $value );
			return in_array( $value, array( '1', 'true', 'yes', 'on' ), true );
		}

		return (bool) $value;
	}

	/**
	 * Sanitize arbitrary string values.
	 *
	 * @param mixed $value Input string.
	 * @param int   $length Maximum length.
	 *
	 * @return string
	 */
	private static function sanitize_string( $value, int $length ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$normalized = trim( (string) $value );
		if ( '' === $normalized ) {
			return '';
		}

		return mb_substr( wp_strip_all_tags( $normalized ), 0, $length );
	}

	/**
	 * Sanitize separator characters.
	 *
	 * @param mixed $value Raw separator.
	 *
	 * @return string
	 */
	private static function sanitize_separator( $value ): string {
		$fallback = '›';
		if ( ! is_scalar( $value ) ) {
			return $fallback;
		}

		$normalized = trim( (string) $value );
		if ( '' === $normalized ) {
			return $fallback;
		}

		return mb_substr( $normalized, 0, 10 );
	}

	/**
	 * Sanitize URL strings.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	private static function sanitize_url( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return trailingslashit( home_url() );
		}

		$url = esc_url_raw( trim( (string) $value ) );
		return '' === $url ? trailingslashit( home_url() ) : $url;
	}

	/**
	 * Sanitize color value (hex).
	 *
	 * @param mixed  $value Raw value.
	 * @param string $fallback Fallback color.
	 *
	 * @return string
	 */
	private static function sanitize_color( $value, string $fallback ): string {
		if ( ! is_scalar( $value ) ) {
			return $fallback;
		}

		$normalized = trim( (string) $value );
		if ( '' === $normalized ) {
			return $fallback;
		}

		if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $normalized ) ) {
			return $normalized;
		}

		return $fallback;
	}

	/**
	 * Sanitize font size in pixels.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return int
	 */
	private static function sanitize_font_size( $value ): int {
		$size = is_numeric( $value ) ? (int) $value : 14;
		if ( $size < 10 ) {
			return 10;
		}
		if ( $size > 24 ) {
			return 24;
		}
		return $size;
	}

	/**
	 * Sanitize border width in pixels.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return int
	 */
	private static function sanitize_border_width( $value ): int {
		$size = is_numeric( $value ) ? (int) $value : 1;
		if ( $size < 0 ) {
			return 0;
		}
		if ( $size > 10 ) {
			return 10;
		}
		return $size;
	}

	/**
	 * Sanitize container padding in pixels.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return int
	 */
	private static function sanitize_padding( $value ): int {
		$size = is_numeric( $value ) ? (int) $value : 12;
		if ( $size < 0 ) {
			return 0;
		}
		if ( $size > 64 ) {
			return 64;
		}
		return $size;
	}

	/**
	 * Sanitize color value and allow the transparent keyword.
	 *
	 * @param mixed  $value Raw value.
	 * @param string $fallback Fallback color.
	 *
	 * @return string
	 */
	private static function sanitize_color_or_transparent( $value, string $fallback ): string {
		if ( is_string( $value ) && 'transparent' === strtolower( trim( $value ) ) ) {
			return 'transparent';
		}

		return self::sanitize_color( $value, $fallback );
	}
}
