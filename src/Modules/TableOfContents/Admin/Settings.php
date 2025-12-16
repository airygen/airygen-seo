<?php
/**
 * Stores configuration for Table of Contents output.
 *
 * @package Airygen\Modules\TableOfContents\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\TableOfContents\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Persists admin-configurable TOC settings.
 */
final class Settings {

	private const OPTION = Constants::OPTION_TOC;

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

		if ( isset( $sanitized['post_types'] ) && is_array( $sanitized['post_types'] ) ) {
			$config['post_types'] = self::sanitize_post_types( $sanitized['post_types'] );
		}

		if ( isset( $sanitized['levels'] ) && is_array( $sanitized['levels'] ) ) {
			$config['levels'] = self::sanitize_levels( $sanitized['levels'] );
		}

		if ( isset( $sanitized['position'] ) ) {
			$config['position'] = self::sanitize_position( $sanitized['position'] );
		}

		if ( isset( $sanitized['title_enabled'] ) ) {
			$config['title_enabled'] = self::to_bool( $sanitized['title_enabled'] );
		}

		if ( isset( $sanitized['title'] ) ) {
			$config['title'] = self::sanitize_string( $sanitized['title'], 120 );
		}
		if ( isset( $sanitized['title_level'] ) ) {
			$config['title_level'] = self::sanitize_title_level( $sanitized['title_level'] );
		}

		if ( isset( $sanitized['min_headings'] ) ) {
			$config['min_headings'] = self::sanitize_min_headings( $sanitized['min_headings'] );
		}

		if ( isset( $sanitized['smooth_scroll'] ) ) {
			$config['smooth_scroll'] = self::to_bool( $sanitized['smooth_scroll'] );
		}

		if ( isset( $sanitized['anchor_prefix'] ) ) {
			$config['anchor_prefix'] = self::sanitize_anchor_prefix( $sanitized['anchor_prefix'] );
		}

		if ( isset( $sanitized['add_numbers'] ) ) {
			$config['add_numbers'] = self::to_bool( $sanitized['add_numbers'] );
		}

		if ( isset( $sanitized['exclude_headings'] ) ) {
			$config['exclude_headings'] = self::sanitize_string( $sanitized['exclude_headings'], 200 );
		}

		if ( isset( $sanitized['collapse_on_load'] ) ) {
			$config['collapse_on_load'] = self::to_bool( $sanitized['collapse_on_load'] );
		}

