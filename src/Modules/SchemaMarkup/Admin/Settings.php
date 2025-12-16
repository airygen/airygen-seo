<?php
/**
 * Registers Schema Markup settings.
 *
 * @package Airygen\Modules\SchemaMarkup\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\SchemaMarkup\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Handles option registration for schema defaults.
 */
final class Settings {

	private const OPTION_NAME = Constants::OPTION_SCHEMA;

	/**
	 * Ensure option exists.
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
	 * Update option with sanitized data.
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
	 * @param mixed $value Raw value.
	 *
	 * @return array<string, mixed>
	 */
	public static function sanitize( $value ): array {
		if ( ! is_array( $value ) ) {
			return self::default_config();
		}

		$config = self::default_config();

		if ( isset( $value['organization_name'] ) ) {
			$config['organization_name'] = sanitize_text_field( (string) $value['organization_name'] );
		}

		if ( isset( $value['organization_type'] ) ) {
			$config['organization_type'] = sanitize_text_field( (string) $value['organization_type'] );
		}

		if ( isset( $value['organization_logo_id'] ) ) {
			$config['organization_logo_id'] = absint( $value['organization_logo_id'] );
		}

		if ( isset( $value['organization_logo_url'] ) ) {
			$config['organization_logo_url'] = esc_url_raw( (string) $value['organization_logo_url'] );
		}

		if ( isset( $value['article_type'] ) ) {
			$config['article_type'] = sanitize_text_field( (string) $value['article_type'] );
		}

		if ( isset( $value['article_show_author'] ) ) {
			$config['article_show_author'] = ! empty( $value['article_show_author'] );
		}

		if ( isset( $value['article_only_post'] ) ) {
			$config['article_only_post'] = ! empty( $value['article_only_post'] );
		}

		if ( isset( $value['visibility'] ) && is_array( $value['visibility'] ) ) {
			$config['visibility'] = array(
				'organization' => ! empty( $value['visibility']['organization'] ),
				'website'      => ! empty( $value['visibility']['website'] ),
				'breadcrumb'   => ! empty( $value['visibility']['breadcrumb'] ),
				'article'      => ! empty( $value['visibility']['article'] ),
			);
		}

		if ( isset( $value['post_type_defaults'] ) && is_array( $value['post_type_defaults'] ) ) {
			$post_type_defaults = array();
			foreach ( $value['post_type_defaults'] as $post_type => $schema_type ) {
				$slug = sanitize_key( (string) $post_type );
				if ( '' === $slug ) {
					continue;
				}

				$schema_type = sanitize_text_field( (string) $schema_type );

				if ( '' === $schema_type ) {
					continue;
				}

				$post_type_defaults[ $slug ] = $schema_type;
			}

			$config['post_type_defaults'] = $post_type_defaults;
		}

		return $config;
	}

	/**
	 * Default option configuration.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_config(): array {
		return array(
			'organization_name'     => '',
			'organization_type'     => 'Organization',
			'organization_logo_id'  => 0,
			'organization_logo_url' => '',
			'article_type'          => 'Article',
			'article_show_author'   => true,
			'article_only_post'     => true,
			'post_type_defaults'    => array(),
			'visibility'            => array(
				'organization' => false,
				'website'      => false,
				'breadcrumb'   => false,
				'article'      => false,
			),
		);
	}
}
