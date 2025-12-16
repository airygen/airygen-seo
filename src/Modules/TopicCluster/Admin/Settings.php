<?php
/**
 * Stores configuration for Topic Cluster behavior.
 *
 * @package Airygen\Modules\TopicCluster\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\TopicCluster\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Persists admin-configurable Topic Cluster settings.
 */
final class Settings {

	private const OPTION = Constants::OPTION_TOPIC_CLUSTER;

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

		$config['override_breadcrumbs']   = self::to_bool(
			$sanitized['override_breadcrumbs'] ?? $config['override_breadcrumbs']
		);
		$config['manual_output_enabled']  = self::to_bool(
			$sanitized['manual_output_enabled'] ?? $config['manual_output_enabled']
		);
		$config['auto_injection_enabled'] = self::to_bool(
			$sanitized['auto_injection_enabled'] ?? $config['auto_injection_enabled']
		);
		$config['override_wp_adjacent']   = self::to_bool(
			$sanitized['override_wp_adjacent'] ?? $config['override_wp_adjacent']
		);

		if ( isset( $sanitized['insert_position'] ) ) {
			$config['insert_position'] = self::sanitize_insert_position( $sanitized['insert_position'] );
		}

		if ( isset( $sanitized['post_types'] ) && is_array( $sanitized['post_types'] ) ) {
			$config['post_types'] = self::sanitize_post_types( $sanitized['post_types'] );
		}

		$config['title_enabled'] = self::to_bool(
			$sanitized['title_enabled'] ?? $config['title_enabled']
		);

		if ( isset( $sanitized['title_text'] ) && is_string( $sanitized['title_text'] ) ) {
			$config['title_text'] = sanitize_text_field( $sanitized['title_text'] );
		}

		$config['relation_text_l1'] = self::sanitize_relation_text(
			$sanitized['relation_text_l1'] ?? $config['relation_text_l1'],
			(string) $config['relation_text_l1'],
			0
		);
		$config['relation_text_l2'] = self::sanitize_relation_text(
			$sanitized['relation_text_l2'] ?? $config['relation_text_l2'],
			(string) $config['relation_text_l2'],
			1
		);
		$config['relation_text_l3'] = self::sanitize_relation_text(
			$sanitized['relation_text_l3'] ?? $config['relation_text_l3'],
			(string) $config['relation_text_l3'],
			1
		);

		if ( isset( $sanitized['title_level'] ) && is_string( $sanitized['title_level'] ) ) {
			$config['title_level'] = self::sanitize_title_level( $sanitized['title_level'] );
		}

		if ( isset( $sanitized['style_type'] ) && is_string( $sanitized['style_type'] ) ) {
			$config['style_type'] = sanitize_key( $sanitized['style_type'] );
		}

