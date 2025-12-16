<?php
/**
 * REST controller for managing debug logging state.
 *
 * @package Airygen\Admin
 */

declare(strict_types=1);

namespace Airygen\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Support\Debug\Settings as DebugSettings;
use Airygen\Support\Errors\ErrorCodes;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Provides endpoints for enabling and inspecting Airygen debug logs.
 */
final class DebugRestController {

	/**
	 * Return the current debug configuration.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_get(): WP_REST_Response {
		return rest_ensure_response( self::serialize_state() );
	}

	/**
	 * Enable debug logging and return the updated state.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_enable() {
		$result = DebugSettings::enable();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( self::serialize_state() );
	}

	/**
	 * Disable debug logging and return the updated state.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_disable(): WP_REST_Response {
		DebugSettings::disable();
		return rest_ensure_response( self::serialize_state() );
	}

	/**
	 * Clear all debug logs and return the updated state.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_clear(): WP_REST_Response {
		DebugSettings::clear_logs();
		return rest_ensure_response( self::serialize_state() );
	}

	/**
	 * Toggle forcing classic editor mode.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_editor_mode( WP_REST_Request $request ): WP_REST_Response {
		$enabled = (bool) $request->get_param( 'forceClassic' );
		DebugSettings::set_force_classic( $enabled );

		return rest_ensure_response( self::serialize_state() );
	}

	/**
	 * Update debug log level.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public static function handle_level( WP_REST_Request $request ): WP_REST_Response {
		$level = (string) $request->get_param( 'level' );
		DebugSettings::set_level( $level );

		return rest_ensure_response( self::serialize_state() );
	}

	/**
	 * Return the log contents for a specific date.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_log_view( WP_REST_Request $request ) {
		$date = (string) $request->get_param( 'date' );
		if ( '' === $date ) {
			return new WP_Error(
				ErrorCodes::AIRYGEN_DEBUG_INVALID_DATE,
				__( 'Please provide a valid log date.', 'airygen-seo' ),
				array( 'status' => 400 )
			);
		}

		$content = DebugSettings::read_log( $date );

		return rest_ensure_response(
			array(
				'content' => null === $content ? '' : $content,
				'exists'  => null !== $content,
			)
		);
	}

	/**
	 * Prepare the debug state payload for REST responses.
	 *
	 * @return array<string, mixed>
	 */
	private static function serialize_state(): array {
		$config    = DebugSettings::get_config();
		$directory = $config['enabled']
		? DebugSettings::get_directory_path()
		: null;

		return array(
			'config' => array(
				'enabled'      => ! empty( $config['enabled'] ),
				'slug'         => $config['slug'],
				'directory'    => $directory,
				'forceClassic' => ! empty( $config['force_classic'] ),
				'level'        => isset( $config['level'] ) ? (string) $config['level'] : 'info',
			),
			'logs'   => DebugSettings::list_logs(),
		);
	}
}
