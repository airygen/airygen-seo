<?php
/**
 * REST controller exposing Sitewide SEO diagnostics.
 *
 * @package Airygen\Modules\SitewideSeo\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\SitewideSeo\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles Sitewide SEO REST responses.
 */
final class RestController {

	/**
	 * Determine whether the current user can view diagnostics.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return bool
	 */
	// phpcs:disable
	public static function can_view( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handle GET requests for diagnostics.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_get( WP_REST_Request $request ) {
		$results   = Hooks::get_results();
		$timestamp = Hooks::get_results_timestamp();

		return rest_ensure_response(
			array(
				'tests' => $results,
				'meta'  => array(
					'site_url'  => home_url(),
					'timestamp' => $timestamp ? $timestamp : current_time( 'mysql' ),
				),
			)
		);
	}
	// phpcs:enable
}