		if ( isset( $sanitized['style'] ) && is_array( $sanitized['style'] ) ) {
			$config['style'] = self::sanitize_style( $sanitized['style'], $config['style'] );
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
			'manual_output_enabled'  => false,
			'auto_injection_enabled' => false,
			'override_breadcrumbs'   => false,
			'override_wp_adjacent'   => false,
			'insert_position'        => 'after-content',
			'post_types'             => self::default_post_types(),
			'title_enabled'          => true,
			'title_text'             => 'Featured topics',
			'relation_text_l1'       => 'Explore the main articles in this series.',
			'relation_text_l2'       => 'This article is part of the %s series. The links below expand on the topic.',
			'relation_text_l3'       => 'This article expands on %s.',
			'title_level'            => 'h2',
			'style_type'             => 'snow-slate',
			'style'                  => array(
				'preset'              => 'snow-slate',
				'show_border'         => true,
				'border_style'        => 'dashed',
				'border_color'        => '#dddddd',
				'border_width_top'    => 1,
				'border_width_right'  => 1,
				'border_width_bottom' => 1,
				'border_width_left'   => 1,
				'border_radius'       => 6,
				'padding_top'         => 9,
				'padding_right'       => 16,
				'padding_bottom'      => 16,
				'padding_left'        => 16,
				'margin_top'          => 0,
				'margin_right'        => 0,
				'margin_bottom'       => 0,
				'margin_left'         => 0,
				'bg_color'            => 'transparent',
				'item_text_color'     => '#0f172a',
				'item_font_size'      => 14,
				'item_bold'           => false,
				'item_italic'         => false,
				'item_underline'      => false,
				'item_list_style'     => 'disc',
				'item_gap'            => 0,
				'header_container'    => array(
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
				'header_title'        => array(
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
	 * Sanitize insert position string.
	 *
	 * @param mixed $value Input value.
	 *
	 * @return string
	 */
	private static function sanitize_insert_position( $value ): string {
		if ( ! is_string( $value ) ) {
			return 'after-content';
		}

		$value = strtolower( trim( $value ) );
		if ( 'before-content' === $value || 'after-content' === $value ) {
			return $value;
		}

		return 'after-content';
	}

	/**
	 * Sanitize heading level.
	 *
	 * @param string $value Input value.
	 *
	 * @return string
	 */
	private static function sanitize_title_level( string $value ): string {
		$value = strtolower( trim( $value ) );
		if ( 'h2' === $value || 'h3' === $value || 'h4' === $value ) {
			return $value;
		}

		return 'h2';
	}

	/**
	 * Sanitize relation link description text.
	 *
	 * @param mixed  $value Raw value.
	 * @param string $fallback Fallback value.
	 * @param int    $required_tokens Number of required %s tokens.
	 *
	 * @return string
	 */
	private static function sanitize_relation_text( $value, string $fallback, int $required_tokens ): string {
		if ( ! is_string( $value ) ) {
			return $fallback;
		}

		$normalized = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $value ) ) ?? '' );
		if ( '' === $normalized ) {
			return $fallback;
		}

		$token_count = substr_count( $normalized, '%s' );
		if ( $token_count !== $required_tokens ) {
			return $fallback;
		}

		return $normalized;
	}

	/**
	 * Sanitize style payload.
	 *
	 * @param array<string, mixed> $style Raw style values.
	 * @param array<string, mixed> $defaults Default style values.
	 *
	 * @return array<string, mixed>
	 */
	private static function sanitize_style( array $style, array $defaults ): array {
		$next = $defaults;

		$next['preset']              = isset( $style['preset'] ) ? sanitize_key( (string) $style['preset'] ) : $defaults['preset'];
		$next['show_border']         = self::to_bool( $style['show_border'] ?? $defaults['show_border'] );
		$next['border_style']        = self::sanitize_border_style( (string) ( $style['border_style'] ?? $defaults['border_style'] ) );
		$next['border_color']        = self::sanitize_hex_or_default( $style['border_color'] ?? $defaults['border_color'], (string) $defaults['border_color'] );
		$next['border_width_top']    = self::sanitize_range_int( $style['border_width_top'] ?? $defaults['border_width_top'], 0, 50, (int) $defaults['border_width_top'] );
		$next['border_width_right']  = self::sanitize_range_int( $style['border_width_right'] ?? $defaults['border_width_right'], 0, 50, (int) $defaults['border_width_right'] );
		$next['border_width_bottom'] = self::sanitize_range_int( $style['border_width_bottom'] ?? $defaults['border_width_bottom'], 0, 50, (int) $defaults['border_width_bottom'] );
		$next['border_width_left']   = self::sanitize_range_int( $style['border_width_left'] ?? $defaults['border_width_left'], 0, 50, (int) $defaults['border_width_left'] );
		$next['border_radius']       = self::sanitize_range_int( $style['border_radius'] ?? $defaults['border_radius'], 0, 50, (int) $defaults['border_radius'] );
		$next['padding_top']         = self::sanitize_range_int( $style['padding_top'] ?? $defaults['padding_top'], 0, 50, (int) $defaults['padding_top'] );
		$next['padding_right']       = self::sanitize_range_int( $style['padding_right'] ?? $defaults['padding_right'], 0, 50, (int) $defaults['padding_right'] );
		$next['padding_bottom']      = self::sanitize_range_int( $style['padding_bottom'] ?? $defaults['padding_bottom'], 0, 50, (int) $defaults['padding_bottom'] );
		$next['padding_left']        = self::sanitize_range_int( $style['padding_left'] ?? $defaults['padding_left'], 0, 50, (int) $defaults['padding_left'] );
		$next['margin_top']          = self::sanitize_range_int( $style['margin_top'] ?? $defaults['margin_top'], 0, 50, (int) $defaults['margin_top'] );
		$next['margin_right']        = self::sanitize_range_int( $style['margin_right'] ?? $defaults['margin_right'], 0, 50, (int) $defaults['margin_right'] );
		$next['margin_bottom']       = self::sanitize_range_int( $style['margin_bottom'] ?? $defaults['margin_bottom'], 0, 50, (int) $defaults['margin_bottom'] );
		$next['margin_left']         = self::sanitize_range_int( $style['margin_left'] ?? $defaults['margin_left'], 0, 50, (int) $defaults['margin_left'] );
		$next['bg_color']            = self::sanitize_hex_or_default( $style['bg_color'] ?? $defaults['bg_color'], (string) $defaults['bg_color'] );
		$next['item_text_color']     = self::sanitize_hex_or_default( $style['item_text_color'] ?? $defaults['item_text_color'], (string) $defaults['item_text_color'] );
		$next['item_font_size']      = self::sanitize_range_int( $style['item_font_size'] ?? $defaults['item_font_size'], 10, 24, (int) $defaults['item_font_size'] );
		$next['item_bold']           = self::to_bool( $style['item_bold'] ?? $defaults['item_bold'] );
		$next['item_italic']         = self::to_bool( $style['item_italic'] ?? $defaults['item_italic'] );
		$next['item_underline']      = self::to_bool( $style['item_underline'] ?? $defaults['item_underline'] );
		$next['item_list_style']     = self::sanitize_item_list_style( (string) ( $style['item_list_style'] ?? $defaults['item_list_style'] ) );
		$next['item_gap']            = self::sanitize_range_int( $style['item_gap'] ?? $defaults['item_gap'], 0, 20, (int) $defaults['item_gap'] );
		$next['header_container']    = self::sanitize_header_container_style(
			is_array( $style['header_container'] ?? null ) ? $style['header_container'] : array(),
			is_array( $defaults['header_container'] ?? null ) ? $defaults['header_container'] : array()
		);
		$next['header_title']        = self::sanitize_header_title_style(
			is_array( $style['header_title'] ?? null ) ? $style['header_title'] : array(),
			is_array( $defaults['header_title'] ?? null ) ? $defaults['header_title'] : array()
		);

		return $next;
	}

	/**
	 * Sanitize border style.
	 *
	 * @param string $value Raw border style.
	 *
	 * @return string
	 */
	private static function sanitize_border_style( string $value ): string {
		$value = strtolower( trim( $value ) );
		if ( 'dashed' === $value || 'dotted' === $value ) {
			return $value;
		}

		return 'solid';
	}

	/**
	 * Sanitize item list style.
	 *
	 * @param string $value Raw list style.
	 *
	 * @return string
	 */
	private static function sanitize_item_list_style( string $value ): string {
		$value = strtolower( trim( $value ) );
		if ( 'disc' === $value || 'decimal' === $value ) {
			return $value;
		}

		return 'none';
	}

	/**
	 * @param array<string,mixed> $style Raw style.
	 * @param array<string,mixed> $defaults Defaults.
	 *
	 * @return array<string,mixed>
	 */
	private static function sanitize_header_container_style( array $style, array $defaults ): array {
		return array(
			'border_width_top'    => self::sanitize_range_int( $style['border_width_top'] ?? $defaults['border_width_top'] ?? 0, 0, 20, (int) ( $defaults['border_width_top'] ?? 0 ) ),
			'border_width_right'  => self::sanitize_range_int( $style['border_width_right'] ?? $defaults['border_width_right'] ?? 0, 0, 20, (int) ( $defaults['border_width_right'] ?? 0 ) ),
			'border_width_bottom' => self::sanitize_range_int( $style['border_width_bottom'] ?? $defaults['border_width_bottom'] ?? 0, 0, 20, (int) ( $defaults['border_width_bottom'] ?? 0 ) ),
			'border_width_left'   => self::sanitize_range_int( $style['border_width_left'] ?? $defaults['border_width_left'] ?? 0, 0, 20, (int) ( $defaults['border_width_left'] ?? 0 ) ),
			'border_radius'       => self::sanitize_range_int( $style['border_radius'] ?? $defaults['border_radius'] ?? 0, 0, 50, (int) ( $defaults['border_radius'] ?? 0 ) ),
			'border_style'        => self::sanitize_border_style( (string) ( $style['border_style'] ?? $defaults['border_style'] ?? 'solid' ) ),
			'border_color'        => self::sanitize_hex_or_default( $style['border_color'] ?? $defaults['border_color'] ?? '#cbd5e1', (string) ( $defaults['border_color'] ?? '#cbd5e1' ) ),
			'padding_top'         => self::sanitize_range_int( $style['padding_top'] ?? $defaults['padding_top'] ?? 0, 0, 50, (int) ( $defaults['padding_top'] ?? 0 ) ),
			'padding_right'       => self::sanitize_range_int( $style['padding_right'] ?? $defaults['padding_right'] ?? 0, 0, 50, (int) ( $defaults['padding_right'] ?? 0 ) ),
			'padding_bottom'      => self::sanitize_range_int( $style['padding_bottom'] ?? $defaults['padding_bottom'] ?? 0, 0, 50, (int) ( $defaults['padding_bottom'] ?? 0 ) ),
			'padding_left'        => self::sanitize_range_int( $style['padding_left'] ?? $defaults['padding_left'] ?? 0, 0, 50, (int) ( $defaults['padding_left'] ?? 0 ) ),
			'bg_color'            => self::sanitize_hex_or_default( $style['bg_color'] ?? $defaults['bg_color'] ?? '#f8fafc', (string) ( $defaults['bg_color'] ?? '#f8fafc' ) ),
			'margin_top'          => self::sanitize_range_int( $style['margin_top'] ?? $defaults['margin_top'] ?? 0, 0, 50, (int) ( $defaults['margin_top'] ?? 0 ) ),
			'margin_right'        => self::sanitize_range_int( $style['margin_right'] ?? $defaults['margin_right'] ?? 0, 0, 50, (int) ( $defaults['margin_right'] ?? 0 ) ),
			'margin_bottom'       => self::sanitize_range_int( $style['margin_bottom'] ?? $defaults['margin_bottom'] ?? 12, 0, 50, (int) ( $defaults['margin_bottom'] ?? 12 ) ),
			'margin_left'         => self::sanitize_range_int( $style['margin_left'] ?? $defaults['margin_left'] ?? 0, 0, 50, (int) ( $defaults['margin_left'] ?? 0 ) ),
		);
	}

	/**
	 * @param array<string,mixed> $style Raw style.
	 * @param array<string,mixed> $defaults Defaults.
	 *
	 * @return array<string,mixed>
	 */
	private static function sanitize_header_title_style( array $style, array $defaults ): array {
		$font_style = is_array( $style['font_style'] ?? null ) ? $style['font_style'] : array();
		$fallback   = is_array( $defaults['font_style'] ?? null ) ? $defaults['font_style'] : array();
		return array(
			'font_style' => array(
				'bold'      => self::to_bool( $font_style['bold'] ?? $fallback['bold'] ?? true ),
				'italic'    => self::to_bool( $font_style['italic'] ?? $fallback['italic'] ?? false ),
				'underline' => self::to_bool( $font_style['underline'] ?? $fallback['underline'] ?? false ),
			),
			'color'      => self::sanitize_hex_or_default( $style['color'] ?? $defaults['color'] ?? '#0f172a', (string) ( $defaults['color'] ?? '#0f172a' ) ),
			'font_size'  => self::sanitize_range_int( $style['font_size'] ?? $defaults['font_size'] ?? 18, 10, 40, (int) ( $defaults['font_size'] ?? 18 ) ),
		);
	}

	/**
	 * Sanitize ranged integer.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $min Minimum.
	 * @param int   $max Maximum.
	 * @param int   $fallback Fallback.
	 *
	 * @return int
	 */
	private static function sanitize_range_int( $value, int $min, int $max, int $fallback ): int {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		$number = (int) $value;
		if ( $number < $min ) {
			return $min;
		}
		if ( $number > $max ) {
			return $max;
		}

		return $number;
	}

	/**
	 * Sanitize hex color with fallback.
	 *
	 * @param mixed  $value Raw value.
	 * @param string $fallback Fallback color.
	 *
	 * @return string
	 */
	private static function sanitize_hex_or_default( $value, string $fallback ): string {
		if ( ! is_string( $value ) ) {
			return $fallback;
		}

		$value = trim( $value );
		if ( 'transparent' === strtolower( $value ) ) {
			return 'transparent';
		}

		if ( 1 === preg_match( '/^rgba?\(\s*(?:\d{1,3}\s*,\s*){2}\d{1,3}(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/i', $value ) ) {
			return $value;
		}

		$sanitized = sanitize_hex_color( $value );
		return is_string( $sanitized ) && '' !== $sanitized ? $sanitized : $fallback;
	}

	/**
	 * Sanitize post types list.
	 *
	 * @param array<int, mixed> $post_types Raw post types.
	 *
	 * @return array<int, string>
	 */
	private static function sanitize_post_types( array $post_types ): array {
		$allowed = self::available_post_types();
		$cleaned = array();

		foreach ( $post_types as $post_type ) {
			if ( is_string( $post_type ) && isset( $allowed[ $post_type ] ) ) {
				$cleaned[] = $post_type;
			}
		}

		$cleaned = array_values( array_unique( $cleaned ) );
		if ( empty( $cleaned ) ) {
			return self::default_post_types();
		}

		return $cleaned;
	}

	/**
	 * Default post types.
	 *
	 * @return array<int, string>
	 */
	private static function default_post_types(): array {
		$defaults = array( 'post' );
		$allowed  = self::available_post_types();
		$filtered = array();

		foreach ( $defaults as $post_type ) {
			if ( isset( $allowed[ $post_type ] ) ) {
				$filtered[] = $post_type;
			}
		}

		return empty( $filtered ) ? array_keys( $allowed ) : $filtered;
	}

	/**
	 * Public post types eligible for settings.
	 *
	 * @return array<string, bool>
	 */
	private static function available_post_types(): array {
		$types = get_post_types( array( 'public' => true ), 'names' );
		if ( empty( $types ) ) {
			return array( 'post' => true );
		}

		return array_fill_keys( $types, true );
	}
}
