<?php
/**
 * REST tests for /airygen/v1/session-check.
 *
 * @package AirygenTest\Admin\Rest
 */

declare(strict_types=1);

namespace AirygenTest\Admin\Rest;

use WP_REST_Response;

/**
 * @coversNothing
 */
final class SessionCheckRouteTest extends RestRouteTestCase {

	/**
	 * Endpoint should return ok=true for authenticated editors/admins.
	 *
	 * @return void
	 */
	public function test_session_check_returns_ok_payload(): void {
		$this->acting_as_admin();

		$response = $this->rest_get( '/airygen/v1/session-check' );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'ok' => true,
			),
			$response->get_data()
		);
	}
}
