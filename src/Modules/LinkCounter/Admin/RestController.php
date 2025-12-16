<?php
/**
 * REST controller for Link Counter admin tools.
 *
 * @package Airygen\Modules\LinkCounter\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkCounter\Admin;

use Airygen\Modules\LinkCounter\Domain\Storage;
use Airygen\Modules\LinkCounter\Runtime\Hooks as RuntimeHooks;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers REST endpoints for Link Counter status + actions.
 */
final class RestController {

	/**
	 * Permission callback for Link Counter REST endpoints.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handle status requests.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_status(): WP_REST_Response {
		return rest_ensure_response(
			array(
				'status' => StatusReporter::get_status(),
			)
		);
	}

	/**
	 * Handle recheck requests.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_recheck( WP_REST_Request $request ) {
		unset( $request );

		$storage = new Storage();
		$updated = $storage->reset_all_to_pending();

		RuntimeHooks::queue_backlog_processing();

		return rest_ensure_response(
			array(
				'updated' => $updated,
				'status'  => StatusReporter::get_status(),
			)
		);
	}
}
