<?php
/**
 * REST controller for Markdown for Agents.
 *
 * @package Airygen\Modules\MarkdownForAgents\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\MarkdownForAgents\Admin;

use Airygen\Modules\MarkdownForAgents\Application\MarkdownExporter;
use Airygen\Modules\MarkdownForAgents\Infrastructure\MarkdownPostRepository;
use Airygen\Support\Errors\ErrorCodes;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes preview/export/rebuild endpoints.
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
	 * Handle markdown export endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_export( WP_REST_Request $request ) {
		$post_id = absint( (string) $request->get_param( 'post_id' ) );
		if ( $post_id <= 0 ) {
			return new WP_Error( ErrorCodes::AIRYGEN_INVALID_POST, __( 'Invalid post ID.', 'airygen-seo' ), array( 'status' => 400 ) );
		}

		$settings = Settings::get();
		$payload  = MarkdownExporter::export( $post_id, $settings );
		if ( ! is_array( $payload ) ) {
			return new WP_Error( ErrorCodes::AIRYGEN_POST_NOT_FOUND, __( 'Unable to export markdown for this post.', 'airygen-seo' ), array( 'status' => 404 ) );
		}

		( new MarkdownPostRepository() )->upsert( $payload );

		$download = rest_sanitize_boolean( $request->get_param( 'download' ) );
		if ( $download ) {
			$filename = $post_id . '-' . sanitize_title( (string) $payload['title'] ) . '.md';
			return new WP_REST_Response(
				(string) $payload['markdown_content'],
				200,
				array(
					'Content-Type'        => 'text/markdown; charset=UTF-8',
					'Content-Disposition' => 'attachment; filename="' . $filename . '"',
					'Vary'                => 'Accept',
				)
			);
		}

		return rest_ensure_response(
			array(
				'postId'      => $post_id,
				'title'       => (string) $payload['title'],
				'frontmatter' => (string) $payload['frontmatter_yaml'],
				'markdown'    => (string) $payload['markdown_content'],
			)
		);
	}

	/**
	 * Handle preview endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_preview( WP_REST_Request $request ) {
		$post_id = absint( (string) $request->get_param( 'post_id' ) );
		if ( $post_id <= 0 ) {
			return new WP_Error( ErrorCodes::AIRYGEN_INVALID_POST, __( 'Invalid post ID.', 'airygen-seo' ), array( 'status' => 400 ) );
		}

		$settings = Settings::get();
		$payload  = MarkdownExporter::export( $post_id, $settings );
		if ( ! is_array( $payload ) ) {
			return new WP_Error( ErrorCodes::AIRYGEN_POST_NOT_FOUND, __( 'Unable to preview markdown for this post.', 'airygen-seo' ), array( 'status' => 404 ) );
		}

		$markdown = (string) $payload['markdown_content'];

		return rest_ensure_response(
			array(
				'postId'      => $post_id,
				'frontmatter' => (string) $payload['frontmatter_yaml'],
				'markdown'    => $markdown,
				'meta'        => array(
					'wordCount'    => str_word_count( wp_strip_all_tags( $markdown ) ),
					'headingCount' => preg_match_all( '/^#{1,6}\s+/m', $markdown ),
					'linkCount'    => preg_match_all( '/\[[^\]]+\]\([^)]+\)/', $markdown ),
				),
			)
		);
	}

	/**
	 * Handle rebuild endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_rebuild( WP_REST_Request $request ): WP_REST_Response {
		$settings      = Settings::get();
		$allowed_types = array( 'post', 'page' );
		if ( isset( $settings['post_types'] ) && is_array( $settings['post_types'] ) ) {
			$allowed_types = array_values( array_filter( array_map( 'strval', $settings['post_types'] ) ) );
		}

		$ids = $request->get_param( 'post_ids' );
		$ids = is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : array();

		if ( empty( $ids ) ) {
			$query = new \WP_Query(
				array(
					'post_type'      => $allowed_types,
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			$ids   = is_array( $query->posts ) ? array_map( 'intval', $query->posts ) : array();
		}

		$synced = 0;
		$failed = 0;
		$repo   = new MarkdownPostRepository();

		foreach ( $ids as $post_id ) {
			$payload = MarkdownExporter::export( (int) $post_id, $settings );
			if ( ! is_array( $payload ) ) {
				++$failed;
				continue;
			}
			$repo->upsert( $payload );
			++$synced;
		}

		return rest_ensure_response(
			array(
				'total'  => count( $ids ),
				'synced' => $synced,
				'failed' => $failed,
			)
		);
	}

	/**
	 * Handle snapshots records list endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_records( WP_REST_Request $request ): WP_REST_Response {
		$page      = absint( (string) $request->get_param( 'page' ) );
		$per_page  = absint( (string) $request->get_param( 'per_page' ) );
		$post_type = (string) $request->get_param( 'post_type' );
		$page      = max( 1, $page );
		$per_page  = max( 1, min( 100, $per_page > 0 ? $per_page : 20 ) );
		$post_type = sanitize_key( $post_type );

		$settings      = Settings::get();
		$allowed_types = isset( $settings['post_types'] ) && is_array( $settings['post_types'] )
		? array_values( array_filter( array_map( 'strval', $settings['post_types'] ) ) )
		: array( 'post', 'page' );
		if ( '' !== $post_type && ! in_array( $post_type, $allowed_types, true ) ) {
			$post_type = '';
		}

		$repo    = new MarkdownPostRepository();
		$total   = $repo->count_snapshots( '' !== $post_type ? $post_type : null );
		$records = $repo->get_snapshots( $page, $per_page, '' !== $post_type ? $post_type : null );

		return rest_ensure_response(
			array(
				'records'    => array_map(
					static function ( array $row ): array {
						return array(
							'postId'      => isset( $row['post_id'] ) ? (int) $row['post_id'] : 0,
							'postType'    => isset( $row['post_type'] ) ? (string) $row['post_type'] : '',
							'title'       => isset( $row['title'] ) ? (string) $row['title'] : '',
							'url'         => isset( $row['canonical_url'] ) ? (string) $row['canonical_url'] : '',
							'lastSynced'  => isset( $row['last_synced_gmt'] ) ? (string) $row['last_synced_gmt'] : '',
							'contentHash' => isset( $row['content_hash'] ) ? (string) $row['content_hash'] : '',
						);
					},
					$records
				),
				'pagination' => array(
					'page'       => $page,
					'perPage'    => $per_page,
					'total'      => $total,
					'totalPages' => max( 1, (int) ceil( $total / $per_page ) ),
				),
			)
		);
	}
}
