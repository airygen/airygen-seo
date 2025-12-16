<?php
/**
 * Stores configuration for the Taxonomy SEO module.
 *
 * @package Airygen\Modules\TaxonomySeo\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\TaxonomySeo\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Persists admin-configurable Taxonomy SEO settings.
 */
final class Settings {

	private const OPTION = Constants::OPTION_TAXONOMY_SEO;

	/**
	 * Ensure the option exists with defaults.
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
	 * @param array<string, mixed> $value Raw settings array.
	 *
	 * @return void
	 */
	public static function update( array $value ): void {
		update_option( self::OPTION, self::sanitize( $value ), 'no' );
	}

	/**
	 * Sanitize incoming values.
	 *
	 * @param mixed $value Raw option value.
	 *
	 * @return array<string, mixed>
	 */
	private static function sanitize( $value ): array {
		$config = self::default_config();

		if ( ! is_array( $value ) ) {
			return $config;
		}

		if ( array_key_exists( 'enabled', $value ) ) {
			$config['enabled'] = (bool) $value['enabled'];
		}

		if ( isset( $value['enabled_taxonomies'] ) && is_array( $value['enabled_taxonomies'] ) ) {
			$allowed = self::available_taxonomies();
			$enabled = array();
			foreach ( $value['enabled_taxonomies'] as $taxonomy ) {
				if ( ! is_string( $taxonomy ) ) {
					continue;
				}
				$slug = sanitize_key( $taxonomy );
				if ( in_array( $slug, $allowed, true ) && ! in_array( $slug, $enabled, true ) ) {
					$enabled[] = $slug;
				}
			}
			$config['enabled_taxonomies'] = $enabled;
		}

		if ( isset( $value['templates'] ) && is_array( $value['templates'] ) ) {
			if ( isset( $value['templates']['global'] ) && is_array( $value['templates']['global'] ) ) {
				$config['templates']['global'] = self::sanitize_template_group(
					$value['templates']['global'],
					$config['templates']['global']
				);
			}

			if ( isset( $value['templates']['separator'] ) && is_string( $value['templates']['separator'] ) ) {
				$separator = trim( sanitize_text_field( $value['templates']['separator'] ) );
				if ( '' !== $separator ) {
					$config['templates']['separator'] = mb_substr( $separator, 0, 10 );
				}
			}

			if ( isset( $value['templates']['custom_tokens'] ) && is_array( $value['templates']['custom_tokens'] ) ) {
				$config['templates']['custom_tokens'] = self::sanitize_custom_tokens(
					$value['templates']['custom_tokens'],
					$config['templates']['custom_tokens']
				);
			}
		}

		return $config;
	}

	/**
	 * Get default configuration.
	 *
	 * @return array<string, mixed>
	 */
	private static function default_config(): array {
		return array(
			'enabled'            => true,
			'enabled_taxonomies' => array( 'category', 'post_tag' ),
			'templates'          => array(
				'global'        => array(
					'title'       => '%term_name% %separator% %site_name%',
					'description' => '%term_description%',
				),
				'separator'     => '–',
				'custom_tokens' => array(
					'custom_1' => '',
					'custom_2' => '',
					'custom_3' => '',
				),
			),
		);
	}

	/**
	 * Sanitize template group.
	 *
	 * @param array<string, mixed>  $group    Raw group values.
	 * @param array<string, string> $fallback Fallback values.
	 *
	 * @return array<string, string>
	 */
	private static function sanitize_template_group( array $group, array $fallback ): array {
		$title       = isset( $group['title'] ) && is_string( $group['title'] ) ? $group['title'] : $fallback['title'];
		$description = isset( $group['description'] ) && is_string( $group['description'] ) ? $group['description'] : $fallback['description'];

		return array(
			'title'       => mb_substr( trim( wp_strip_all_tags( $title ) ), 0, 180 ),
			'description' => mb_substr( trim( wp_strip_all_tags( $description ) ), 0, 220 ),
		);
	}

	/**
	 * Sanitize custom tokens.
	 *
	 * @param array<string, mixed>  $tokens   Raw token values.
	 * @param array<string, string> $fallback Fallback tokens.
	 *
	 * @return array<string, string>
	 */
	private static function sanitize_custom_tokens( array $tokens, array $fallback ): array {
		$custom_1 = isset( $tokens['custom_1'] ) && is_string( $tokens['custom_1'] ) ? $tokens['custom_1'] : $fallback['custom_1'];
		$custom_2 = isset( $tokens['custom_2'] ) && is_string( $tokens['custom_2'] ) ? $tokens['custom_2'] : $fallback['custom_2'];
		$custom_3 = isset( $tokens['custom_3'] ) && is_string( $tokens['custom_3'] ) ? $tokens['custom_3'] : $fallback['custom_3'];

		return array(
			'custom_1' => mb_substr( sanitize_text_field( $custom_1 ), 0, 160 ),
			'custom_2' => mb_substr( sanitize_text_field( $custom_2 ), 0, 160 ),
			'custom_3' => mb_substr( sanitize_text_field( $custom_3 ), 0, 160 ),
		);
	}

	/**
	 * Resolve public taxonomies supported by this module.
	 *
	 * @return array<int, string>
	 */
	public static function available_taxonomies(): array {
		$taxonomies = get_taxonomies(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'names'
		);

		$excluded = array(
			'nav_menu',
			'link_category',
			'post_format',
			'product_cat',
			'product_tag',
		);

		$normalized = array();
		foreach ( $taxonomies as $taxonomy ) {
			if ( ! is_string( $taxonomy ) ) {
				continue;
			}

			if ( str_starts_with( $taxonomy, 'pa_' ) ) {
				continue;
			}

			if ( in_array( $taxonomy, $excluded, true ) ) {
				continue;
			}

			$normalized[] = $taxonomy;
		}

		return array_values( $normalized );
	}
}