		if ( isset( $sanitized['style'] ) && is_array( $sanitized['style'] ) ) {
			$config['style'] = array(
				'preset'           => self::sanitize_preset( $sanitized['style']['preset'] ?? $config['style']['preset'] ),
				'border_style'     => self::sanitize_border_style( $sanitized['style']['border_style'] ?? $config['style']['border_style'] ),
				'border_color'     => self::sanitize_color( $sanitized['style']['border_color'] ?? $config['style']['border_color'], $config['style']['border_color'] ),
				'border_radius'    => self::sanitize_dimension( $sanitized['style']['border_radius'] ?? $config['style']['border_radius'], 0, 50, $config['style']['border_radius'] ),
				'body_container'   => array(
					'border_width_top'    => self::sanitize_dimension( $sanitized['style']['body_container']['border_width_top'] ?? $config['style']['body_container']['border_width_top'], 0, 8, $config['style']['body_container']['border_width_top'] ),
					'border_width_right'  => self::sanitize_dimension( $sanitized['style']['body_container']['border_width_right'] ?? $config['style']['body_container']['border_width_right'], 0, 8, $config['style']['body_container']['border_width_right'] ),
					'border_width_bottom' => self::sanitize_dimension( $sanitized['style']['body_container']['border_width_bottom'] ?? $config['style']['body_container']['border_width_bottom'], 0, 8, $config['style']['body_container']['border_width_bottom'] ),
					'border_width_left'   => self::sanitize_dimension( $sanitized['style']['body_container']['border_width_left'] ?? $config['style']['body_container']['border_width_left'], 0, 8, $config['style']['body_container']['border_width_left'] ),
					'padding_top'         => self::sanitize_dimension( $sanitized['style']['body_container']['padding_top'] ?? $config['style']['body_container']['padding_top'], 0, 50, $config['style']['body_container']['padding_top'] ),
					'padding_right'       => self::sanitize_dimension( $sanitized['style']['body_container']['padding_right'] ?? $config['style']['body_container']['padding_right'], 0, 50, $config['style']['body_container']['padding_right'] ),
					'padding_bottom'      => self::sanitize_dimension( $sanitized['style']['body_container']['padding_bottom'] ?? $config['style']['body_container']['padding_bottom'], 0, 50, $config['style']['body_container']['padding_bottom'] ),
					'padding_left'        => self::sanitize_dimension( $sanitized['style']['body_container']['padding_left'] ?? $config['style']['body_container']['padding_left'], 0, 50, $config['style']['body_container']['padding_left'] ),
					'margin_top'          => self::sanitize_dimension( $sanitized['style']['body_container']['margin_top'] ?? $config['style']['body_container']['margin_top'], 0, 50, $config['style']['body_container']['margin_top'] ),
					'margin_right'        => self::sanitize_dimension( $sanitized['style']['body_container']['margin_right'] ?? $config['style']['body_container']['margin_right'], 0, 50, $config['style']['body_container']['margin_right'] ),
					'margin_bottom'       => self::sanitize_dimension( $sanitized['style']['body_container']['margin_bottom'] ?? $config['style']['body_container']['margin_bottom'], 0, 50, $config['style']['body_container']['margin_bottom'] ),
					'margin_left'         => self::sanitize_dimension( $sanitized['style']['body_container']['margin_left'] ?? $config['style']['body_container']['margin_left'], 0, 50, $config['style']['body_container']['margin_left'] ),
				),
				'toc_padding'      => self::sanitize_dimension( $sanitized['style']['toc_padding'] ?? $config['style']['toc_padding'], 0, 48, $config['style']['toc_padding'] ),
				'link_color'       => self::sanitize_color( $sanitized['style']['link_color'] ?? $config['style']['link_color'], $config['style']['link_color'] ),
				'link_size'        => self::sanitize_dimension( $sanitized['style']['link_size'] ?? $config['style']['link_size'], 10, 22, $config['style']['link_size'] ),
				'font_style'       => array(
					'bold'      => self::to_bool( $sanitized['style']['font_style']['bold'] ?? $config['style']['font_style']['bold'] ),
					'italic'    => self::to_bool( $sanitized['style']['font_style']['italic'] ?? $config['style']['font_style']['italic'] ),
					'underline' => self::to_bool( $sanitized['style']['font_style']['underline'] ?? $config['style']['font_style']['underline'] ),
				),
				'bg_color'         => self::sanitize_color( $sanitized['style']['bg_color'] ?? $config['style']['bg_color'], $config['style']['bg_color'] ),
				'header_container' => array(
					'border_width_top'    => self::sanitize_dimension( $sanitized['style']['header_container']['border_width_top'] ?? $config['style']['header_container']['border_width_top'], 0, 8, $config['style']['header_container']['border_width_top'] ),
					'border_width_right'  => self::sanitize_dimension( $sanitized['style']['header_container']['border_width_right'] ?? $config['style']['header_container']['border_width_right'], 0, 8, $config['style']['header_container']['border_width_right'] ),
					'border_width_bottom' => self::sanitize_dimension( $sanitized['style']['header_container']['border_width_bottom'] ?? $config['style']['header_container']['border_width_bottom'], 0, 8, $config['style']['header_container']['border_width_bottom'] ),
					'border_width_left'   => self::sanitize_dimension( $sanitized['style']['header_container']['border_width_left'] ?? $config['style']['header_container']['border_width_left'], 0, 8, $config['style']['header_container']['border_width_left'] ),
					'border_radius'       => self::sanitize_dimension( $sanitized['style']['header_container']['border_radius'] ?? $config['style']['header_container']['border_radius'], 0, 50, $config['style']['header_container']['border_radius'] ),
					'border_style'        => self::sanitize_border_style( $sanitized['style']['header_container']['border_style'] ?? $config['style']['header_container']['border_style'] ),
					'border_color'        => self::sanitize_color( $sanitized['style']['header_container']['border_color'] ?? $config['style']['header_container']['border_color'], $config['style']['header_container']['border_color'] ),
					'padding_top'         => self::sanitize_dimension( $sanitized['style']['header_container']['padding_top'] ?? $config['style']['header_container']['padding_top'], 0, 50, $config['style']['header_container']['padding_top'] ),
					'padding_right'       => self::sanitize_dimension( $sanitized['style']['header_container']['padding_right'] ?? $config['style']['header_container']['padding_right'], 0, 50, $config['style']['header_container']['padding_right'] ),
					'padding_bottom'      => self::sanitize_dimension( $sanitized['style']['header_container']['padding_bottom'] ?? $config['style']['header_container']['padding_bottom'], 0, 50, $config['style']['header_container']['padding_bottom'] ),
					'padding_left'        => self::sanitize_dimension( $sanitized['style']['header_container']['padding_left'] ?? $config['style']['header_container']['padding_left'], 0, 50, $config['style']['header_container']['padding_left'] ),
					'bg_color'            => self::sanitize_color( $sanitized['style']['header_container']['bg_color'] ?? $config['style']['header_container']['bg_color'], $config['style']['header_container']['bg_color'] ),
					'margin_top'          => self::sanitize_dimension( $sanitized['style']['header_container']['margin_top'] ?? $config['style']['header_container']['margin_top'], 0, 50, $config['style']['header_container']['margin_top'] ),
					'margin_right'        => self::sanitize_dimension( $sanitized['style']['header_container']['margin_right'] ?? $config['style']['header_container']['margin_right'], 0, 50, $config['style']['header_container']['margin_right'] ),
					'margin_bottom'       => self::sanitize_dimension( $sanitized['style']['header_container']['margin_bottom'] ?? $config['style']['header_container']['margin_bottom'], 0, 50, $config['style']['header_container']['margin_bottom'] ),
					'margin_left'         => self::sanitize_dimension( $sanitized['style']['header_container']['margin_left'] ?? $config['style']['header_container']['margin_left'], 0, 50, $config['style']['header_container']['margin_left'] ),
				),
				'header_title'     => array(
					'font_style' => array(
						'bold'      => self::to_bool( $sanitized['style']['header_title']['font_style']['bold'] ?? $config['style']['header_title']['font_style']['bold'] ),
						'italic'    => self::to_bool( $sanitized['style']['header_title']['font_style']['italic'] ?? $config['style']['header_title']['font_style']['italic'] ),
						'underline' => self::to_bool( $sanitized['style']['header_title']['font_style']['underline'] ?? $config['style']['header_title']['font_style']['underline'] ),
					),
					'color'      => self::sanitize_color( $sanitized['style']['header_title']['color'] ?? $config['style']['header_title']['color'], $config['style']['header_title']['color'] ),
					'font_size'  => self::sanitize_dimension( $sanitized['style']['header_title']['font_size'] ?? $config['style']['header_title']['font_size'], 10, 40, $config['style']['header_title']['font_size'] ),
				),
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
			'post_types'             => self::default_post_types(),
			'levels'                 => array( 2, 3 ),
			'position'               => 'after-first-paragraph',
			'title_enabled'          => true,
			'title'                  => 'Table of contents',
			'title_level'            => 'h2',
			'min_headings'           => 3,
			'smooth_scroll'          => true,
			'anchor_prefix'          => 'toc-',
			'add_numbers'            => true,
			'exclude_headings'       => '',
			'collapse_on_load'       => false,
			'style'                  => array(
				'preset'           => 'minimal',
				'border_style'     => 'dashed',
				'border_color'     => '#dddddd',
				'border_radius'    => 6,
				'body_container'   => array(
					'border_width_top'    => 1,
					'border_width_right'  => 1,
					'border_width_bottom' => 1,
					'border_width_left'   => 1,
					'padding_top'         => 9,
					'padding_right'       => 16,
					'padding_bottom'      => 16,
					'padding_left'        => 16,
					'margin_top'          => 0,
					'margin_right'        => 0,
					'margin_bottom'       => 0,
					'margin_left'         => 0,
				),
				'toc_padding'      => 20,
				'link_color'       => '#2563eb',
				'link_size'        => 14,
				'font_style'       => array(
					'bold'      => false,
					'italic'    => false,
					'underline' => false,
				),
				'bg_color'         => 'transparent',
				'header_container' => array(
					'border_width_top'    => 0,
					'border_width_right'  => 0,
					'border_width_bottom' => 0,
					'border_width_left'   => 7,
					'border_radius'       => 0,
					'border_style'        => 'solid',
					'border_color'        => '#a3a3a3',
					'padding_top'         => 0,
					'padding_right'       => 0,
					'padding_bottom'      => 0,
					'padding_left'        => 15,
					'bg_color'            => 'transparent',
					'margin_top'          => 0,
					'margin_right'        => 0,
					'margin_bottom'       => 12,
					'margin_left'         => 0,
				),
				'header_title'     => array(
					'font_style' => array(
						'bold'      => false,
						'italic'    => false,
						'underline' => false,
					),
					'color'      => '#0f172a',
					'font_size'  => 18,
				),
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
	 * Sanitize preset selection.
	 *
	 * @param mixed $value Input.
	 *
	 * @return string
	 */
	private static function sanitize_preset( $value ): string {
		$allowed = array( 'minimal', 'card', 'soft', 'accent', 'compact' );
		$preset  = is_scalar( $value ) ? (string) $value : 'minimal';

		return in_array( $preset, $allowed, true ) ? $preset : 'minimal';
	}

	/**
	 * Sanitize allowed heading levels.
	 *
	 * @param array<int, mixed> $levels Input levels.
	 *
	 * @return array<int, int>
	 */
	private static function sanitize_levels( array $levels ): array {
		$allowed = array( 2, 3, 4 );
		$clean   = array();

		foreach ( $levels as $level ) {
			$level = (int) $level;
			if ( in_array( $level, $allowed, true ) && ! in_array( $level, $clean, true ) ) {
				$clean[] = $level;
			}
		}

		return empty( $clean ) ? array( 2, 3 ) : $clean;
	}

	/**
	 * Sanitize position option.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	private static function sanitize_position( $value ): string {
		$position = is_scalar( $value ) ? (string) $value : '';
		$allowed  = array( 'before-content', 'after-first-paragraph' );
		if ( in_array( $position, $allowed, true ) ) {
			return $position;
		}

		return 'after-first-paragraph';
	}

	/**
	 * Sanitize title heading level.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	private static function sanitize_title_level( $value ): string {
		$level = is_scalar( $value ) ? strtolower( (string) $value ) : '';
		return in_array( $level, array( 'h2', 'h3', 'h4' ), true ) ? $level : 'h2';
	}

	/**
	 * Sanitize min headings.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return int
	 */
	private static function sanitize_min_headings( $value ): int {
		$numeric = (int) $value;
		if ( $numeric < 1 ) {
			return 1;
		}

		if ( $numeric > 20 ) {
			return 20;
		}

		return $numeric;
	}

	/**
	 * Sanitize the anchor prefix.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	private static function sanitize_anchor_prefix( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return 'toc-';
		}

		$prefix = trim( (string) $value );
		$prefix = strtolower( $prefix );
		$prefix = preg_replace( '/[^a-z0-9\-_]/', '', $prefix );
		$prefix = is_string( $prefix ) ? $prefix : '';

		return '' === $prefix ? 'toc-' : $prefix;
	}

	/**
	 * Sanitize post type list.
	 *
	 * @param array<int, mixed> $post_types Raw post type list.
	 *
	 * @return array<int, string>
	 */
	private static function sanitize_post_types( array $post_types ): array {
		$available = self::available_post_types();
		$sanitized = array();

		foreach ( $post_types as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' === $slug ) {
				continue;
			}

			if ( ! in_array( $slug, $available, true ) ) {
				continue;
			}

			if ( ! in_array( $slug, $sanitized, true ) ) {
				$sanitized[] = $slug;
			}
		}

		return empty( $sanitized ) ? self::default_post_types() : $sanitized;
	}

	/**
	 * Resolve available post types.
	 *
	 * @return array<int, string>
	 */
	private static function available_post_types(): array {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$post_types = array_diff( $post_types, array( 'attachment', 'revision', 'nav_menu_item' ) );

		return array_values( $post_types );
	}

	/**
	 * Default enabled post types.
	 *
	 * @return array<int, string>
	 */
	private static function default_post_types(): array {
		$types = self::available_post_types();
		if ( in_array( 'post', $types, true ) ) {
			return array( 'post' );
		}

		return $types;
	}

	/**
	 * Sanitize border style.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	private static function sanitize_border_style( $value ): string {
		$style   = is_scalar( $value ) ? (string) $value : '';
		$allowed = array( 'solid', 'dashed', 'dotted' );
		if ( in_array( $style, $allowed, true ) ) {
			return $style;
		}

		return 'solid';
	}

	/**
	 * Sanitize color values.
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

		$normalized = sanitize_hex_color( $normalized );
		return is_string( $normalized ) && '' !== $normalized ? $normalized : $fallback;
	}

	/**
	 * Sanitize a numeric dimension with bounds.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $min Minimum.
	 * @param int   $max Maximum.
	 * @param int   $fallback Default.
	 *
	 * @return int
	 */
	private static function sanitize_dimension( $value, int $min, int $max, int $fallback ): int {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		$numeric = (int) $value;
		if ( $numeric < $min ) {
			return $min;
		}

		if ( $numeric > $max ) {
			return $max;
		}

		return $numeric;
	}
}
