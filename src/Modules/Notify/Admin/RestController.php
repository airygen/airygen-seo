<?php
/**
 * REST controller for Notify module.
 *
 * @package Airygen\Modules\Notify\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\Notify\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\Notify\Infrastructure\Channels\ChannelRegistry;
use Airygen\Modules\Notify\Infrastructure\DigestDispatcher;
use Airygen\Modules\Notify\Infrastructure\LogRepository;
use Airygen\Support\Errors\ErrorCodes;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Exposes notify REST endpoints.
 */
final class RestController {

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get settings.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_get_settings(): WP_REST_Response {
		Settings::ensure_exists();
		return rest_ensure_response( Settings::get() );
	}

	/**
	 * Update settings.
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
	 * List notify logs (phase 1 placeholder).
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_logs( WP_REST_Request $request ): WP_REST_Response {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = (int) $request->get_param( 'per_page' );
		$per_page = $per_page > 0 ? $per_page : 20;
		$per_page = max( 1, min( 50, $per_page ) );

		$repository  = new LogRepository();
		$items       = $repository->get_logs( $page, $per_page );
		$total       = $repository->count_logs();
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );

		return rest_ensure_response(
			array(
				'items'      => $items,
				'pagination' => array(
					'page'       => $page,
					'perPage'    => $per_page,
					'total'      => $total,
					'totalPages' => $total_pages,
				),
			)
		);
	}

	/**
	 * Test channel delivery.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_test_channel( WP_REST_Request $request ) {
		$key     = (string) $request->get_param( 'channel' );
		$channel = ChannelRegistry::find( $key );
		if ( null === $channel ) {
			return new WP_Error( ErrorCodes::INVALID_CHANNEL, __( 'Invalid notify channel.', 'airygen-seo' ), array( 'status' => 404 ) );
		}

		$settings   = Settings::get();
		$result     = $channel->send(
			$settings,
			__( 'Airygen Notify test', 'airygen-seo' ),
			__( 'This is a test message from Airygen Notify.', 'airygen-seo' )
		);
		$repository = new LogRepository();
		$repository->append(
			array(
				array(
					'channel' => $key,
					'ok'      => isset( $result['ok'] ) ? (bool) $result['ok'] : false,
					'message' => isset( $result['message'] ) ? (string) $result['message'] : '',
				),
			)
		);
		$retention_days = isset( $settings['logs']['retention_days'] ) ? (int) $settings['logs']['retention_days'] : 30;
		$retention_days = max( 1, min( 3650, $retention_days ) );
		$repository->purge_older_than_days( $retention_days );

		return rest_ensure_response(
			array(
				'channel' => $key,
				'result'  => $result,
			)
		);
	}

	/**
	 * Send digest now.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_send_now(): WP_REST_Response {
		$settings = Settings::get();
		$subject  = isset( $settings['message']['subject'] ) && is_string( $settings['message']['subject'] ) ? trim( $settings['message']['subject'] ) : '';
		if ( '' === $subject ) {
			$subject = __( 'Airygen SEO Daily Digest', 'airygen-seo' );
		}
		$dispatch = DigestDispatcher::dispatch(
			$settings,
			$subject,
			__( 'No new records were generated for the selected digest sections.', 'airygen-seo' )
		);

		return rest_ensure_response(
			array(
				'ok'      => $dispatch['ok'],
				'results' => $dispatch['results'],
			)
		);
	}
}
