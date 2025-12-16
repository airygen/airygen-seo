<?php
/**
 * Responsible for registering post meta used by the OnPage SEO feature.
 *
 * @package Airygen\Modules\OnPageSeo\Admin\Meta
 */

declare(strict_types=1);

namespace Airygen\Support\Meta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;

/**
 * Registers post meta with the WordPress REST API.
 */
final class RegisterPostMeta {

	/**
	 * Register all meta keys exposed via the REST API.
	 *
	 * @return void
	 */
	public static function register(): void {
		$post_types = self::target_post_types();

		foreach ( $post_types as $post_type ) {
			self::register_post_data_meta( $post_type );
			self::register_string_meta( $post_type, Constants::META_OUTPUT_MODES );
		}
	}

	/**
	 * Register the consolidated post data meta key.
	 *
	 * @param string $post_type Post type name.
	 *
	 * @return void
	 */
	private static function register_post_data_meta( string $post_type ): void {
		register_post_meta(
			$post_type,
			Constants::META_POST_DATA,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'sanitize_callback' => array( PostData::class, 'sanitize_meta_value' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'string',
						'context' => array( 'view', 'edit' ),
					),
				),
				'auth_callback'     => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * Register a single string meta key.
	 *
	 * @param string $post_type Post type name.
	 * @param string $meta_key  Meta key identifier.
	 *
	 * @return void
	 */
	private static function register_string_meta( string $post_type, string $meta_key ): void {
		register_post_meta(
			$post_type,
			$meta_key,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'string',
						'context' => array( 'view', 'edit' ),
					),
				),
				'auth_callback'     => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * Determine the post types that should expose the metadata.
	 *
	 * @return array<int, string>
	 */
	private static function target_post_types(): array {
		$post_types = get_post_types(
			array(
				'show_ui' => true,
			),
			'names'
		);

		return array_values(
			array_diff(
				$post_types,
				array( 'attachment', 'revision', 'nav_menu_item', 'wp_block' )
			)
		);
	}
}
