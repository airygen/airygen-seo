<?php
/**
 * REST endpoints for Related Posts / Link Suggestions settings and reindex trigger.
 *
 * @package Airygen\Admin\Rest
 */

declare(strict_types=1);

namespace Airygen\Admin\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Admin\RestRouteInterface;
use Airygen\Constants;
use Airygen\Modules\LinkSuggestions\Admin\Settings as RelatedSettings;
use Airygen\Modules\LinkSuggestions\Persistence\LinkTermsRepository;
use Airygen\Support\Errors\ErrorCodes;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

use function current_user_can;
use function get_post_stati;
use function get_post_type_object;
use function get_post_types;
use function max;

/**
 * Registers related settings and reindex endpoints.
 */
class RelatedRoute implements RestRouteInterface {

	private const NAMESPACE      = 'airygen/v1';
	private const SETTINGS_ROUTE = '/link-suggestions/settings';
	private const REINDEX_ROUTE  = '/link-suggestions/reindex';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register(): void {
		register_rest_route(
			self::NAMESPACE,
			self::SETTINGS_ROUTE,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			self::REINDEX_ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'trigger_reindex' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Permission check.
	 *
	 * @return bool|WP_Error
	 */
	public function can_manage() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new WP_Error( ErrorCodes::REST_FORBIDDEN, __( 'You are not allowed to manage related settings.', 'airygen-seo' ), array( 'status' => 403 ) );
	}

	/**
	 * GET /link-suggestions/settings
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings( WP_REST_Request $request ): WP_REST_Response {
		$settings = RelatedSettings::get();

		return new WP_REST_Response(
			array_merge(
				$settings,
				array(
					'stats' => $this->build_stats( $settings['allowed_post_types'] ),
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
	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$payload = $request->get_json_params();
		$payload = is_array( $payload ) ? $payload : array();

		$settings = RelatedSettings::update( $payload );

		return new WP_REST_Response(
			array_merge(
				$settings,
				array(
					'stats' => $this->build_stats( $settings['allowed_post_types'] ),
				)
			)
		);
	}

	/**
	 * POST /link-suggestions/reindex
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function trigger_reindex( WP_REST_Request $request ): WP_REST_Response {
		// Placeholder: will integrate with Action Scheduler to queue full reindex.
		do_action( Constants::HOOK_LINK_SUGGESTIONS_REINDEX_ALL ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook constant is already plugin-prefixed.

		return new WP_REST_Response(
			array(
				'queued' => true,
			)
		);
	}

	/**
	 * Build index stats for allowed post types.
	 *
	 * @param array<int,string> $post_types Allowed post types.
	 *
	 * @return array<int,array<string,int|string>>
	 */
	private function build_stats( array $post_types ): array {
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

			$total   = $this->count_posts_by_type( $post_type, $statuses );
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
	 * @param array  $statuses  Allowed statuses.
	 *
	 * @return int
	 */
	private function count_posts_by_type( string $post_type, array $statuses ): int {
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
}
