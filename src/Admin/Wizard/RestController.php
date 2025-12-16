<?php
/**
 * REST controller for the install wizard dismiss endpoint.
 *
 * @package Airygen\Admin\Wizard
 */

declare(strict_types=1);

namespace Airygen\Admin\Wizard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles the wizard dismiss request.
 */
final class RestController {

	/**
	 * Determine whether the current user can manage the wizard.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permanently dismiss the install wizard for this site.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function handle_dismiss( WP_REST_Request $request ): WP_REST_Response {
		$dismissed = (bool) $request->get_param( 'dismissed' );

		if ( $dismissed ) {
			update_option( Constants::OPTION_WIZARD_DISMISSED, true, 'no' );
		}

		return rest_ensure_response( array( 'dismissed' => $dismissed ) );
	}
}
