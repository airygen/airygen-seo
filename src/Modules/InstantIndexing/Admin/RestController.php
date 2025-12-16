<?php
/**
 * REST endpoints for IndexNow tooling.
 *
 * @package Airygen\Modules\InstantIndexing\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\InstantIndexing\Admin;

use Airygen\Modules\InstantIndexing\Runtime\BackfillJob;
use Airygen\Modules\InstantIndexing\Runtime\Hooks;
use Airygen\Modules\InstantIndexing\Runtime\QueueRepository;
use Airygen\Modules\InstantIndexing\Runtime\ResponseLogger;
use Airygen\Support\Errors\ErrorCodes;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers REST endpoints for Instant Indexing tooling.
 */
final class RestController {

	/**
	 * Permission callback for Instant Indexing endpoints.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission callback for cloud-submission actions.
	 *
	 * @return bool
	 */
	public static function can_manage_cloud(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Return queue/log status details.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_status(): WP_REST_Response {
		$queue   = new QueueRepository();
		$logger  = new ResponseLogger();
		$summary = $queue->summary()->to_array();
		$recent  = $queue->recent( 10 );
		$logs    = $logger->all();

		return rest_ensure_response(
			array(
				'summary' => $summary,
				'recent'  => $recent,
				'logs'    => $logs,
				'key'     => self::key_status(),
			)
		);
	}

	/**
	 * Handle manual submission requests.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_manual( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$urls   = isset( $params['urls'] ) && is_array( $params['urls'] ) ? $params['urls'] : array();
		$action = isset( $params['action'] ) ? strtolower( (string) $params['action'] ) : 'update';

		if ( ! in_array( $action, array( 'add', 'update', 'delete' ), true ) ) {
			return new WP_Error( ErrorCodes::AIRYGEN_INDEXNOW_INVALID_ACTION, __( 'Invalid action.', 'airygen-seo' ), array( 'status' => 400 ) );
		}

		if ( empty( $urls ) ) {
			return new WP_Error( ErrorCodes::AIRYGEN_INDEXNOW_MISSING_URLS, __( 'Provide at least one URL.', 'airygen-seo' ), array( 'status' => 400 ) );
		}

		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! is_string( $site_host ) || '' === $site_host ) {
			return new WP_Error( ErrorCodes::AIRYGEN_INDEXNOW_MISSING_HOST, __( 'Unable to determine site host.', 'airygen-seo' ), array( 'status' => 500 ) );
		}

		$queue  = new QueueRepository();
		$queued = 0;

		foreach ( $urls as $url ) {
			$clean = esc_url_raw( (string) $url );
			if ( '' === $clean ) {
				continue;
			}

			$host = wp_parse_url( $clean, PHP_URL_HOST );
			if ( strtolower( (string) $host ) !== strtolower( (string) $site_host ) ) {
				continue;
			}

			if ( $queue->enqueue( (string) $host, $clean, $action, 'manual' ) ) {
				++$queued;
			}
		}

		if ( $queued > 0 ) {
			Hooks::queue_queue_processing();
		}

		return rest_ensure_response(
			array(
				'queued' => $queued,
			)
		);
	}

	/**
	 * Queue a backfill job for selected post types.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_backfill( WP_REST_Request $request ) {
		$params     = $request->get_json_params();
		$post_types = isset( $params['post_types'] ) && is_array( $params['post_types'] ) ? $params['post_types'] : array();

		if ( empty( $post_types ) ) {
			return new WP_Error( ErrorCodes::AIRYGEN_INDEXNOW_MISSING_POST_TYPES, __( 'Select at least one post type.', 'airygen-seo' ), array( 'status' => 400 ) );
		}

		$backfill = new BackfillJob( new QueueRepository() );
		$backfill->enqueue( $post_types );

		return rest_ensure_response( array( 'queued' => true ) );
	}

	/**
	 * Rotate the stored IndexNow key.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_rotate_key(): WP_REST_Response {
		$settings        = Settings::get();
		$key             = Settings::generate_key();
		$settings['key'] = $key;
		Settings::update( $settings );

		return rest_ensure_response(
			array(
				'key' => $key,
			)
		);
	}

	/**
	 * Retrieve key reachability metadata.
	 *
	 * @return array<string, mixed>
	 */
	private static function key_status(): array {
		$settings = Settings::get();
		$key      = isset( $settings['key'] ) ? (string) $settings['key'] : '';
		$location = Settings::key_location( $settings );

		if ( '' === $location ) {
			return array(
				'present'   => '' !== $key,
				'reachable' => false,
				'message'   => __( 'Set a key to start sending submissions.', 'airygen-seo' ),
				'location'  => '',
			);
		}

		$response = wp_remote_head( $location, array( 'timeout' => 8 ) );
		if ( is_wp_error( $response ) ) {
			return array(
				'present'   => '' !== $key,
				'reachable' => false,
				'message'   => $response->get_error_message(),
				'location'  => $location,
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		return array(
			'present'   => '' !== $key,
			'reachable' => $code >= 200 && $code < 400,
			'message'   => sprintf(
				/* translators: %d: HTTP response code. */
				__( 'Key file responded with HTTP %d.', 'airygen-seo' ),
				$code
			),
			'location'  => $location,
		);
	}
}
