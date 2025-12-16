<?php
/**
 * REST controller for related posts / link suggestions.
 *
 * @package Airygen\Modules\LinkSuggestions\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkSuggestions\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;
use Airygen\Modules\LinkSuggestions\Application\RecommendationService;
use Airygen\Modules\LinkSuggestions\Domain\SimilarityScorer;
use Airygen\Modules\LinkSuggestions\Persistence\LinkTermsRepository;
use Airygen\Support\Errors\ErrorCodes;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

use function current_user_can;
use function get_permalink;
use function get_post;
use function get_post_status;
use function get_post_stati;
use function get_post_type_object;
use function get_post_type;
use function get_post_types;
use function get_posts;
use function in_array;
use function is_array;
use function rest_ensure_response;
use function absint;
use function rest_url;
use function wp_create_nonce;

/**
 * Handles settings and reindex triggers.
 */
class RestController {

	/**
	 * Capability check.
	 *
	 * @return bool|WP_Error
	 */
	public static function can_manage() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new WP_Error( ErrorCodes::REST_FORBIDDEN, __( 'You are not allowed to manage related settings.', 'airygen-seo' ), array( 'status' => 403 ) );
	}

	/**
	 * Capability check for editing a single post.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return bool|WP_Error
	 */
	public static function can_edit( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'post' ) );
		if ( $post_id > 0 && current_user_can( 'edit_post', $post_id ) ) {
			return true;
		}

		return new WP_Error( ErrorCodes::REST_FORBIDDEN, __( 'You are not allowed to view related suggestions for this post.', 'airygen-seo' ), array( 'status' => 403 ) );
	}

	/**
	 * GET /link-suggestions/settings
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_get_settings(): WP_REST_Response {
		$settings = Settings::get();
		return rest_ensure_response(
			array_merge(
				$settings,
				array(
					'stats' => self::build_stats( $settings['allowed_post_types'] ),
				)
			)
		);
	}

	/**
	 * POST /link-suggestions/settings
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_update_settings( WP_REST_Request $request ): WP_REST_Response {
		$payload = $request->get_json_params();
		$payload = is_array( $payload ) ? $payload : array();

		$settings = Settings::update( $payload );

		return rest_ensure_response(
			array_merge(
				$settings,
				array(
					'stats' => self::build_stats( $settings['allowed_post_types'] ),
				)
			)
		);
	}

	/**
	 * POST /link-suggestions/reindex
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_reindex(): WP_REST_Response {
		do_action( Constants::HOOK_LINK_SUGGESTIONS_REINDEX_ALL ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook constant is already plugin-prefixed.

		return rest_ensure_response( array( 'queued' => true ) );
	}

	/**
	 * Editor-facing API configuration.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_editor_config(): array {
		$settings = Settings::get();

		if ( empty( $settings['enabled'] ) ) {
			return array();
		}

		return array(
			'enabled' => (bool) $settings['enabled'],
			'max'     => (int) ( $settings['max_suggestions'] ?? Settings::MAX_SUGGESTIONS ),
			'api'     => array(
				'root'   => rest_url( 'airygen/v1/link-suggestions/suggestions' ),
				'nonce'  => wp_create_nonce( 'wp_rest' ),
				'method' => 'GET',
			),
		);
	}

	/**
	 * Build index stats for allowed post types.
	 *
	 * @param array<int,string> $post_types Allowed post types.
	 *
	 * @return array<int,array<string,int|string>>
	 */
	private static function build_stats( array $post_types ): array {
		if ( empty( $post_types ) ) {
			$post_types = get_post_types( array( 'public' => true ) );
		}

		$statuses   = array_diff(
			get_post_stati( array( 'internal' => false ), 'names' ),
			array( 'trash', 'auto-draft' )
		);
		$repository = new LinkTermsRepository();
		$stats      = array();

		foreach ( $post_types as $post_type ) {
			$label_object = get_post_type_object( $post_type );
			$label        = $label_object ? $label_object->labels->singular_name : $post_type;

			$total   = self::count_posts_by_type( $post_type, $statuses );
			$indexed = $repository->count_indexed_by_type( $post_type, $statuses );
			$stats[] = array(
				'post_type'   => $post_type,
				'label'       => $label,
				'indexed'     => $indexed,
				'not_indexed' => max( 0, $total - $indexed ),
				'total'       => $total,
			);
		}

		return $stats;
	}

	/**
	 * Count eligible posts by type and status.
	 *
	 * @param string $post_type Post type.
	 * @param array  $statuses Allowed statuses.
	 *
	 * @return int
	 */
	private static function count_posts_by_type( string $post_type, array $statuses ): int {
		$query = new \WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => $statuses,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * GET /link-suggestions/suggestions
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_get_suggestions( WP_REST_Request $request ): WP_REST_Response {
		$post_id = absint( $request->get_param( 'post' ) );

		if ( $post_id <= 0 ) {
			return rest_ensure_response( new WP_Error( ErrorCodes::BAD_REQUEST, __( 'Missing post parameter.', 'airygen-seo' ), array( 'status' => 400 ) ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return rest_ensure_response( new WP_Error( ErrorCodes::NOT_FOUND, __( 'Post not found.', 'airygen-seo' ), array( 'status' => 404 ) ) );
		}

		$settings = Settings::get();
		if ( ! $settings['enabled'] ) {
			return rest_ensure_response(
				array(
					'suggestions' => array(),
					'meta'        => array(
						'reason' => 'disabled',
					),
				)
			);
		}

		$allowed_types = ! empty( $settings['allowed_post_types'] ) ? (array) $settings['allowed_post_types'] : get_post_types( array( 'public' => true ) );
		if ( ! in_array( get_post_type( $post ), $allowed_types, true ) ) {
			return rest_ensure_response(
				array(
					'suggestions' => array(),
					'meta'        => array(
						'reason' => 'post_type_not_allowed',
					),
				)
			);
		}

		if ( 'trash' === get_post_status( $post ) ) {
			return rest_ensure_response(
				array(
					'suggestions' => array(),
					'meta'        => array(
						'reason' => 'post_status_trash',
					),
				)
			);
		}

		$repository    = new LinkTermsRepository();
		$request_terms = $repository->get_terms_for_content( $post_id, get_post_type( $post ) );

		if ( empty( $request_terms ) ) {
			return rest_ensure_response(
				array(
					'suggestions' => array(),
					'meta'        => array(
						'reason' => 'missing_terms',
					),
				)
			);
		}

		$tf_top = $request_terms;
		arsort( $tf_top );
		$tf_top = array_slice( $tf_top, 0, 10, true );

		$public_statuses = get_post_stati( array( 'public' => true ) );

		$candidates = $repository->find_candidate_ids_by_stems(
			array_keys( $request_terms ),
			$allowed_types,
			$public_statuses,
			1000
		);

		if ( empty( $candidates ) ) {
			return rest_ensure_response(
				array(
					'suggestions' => array(),
					'meta'        => array(
						'reason' => 'no_candidates',
					),
				)
			);
		}

		$service = new RecommendationService( $repository, new SimilarityScorer() );
		$scores  = $service->recommend(
			$post_id,
			get_post_type( $post ),
			$request_terms,
			$candidates,
			get_post_type( $post ),
			array( $post_id )
		);

		if ( empty( $scores ) ) {
			return rest_ensure_response(
				array(
					'suggestions' => array(),
					'meta'        => array(
						'reason' => 'no_scores',
					),
				)
			);
		}

		$limit      = Settings::MAX_SUGGESTIONS;
		$sorted_ids = array_keys( $scores );
		$top_ids    = array_slice( $sorted_ids, 0, $limit );

		$posts = get_posts(
			array(
				'post__in'    => $top_ids,
				'post_type'   => $allowed_types,
				'post_status' => $public_statuses,
				'orderby'     => 'post__in',
				'numberposts' => count( $top_ids ),
			)
		);

		$posts_by_id = array();
		foreach ( $posts as $p ) {
			$posts_by_id[ $p->ID ] = $p;
		}

		$suggestions = array();
		foreach ( $top_ids as $id ) {
			if ( ! isset( $posts_by_id[ $id ] ) ) {
				continue;
			}

			$item          = $posts_by_id[ $id ];
			$suggestions[] = array(
				'id'        => $id,
				'title'     => $item->post_title,
				'permalink' => get_permalink( $item ),
				'post_type' => $item->post_type,
				'score'     => isset( $scores[ $id ] ) ? round( (float) $scores[ $id ], 4 ) : 0.0,
			);
		}

		return rest_ensure_response(
			array(
				'suggestions' => $suggestions,
				'meta'        => array(
					'post_id'          => $post_id,
					'count'            => count( $suggestions ),
					'limit'            => $limit,
					'available_terms'  => count( $request_terms ),
					'candidate_counts' => count( $candidates ),
					'tf_top'           => $tf_top,
				),
			)
		);
	}
}
