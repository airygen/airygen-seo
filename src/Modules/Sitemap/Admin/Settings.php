<?php
/**
 * Registers Sitemap settings.
 *
 * @package Airygen\Modules\Sitemap\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\Sitemap\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Constants;
use Airygen\Modules\Sitemap\Public\Routes as SitemapRoutes;

/**
 * Handles option storage for sitemap configuration.
 */
final class Settings {

	private const OPTION_NAME = Constants::OPTION_SITEMAP;

	/**
	 * Ensure the sitemap option exists in storage.
	 *
	 * @return void
	 */
	public static function ensure_exists(): void {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::default_config(), '', 'no' );
		}
	}

	/**
	 * Retrieve sanitized settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		return self::sanitize( get_option( self::OPTION_NAME, array() ) );
	}

	/**
	 * Update settings value.
	 *
	 * @param array<string, mixed> $value Raw value.
	 *
	 * @return void
	 */
	public static function update( array $value ): void {
		update_option( self::OPTION_NAME, self::sanitize( $value ), 'no' );

		if ( ModuleSettings::is_enabled( 'sitemap' ) ) {
			SitemapRoutes::add_rewrite_rules();
			flush_rewrite_rules( false );
			return;
		}

		flush_rewrite_rules( false );
	}

	/**
	 * Sanitize option values.
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

		if ( isset( $value['enabled_post_types'] ) && is_array( $value['enabled_post_types'] ) ) {
			$sanitized = array();

			foreach ( $value['enabled_post_types'] as $slug ) {
				$slug = sanitize_key( (string) $slug );
				if ( '' === $slug ) {
					continue;
				}

				$sanitized[] = $slug;
			}

			$config['enabled_post_types'] = array_values( array_unique( $sanitized ) );
		}

		if ( isset( $value['enabled_taxonomies'] ) && is_array( $value['enabled_taxonomies'] ) ) {
			$sanitized = array();

			foreach ( $value['enabled_taxonomies'] as $slug ) {
				$slug = sanitize_key( (string) $slug );
				if ( '' === $slug ) {
					continue;
				}

				$sanitized[] = $slug;
			}

			$config['enabled_taxonomies'] = array_values( array_unique( $sanitized ) );
		}

		if ( isset( $value['items_per_page'] ) ) {
			$config['items_per_page'] = self::sanitize_items_per_page( $value['items_per_page'] );
		}

		if ( isset( $value['exclude_empty_taxonomies'] ) ) {
			$config['exclude_empty_taxonomies'] = (bool) $value['exclude_empty_taxonomies'];
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
			'enabled_post_types'       => self::default_post_types(),
			'enabled_taxonomies'       => self::default_taxonomies(),
			'exclude_empty_taxonomies' => true,
			'items_per_page'           => 500,
		);
	}

	/**
	 * Sanitize items-per-page value.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	private static function sanitize_items_per_page( $value ): int {
		$numeric = (int) $value;

		if ( $numeric < 500 ) {
			$numeric = 500;
		}

		if ( $numeric > 5000 ) {
			$numeric = 5000;
		}

		$numeric = (int) round( $numeric / 500 ) * 500;

		return max( 500, min( 5000, $numeric ) );
	}

	/**
	 * Default enabled post types.
	 *
	 * @return array<int, string>
	 */
	private static function default_post_types(): array {
		$types = get_post_types(
			array(
				'public' => true,
			),
			'names'
		);

		return array_values( $types );
	}

	/**
	 * Default enabled taxonomies.
	 *
	 * @return array<int, string>
	 */
	private static function default_taxonomies(): array {
		$taxonomies = get_taxonomies(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'names'
		);

		return array_values( $taxonomies );
	}
}
