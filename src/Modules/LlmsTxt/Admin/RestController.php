<?php
/**
 * REST controller for llms.txt module.
 *
 * @package Airygen\Modules\LlmsTxt\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\LlmsTxt\Admin;

use Airygen\Modules\LlmsTxt\Infrastructure\RenderCache;
use Airygen\Modules\LlmsTxt\Public\Hooks as PublicHooks;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes llms preview endpoint.
 */
final class RestController {

	/**
	 * Permission callback.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handle llms preview endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_preview( WP_REST_Request $request ): WP_REST_Response {
		$payload   = $request->get_json_params();
		$raw_value = is_array( $payload ) && isset( $payload['settings'] ) && is_array( $payload['settings'] )
		? $payload['settings']
		: Settings::get();
		$target    = is_array( $payload ) && isset( $payload['target'] ) ? sanitize_text_field( (string) $payload['target'] ) : 'base';
		$settings  = Settings::sanitize_preview( $raw_value );
		$content   = 'base' === $target
		? PublicHooks::build_llms_content( $settings )
		: PublicHooks::build_extension_content( $settings, $target );

		return rest_ensure_response(
			array(
				'type'    => 'index',
				'content' => $content,
			)
		);
	}

	/**
	 * Handle post lookup endpoint used by selection modal.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_posts( WP_REST_Request $request ): WP_REST_Response {
		$query_raw = $request->get_param( 'q' );
		$ids_raw   = $request->get_param( 'ids' );

		$query = is_string( $query_raw ) ? sanitize_text_field( $query_raw ) : '';
		$ids   = array();
		if ( is_string( $ids_raw ) && '' !== $ids_raw ) {
			$parts = array_filter( array_map( 'trim', explode( ',', $ids_raw ) ) );
			$ids   = array_values(
				array_unique(
					array_filter(
						array_map( 'absint', $parts ),
						static fn( int $post_id ): bool => $post_id > 0
					)
				)
			);
		}

		$settings   = Settings::get();
		$post_types = isset( $settings['post_types'] ) && is_array( $settings['post_types'] )
		? array_values( array_filter( array_map( 'strval', $settings['post_types'] ) ) )
		: array( 'post', 'page' );
		if ( empty( $post_types ) ) {
			$post_types = array( 'post', 'page' );
		}

		$args = array(
			'post_type'           => $post_types,
			'post_status'         => 'publish',
			'orderby'             => 'date',
			'order'               => 'DESC',
			'posts_per_page'      => 20,
			'no_found_rows'       => true,
			'suppress_filters'    => false,
			'ignore_sticky_posts' => true,
		);

		if ( ! empty( $ids ) ) {
			$args['post__in'] = $ids;
			$args['orderby']  = 'post__in';
			$args['nopaging'] = true;
		}
		if ( '' !== $query ) {
			$args['s'] = $query;
		}

		$posts = get_posts( $args );
		$items = array_map(
			static function ( \WP_Post $post ): array {
				return array(
					'id'    => (int) $post->ID,
					'title' => (string) get_the_title( $post->ID ),
				);
			},
			$posts
		);

		return rest_ensure_response(
			array(
				'items' => $items,
			)
		);
	}

	/**
	 * Handle manual llms cache invalidation.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_clear_cache( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		RenderCache::invalidate_all();

		return rest_ensure_response(
			array(
				'cleared' => true,
			)
		);
	}
}
