<?php
/**
 * REST controller for 404 Manager.
 *
 * @package Airygen\Modules\NotFoundManager\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\NotFoundManager\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\NotFoundManager\Infrastructure\LogRepository;
use Airygen\Support\Errors\ErrorCodes;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Exposes 404 manager REST endpoints.
 */
final class RestController {

	/**
	 * Manage capability check.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handle logs list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle_logs( WP_REST_Request $request ): WP_REST_Response {
		$repo          = new LogRepository();
		$page          = max( 1, (int) $request->get_param( 'page' ) );
		$per_page      = max( 1, min( 200, (int) $request->get_param( 'per_page' ) ) );
		$status        = (string) $request->get_param( 'status' );
		$search        = $request->get_param( 'q' );
		$search_string = is_string( $search ) ? $search : null;

		return rest_ensure_response( $repo->list( $page, $per_page, $status, $search_string ) );
	}

	/**
	 * Handle stats.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_stats(): WP_REST_Response {
		$repo = new LogRepository();
		return rest_ensure_response( $repo->stats() );
	}

	/**
	 * Return module settings.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_get_settings(): WP_REST_Response {
		Settings::ensure_exists();
		return rest_ensure_response( Settings::get() );
	}

	/**
	 * Update module settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_update_settings( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_SETTINGS_INVALID_PAYLOAD,
				__( 'Invalid settings payload.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		Settings::update( $payload );
		return rest_ensure_response( Settings::get() );
	}

	/**
	 * Mark a log entry as resolved.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_resolve_log( WP_REST_Request $request ) {
		$repo = new LogRepository();
		$id   = (int) $request->get_param( 'id' );
		if ( $id <= 0 ) {
			return new WP_Error( ErrorCodes::INVALID_ID, __( 'Invalid log id.', 'airygen-seo' ), array( 'status' => 400 ) );
		}

		$repo->mark_status( $id, 'resolved' );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * Mark a log entry as ignored.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_ignore_log( WP_REST_Request $request ) {
		$repo = new LogRepository();
		$id   = (int) $request->get_param( 'id' );
		if ( $id <= 0 ) {
			return new WP_Error( ErrorCodes::INVALID_ID, __( 'Invalid log id.', 'airygen-seo' ), array( 'status' => 400 ) );
		}

		$repo->mark_status( $id, 'ignored' );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * Delete log entry.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_delete_log( WP_REST_Request $request ) {
		$repo = new LogRepository();
		$id   = (int) $request->get_param( 'id' );
		if ( $id <= 0 ) {
			return new WP_Error( ErrorCodes::INVALID_ID, __( 'Invalid log id.', 'airygen-seo' ), array( 'status' => 400 ) );
		}

		$repo->delete( $id );
		return rest_ensure_response( array( 'ok' => true ) );
	}
}
