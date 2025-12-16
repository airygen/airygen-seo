<?php
/**
 * REST tests for NotFound Manager endpoints.
 *
 * @package AirygenTest\Admin\Rest
 */

declare(strict_types=1);

namespace AirygenTest\Admin\Rest;

use WP_REST_Response;

/**
 * @coversNothing
 */
final class NotFoundManagerRouteTest extends RestRouteTestCase {

	/**
	 * Logs endpoint should be reachable for admins.
	 *
	 * @return void
	 */
	public function test_logs_endpoint_accessible_for_admin(): void {
		$this->acting_as_admin();

		$response = $this->rest_get( '/airygen/v1/404-manager/logs' );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'items', $data );
		$this->assertArrayHasKey( 'total', $data );
	}

	/**
	 * Settings endpoint should update payload.
	 *
	 * @return void
	 */
	public function test_update_settings_works(): void {
		$this->acting_as_admin();

		$request = new \WP_REST_Request( 'PUT', '/airygen/v1/404-manager/settings' );
		$request->set_body(
			wp_json_encode(
				array(
					'monitor_mode'        => 'advanced',
					'ignore_query_params' => false,
					'retention_days'      => 14,
				)
			)
		);
		$request->set_header( 'Content-Type', 'application/json' );
		$response = $this->server->dispatch( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'advanced', $data['monitor_mode'] );
		$this->assertFalse( (bool) $data['ignore_query_params'] );
		$this->assertSame( 14, (int) $data['retention_days'] );
	}
}
