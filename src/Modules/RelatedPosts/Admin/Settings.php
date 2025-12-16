<?php
/**
 * Stores configuration for Related Posts.
 *
 * @package Airygen\Modules\RelatedPosts\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\RelatedPosts\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Persists Related Posts settings.
 */
final class Settings {

	private const OPTION = Constants::OPTION_RELATED_POSTS;

	/**
	 * Ensure option exists.
	 *
	 * @return void
	 */
	public static function ensure_exists(): void {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::default_config(), '', 'no' );
			return;
		}

		self::update( (array) get_option( self::OPTION, array() ) );
	}

	/**
	 * Get settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		return self::sanitize( get_option( self::OPTION, array() ) );
	}

	/**
	 * Update settings.
	 *
	 * @param array<string,mixed> $value Raw payload.
	 *
	 * @return void
	 */
	public static function update( array $value ): void {
		update_option( self::OPTION, self::sanitize( $value ), 'no' );
	}

	/**
	 * Sanitize payload.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return array<string,mixed>
	 */
	private static function sanitize( $value ): array {
		$config = self::default_config();
		if ( ! is_array( $value ) ) {
			return $config;
		}

		if ( array_key_exists( 'enabled', $value ) ) {
			$config['enabled'] = (bool) $value['enabled'];
		}
		if ( array_key_exists( 'title_enabled', $value ) ) {
			$config['title_enabled'] = (bool) $value['title_enabled'];
		}
		if ( isset( $value['title_text'] ) && is_scalar( $value['title_text'] ) ) {
			$config['title_text'] = sanitize_text_field( (string) $value['title_text'] );
		}
		if ( isset( $value['title_level'] ) && is_string( $value['title_level'] ) ) {
			$title_level = sanitize_key( $value['title_level'] );
			if ( in_array( $title_level, array( 'h2', 'h3', 'h4' ), true ) ) {
				$config['title_level'] = $title_level;
			}
		}

		if ( isset( $value['template'] ) && is_string( $value['template'] ) ) {
			$template = sanitize_key( $value['template'] );
			if ( in_array( $template, array( 'single_column', 'sidebar_left' ), true ) ) {
				$config['template'] = $template;
			}
		}
		if ( isset( $value['footer_columns'] ) ) {
			$config['footer_columns'] = self::sanitize_int( $value['footer_columns'], 1, 3, (int) $config['footer_columns'] );
		}

		if ( isset( $value['block_order'] ) && is_array( $value['block_order'] ) ) {
			$config['block_order'] = self::sanitize_block_order( $value['block_order'] );
		}
		if ( isset( $value['block_regions'] ) && is_array( $value['block_regions'] ) ) {
			$config['block_regions'] = self::sanitize_block_regions( $value['block_regions'] );
		}
		$config['block_regions']    = self::normalize_footer_regions_by_columns(
			$config['block_regions'],
			(int) $config['footer_columns']
		);
		$config['grid_container']   = self::sanitize_container_style(
			isset( $value['grid_container'] ) && is_array( $value['grid_container'] ) ? $value['grid_container'] : array(),
			is_array( $config['grid_container'] ) ? $config['grid_container'] : array()
		);
		$config['post_container']   = self::sanitize_container_style(
			isset( $value['post_container'] ) && is_array( $value['post_container'] ) ? $value['post_container'] : array(),
			is_array( $config['post_container'] ) ? $config['post_container'] : array()
		);
		$config['header_container'] = self::sanitize_header_container_style(
			isset( $value['header_container'] ) && is_array( $value['header_container'] ) ? $value['header_container'] : array(),
			is_array( $config['header_container'] ) ? $config['header_container'] : array()
		);
		$config['header_title']     = self::sanitize_header_title_style(
			isset( $value['header_title'] ) && is_array( $value['header_title'] ) ? $value['header_title'] : array(),
			is_array( $config['header_title'] ) ? $config['header_title'] : array()
		);

		if ( isset( $value['featured_image_size'] ) && is_string( $value['featured_image_size'] ) ) {
			$config['featured_image_size'] = sanitize_key( $value['featured_image_size'] );
		}
		$config['featured_image_radius'] = self::sanitize_int( $value['featured_image_radius'] ?? null, 0, 64, (int) $config['featured_image_radius'] );

		$config['title_font_size'] = self::sanitize_int( $value['title_font_size'] ?? null, 10, 64, (int) $config['title_font_size'] );
		$config['title_color']     = self::sanitize_color( $value['title_color'] ?? null, (string) $config['title_color'] );
		$config['title_bold']      = isset( $value['title_bold'] ) ? (bool) $value['title_bold'] : (bool) $config['title_bold'];
		$config['title_italic']    = isset( $value['title_italic'] ) ? (bool) $value['title_italic'] : (bool) $config['title_italic'];

		$config['excerpt_font_size']   = self::sanitize_int( $value['excerpt_font_size'] ?? null, 10, 48, (int) $config['excerpt_font_size'] );
		$config['excerpt_color']       = self::sanitize_color( $value['excerpt_color'] ?? null, (string) $config['excerpt_color'] );
		$config['excerpt_max_chars']   = self::sanitize_int( $value['excerpt_max_chars'] ?? null, 30, 1000, (int) $config['excerpt_max_chars'] );
		$config['excerpt_fade_mask']   = isset( $value['excerpt_fade_mask'] ) ? (bool) $value['excerpt_fade_mask'] : (bool) $config['excerpt_fade_mask'];
		$config['excerpt_fade_color']  = self::sanitize_color( $value['excerpt_fade_color'] ?? null, (string) $config['excerpt_fade_color'] );
		$config['excerpt_mask_height'] = self::sanitize_int( $value['excerpt_mask_height'] ?? null, 8, 200, (int) $config['excerpt_mask_height'] );

		$config['author_font_size']    = self::sanitize_int( $value['author_font_size'] ?? null, 10, 48, (int) $config['author_font_size'] );
		$config['author_color']        = self::sanitize_color( $value['author_color'] ?? null, (string) $config['author_color'] );
		$config['author_bold']         = isset( $value['author_bold'] ) ? (bool) $value['author_bold'] : (bool) $config['author_bold'];
		$config['author_italic']       = isset( $value['author_italic'] ) ? (bool) $value['author_italic'] : (bool) $config['author_italic'];
		$config['auto_inject_enabled'] = isset( $value['auto_inject_enabled'] ) ? (bool) $value['auto_inject_enabled'] : (bool) $config['auto_inject_enabled'];

		if ( isset( $value['display_preset'] ) && is_string( $value['display_preset'] ) ) {
			$preset = sanitize_text_field( $value['display_preset'] );
			if ( in_array( $preset, array( '2x2', '3x2', '4x2', '4x1', '1x4' ), true ) ) {
				$config['display_preset'] = $preset;
			}
		}

		if ( isset( $value['enabled_post_types'] ) && is_array( $value['enabled_post_types'] ) ) {
			$config['enabled_post_types'] = self::sanitize_post_types( $value['enabled_post_types'] );
		}

		if ( isset( $value['insert_position'] ) && is_string( $value['insert_position'] ) ) {
			$insert_position = sanitize_key( $value['insert_position'] );
			if ( in_array( $insert_position, array( 'before_content', 'after_content' ), true ) ) {
				$config['insert_position'] = $insert_position;
			}
		}

		$config['data_limit'] = self::preset_data_limit( (string) $config['display_preset'] );

		return $config;
	}

	/**
	 * Default config.
	 *
	 * @return array<string,mixed>
	 */
	private static function default_config(): array {
		return array(
			'enabled'               => false,
			'title_enabled'         => true,
			'title_text'            => 'Related Posts',
			'title_level'           => 'h2',
			'template'              => 'sidebar_left',
			'footer_columns'        => 2,
			'block_order'           => array( 'featured_image', 'title', 'excerpt', 'author', 'date' ),
			'block_regions'         => array(
				'featured_image' => 'left_sidebar',
				'title'          => 'header',
				'excerpt'        => 'body',
				'author'         => 'footer_left',
				'date'           => 'footer_right',
			),
			'grid_container'        => array(
				'border_width_top'    => 1,
				'border_width_right'  => 1,
				'border_width_bottom' => 1,
				'border_width_left'   => 1,
				'border_radius'       => 6,
				'border_style'        => 'dashed',
				'border_color'        => '#dddddd',
				'bg_color'            => 'transparent',
				'padding_top'         => 9,
				'padding_right'       => 16,
				'padding_bottom'      => 16,
				'padding_left'        => 16,
				'gap'                 => 16,
			),
			'post_container'        => array(
				'border_width_top'    => 1,
				'border_width_right'  => 1,
				'border_width_bottom' => 1,
				'border_width_left'   => 1,
				'border_radius'       => 0,
				'border_style'        => 'solid',
				'border_color'        => '#e2e8f0',
				'bg_color'            => '#ffffff',
				'padding_top'         => 12,
				'padding_right'       => 12,
				'padding_bottom'      => 12,
				'padding_left'        => 12,
				'gap'                 => 10,
			),
			'header_container'      => array(
				'border_width_top'    => 0,
				'border_width_right'  => 0,
				'border_width_bottom' => 0,
				'border_width_left'   => 7,
				'border_radius'       => 0,
				'border_style'        => 'solid',
				'border_color'        => '#a3a3a3',
				'bg_color'            => 'transparent',
				'padding_top'         => 0,
				'padding_right'       => 0,
				'padding_bottom'      => 0,
				'padding_left'        => 15,
				'margin_top'          => 0,
				'margin_right'        => 0,
				'margin_bottom'       => 12,
				'margin_left'         => 0,
			),
			'header_title'          => array(
				'font_style' => array(
					'bold'      => false,
					'italic'    => false,
					'underline' => false,
				),
				'color'      => '#0f172a',
				'font_size'  => 18,
			),
			'featured_image_size'   => 'medium',
			'featured_image_radius' => 4,
			'title_font_size'       => 18,
			'title_color'           => '#334155',
			'title_bold'            => true,
			'title_italic'          => false,
			'excerpt_font_size'     => 14,
			'excerpt_color'         => '#334155',
			'excerpt_max_chars'     => 140,
			'excerpt_fade_mask'     => false,
			'excerpt_fade_color'    => '#ffffff',
			'excerpt_mask_height'   => 40,
			'author_font_size'      => 13,
			'author_color'          => '#475569',
			'author_bold'           => false,
			'author_italic'         => false,
			'auto_inject_enabled'   => true,
			'display_preset'        => '2x2',
			'data_limit'            => 4,
			'enabled_post_types'    => array( 'post' ),
			'insert_position'       => 'after_content',
		);
	}

	/**
	 * @param array<int,mixed> $value Candidate block order.
	 *
	 * @return array<int,string>
	 */
	private static function sanitize_block_order( array $value ): array {
		$allowed = array_fill_keys(
			array( 'featured_image', 'title', 'excerpt', 'author', 'date' ),
			true
		);
		$order   = array();
		foreach ( $value as $entry ) {
			if ( ! is_string( $entry ) ) {
				continue;
			}
			$key = sanitize_key( $entry );
			if ( isset( $allowed[ $key ] ) && ! in_array( $key, $order, true ) ) {
				$order[] = $key;
			}
		}

		return $order;
	}

	/**
	 * @param array<string,mixed> $value Candidate block regions.
	 *
	 * @return array<string,string>
	 */
	private static function sanitize_block_regions( array $value ): array {
		$allowed_regions = array_fill_keys(
			array( 'header', 'body', 'left_sidebar', 'footer_left', 'footer_center', 'footer_right' ),
			true
		);
		$allowed_blocks  = array( 'featured_image', 'title', 'excerpt', 'author', 'date' );
		$regions         = array();
		foreach ( $allowed_blocks as $block_id ) {
			$region = isset( $value[ $block_id ] ) && is_string( $value[ $block_id ] )
			? sanitize_key( $value[ $block_id ] )
			: '';
			if ( 'footer' === $region ) {
				$region = 'footer_left';
			}
			if ( isset( $allowed_regions[ $region ] ) ) {
				$regions[ $block_id ] = $region;
				continue;
			}
			$regions[ $block_id ] = 'body';
		}

		return $regions;
	}

	/**
	 * @param array<string,string> $regions Block regions.
	 * @param int                  $footer_columns Footer column count.
	 *
	 * @return array<string,string>
	 */
	private static function normalize_footer_regions_by_columns( array $regions, int $footer_columns ): array {
		foreach ( $regions as $block_id => $region ) {
			if ( ! is_string( $region ) ) {
				continue;
			}
			if ( 1 === $footer_columns && in_array( $region, array( 'footer_center', 'footer_right' ), true ) ) {
				$regions[ $block_id ] = 'footer_left';
				continue;
			}
			if ( 2 === $footer_columns && 'footer_center' === $region ) {
				$regions[ $block_id ] = 'footer_left';
			}
		}

		return $regions;
	}

	/**
	 * @param array<string,mixed> $value Raw container style.
	 * @param array<string,mixed> $fallback Defaults.
	 *
	 * @return array<string,mixed>
	 */
	private static function sanitize_container_style( array $value, array $fallback ): array {
		$border_style = isset( $value['border_style'] ) && is_string( $value['border_style'] )
		? sanitize_key( $value['border_style'] )
		: (string) ( $fallback['border_style'] ?? 'solid' );
		if ( ! in_array( $border_style, array( 'solid', 'dashed', 'dotted' ), true ) ) {
			$border_style = (string) ( $fallback['border_style'] ?? 'solid' );
		}

		return array(
			'border_width_top'    => self::sanitize_int( $value['border_width_top'] ?? null, 0, 50, (int) ( $fallback['border_width_top'] ?? 0 ) ),
			'border_width_right'  => self::sanitize_int( $value['border_width_right'] ?? null, 0, 50, (int) ( $fallback['border_width_right'] ?? 0 ) ),
			'border_width_bottom' => self::sanitize_int( $value['border_width_bottom'] ?? null, 0, 50, (int) ( $fallback['border_width_bottom'] ?? 0 ) ),
			'border_width_left'   => self::sanitize_int( $value['border_width_left'] ?? null, 0, 50, (int) ( $fallback['border_width_left'] ?? 0 ) ),
			'border_radius'       => self::sanitize_int( $value['border_radius'] ?? null, 0, 50, (int) ( $fallback['border_radius'] ?? 0 ) ),
			'border_style'        => $border_style,
			'border_color'        => self::sanitize_color( $value['border_color'] ?? null, (string) ( $fallback['border_color'] ?? '#e2e8f0' ) ),
			'bg_color'            => self::sanitize_color( $value['bg_color'] ?? null, (string) ( $fallback['bg_color'] ?? '#ffffff' ) ),
			'padding_top'         => self::sanitize_int( $value['padding_top'] ?? null, 0, 50, (int) ( $fallback['padding_top'] ?? 0 ) ),
			'padding_right'       => self::sanitize_int( $value['padding_right'] ?? null, 0, 50, (int) ( $fallback['padding_right'] ?? 0 ) ),
			'padding_bottom'      => self::sanitize_int( $value['padding_bottom'] ?? null, 0, 50, (int) ( $fallback['padding_bottom'] ?? 0 ) ),
			'padding_left'        => self::sanitize_int( $value['padding_left'] ?? null, 0, 50, (int) ( $fallback['padding_left'] ?? 0 ) ),
			'gap'                 => self::sanitize_int( $value['gap'] ?? null, 0, 64, (int) ( $fallback['gap'] ?? 0 ) ),
		);
	}

	/**
	 * @param array<string,mixed> $value Raw style.
	 * @param array<string,mixed> $fallback Fallback style.
	 *
	 * @return array<string,mixed>
	 */
	private static function sanitize_header_container_style( array $value, array $fallback ): array {
		return array(
			'border_width_top'    => self::sanitize_int( $value['border_width_top'] ?? null, 0, 12, (int) ( $fallback['border_width_top'] ?? 0 ) ),
			'border_width_right'  => self::sanitize_int( $value['border_width_right'] ?? null, 0, 12, (int) ( $fallback['border_width_right'] ?? 0 ) ),
			'border_width_bottom' => self::sanitize_int( $value['border_width_bottom'] ?? null, 0, 12, (int) ( $fallback['border_width_bottom'] ?? 0 ) ),
			'border_width_left'   => self::sanitize_int( $value['border_width_left'] ?? null, 0, 12, (int) ( $fallback['border_width_left'] ?? 0 ) ),
			'border_radius'       => self::sanitize_int( $value['border_radius'] ?? null, 0, 50, (int) ( $fallback['border_radius'] ?? 0 ) ),
			'border_style'        => self::sanitize_border_style( $value['border_style'] ?? null, (string) ( $fallback['border_style'] ?? 'solid' ) ),
			'border_color'        => self::sanitize_color( $value['border_color'] ?? null, (string) ( $fallback['border_color'] ?? '#e2e8f0' ) ),
			'bg_color'            => self::sanitize_color( $value['bg_color'] ?? null, (string) ( $fallback['bg_color'] ?? 'transparent' ) ),
			'padding_top'         => self::sanitize_int( $value['padding_top'] ?? null, 0, 50, (int) ( $fallback['padding_top'] ?? 0 ) ),
			'padding_right'       => self::sanitize_int( $value['padding_right'] ?? null, 0, 50, (int) ( $fallback['padding_right'] ?? 0 ) ),
			'padding_bottom'      => self::sanitize_int( $value['padding_bottom'] ?? null, 0, 50, (int) ( $fallback['padding_bottom'] ?? 0 ) ),
			'padding_left'        => self::sanitize_int( $value['padding_left'] ?? null, 0, 50, (int) ( $fallback['padding_left'] ?? 0 ) ),
			'margin_top'          => self::sanitize_int( $value['margin_top'] ?? null, 0, 50, (int) ( $fallback['margin_top'] ?? 0 ) ),
			'margin_right'        => self::sanitize_int( $value['margin_right'] ?? null, 0, 50, (int) ( $fallback['margin_right'] ?? 0 ) ),
			'margin_bottom'       => self::sanitize_int( $value['margin_bottom'] ?? null, 0, 50, (int) ( $fallback['margin_bottom'] ?? 12 ) ),
			'margin_left'         => self::sanitize_int( $value['margin_left'] ?? null, 0, 50, (int) ( $fallback['margin_left'] ?? 0 ) ),
		);
	}

	/**
	 * @param array<string,mixed> $value Raw style.
	 * @param array<string,mixed> $fallback Fallback style.
	 *
	 * @return array<string,mixed>
	 */
	private static function sanitize_header_title_style( array $value, array $fallback ): array {
		$font_style_value    = isset( $value['font_style'] ) && is_array( $value['font_style'] ) ? $value['font_style'] : array();
		$font_style_fallback = isset( $fallback['font_style'] ) && is_array( $fallback['font_style'] ) ? $fallback['font_style'] : array();
		return array(
			'font_style' => array(
				'bold'      => isset( $font_style_value['bold'] ) ? (bool) $font_style_value['bold'] : (bool) ( $font_style_fallback['bold'] ?? false ),
				'italic'    => isset( $font_style_value['italic'] ) ? (bool) $font_style_value['italic'] : (bool) ( $font_style_fallback['italic'] ?? false ),
				'underline' => isset( $font_style_value['underline'] ) ? (bool) $font_style_value['underline'] : (bool) ( $font_style_fallback['underline'] ?? false ),
			),
			'color'      => self::sanitize_color( $value['color'] ?? null, (string) ( $fallback['color'] ?? '#0f172a' ) ),
			'font_size'  => self::sanitize_int( $value['font_size'] ?? null, 10, 64, (int) ( $fallback['font_size'] ?? 18 ) ),
		);
	}

	/**
	 * @param mixed  $value Raw style.
	 * @param string $fallback Fallback style.
	 *
	 * @return string
	 */
	private static function sanitize_border_style( $value, string $fallback ): string {
		$border_style = is_string( $value ) ? sanitize_key( $value ) : sanitize_key( $fallback );
		if ( ! in_array( $border_style, array( 'solid', 'dashed', 'dotted' ), true ) ) {
			return in_array( $fallback, array( 'solid', 'dashed', 'dotted' ), true ) ? $fallback : 'solid';
		}

		return $border_style;
	}

	/**
	 * @param mixed  $value Raw color.
	 * @param string $fallback Fallback.
	 *
	 * @return string
	 */
	private static function sanitize_color( $value, string $fallback ): string {
		if ( ! is_string( $value ) ) {
			return $fallback;
		}
		$color = sanitize_hex_color( trim( $value ) );
		return is_string( $color ) && '' !== $color ? $color : $fallback;
	}

	/**
	 * @param mixed  $value Raw int.
	 * @param int    $min Min.
	 * @param int    $max Max.
	 * @param int    $fallback Fallback.
	 *
	 * @return int
	 */
	private static function sanitize_int( $value, int $min, int $max, int $fallback ): int {
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
	 * Resolve data limit by display preset.
	 *
	 * @param string $preset Display preset.
	 *
	 * @return int
	 */
	private static function preset_data_limit( string $preset ): int {
		if ( '4x2' === $preset ) {
			return 8;
		}
		if ( '2x2' === $preset || '4x1' === $preset || '1x4' === $preset ) {
			return 4;
		}
		return 6;
	}

	/**
	 * @param array<int,mixed> $value Input post types.
	 *
	 * @return array<int,string>
	 */
	private static function sanitize_post_types( array $value ): array {
		$allowed = get_post_types( array( 'public' => true ), 'names' );
		$map     = array_fill_keys( $allowed, true );
		$types   = array();
		foreach ( $value as $entry ) {
			if ( ! is_string( $entry ) ) {
				continue;
			}
			$key = sanitize_key( $entry );
			if ( isset( $map[ $key ] ) && ! in_array( $key, $types, true ) ) {
				$types[] = $key;
			}
		}

		return ! empty( $types ) ? $types : array( 'post' );
	}
}
