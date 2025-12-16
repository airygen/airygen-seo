<?php
/**
 * REST tests for Notify endpoints.
 *
 * @package AirygenTest\Admin\Rest
 */

declare(strict_types=1);

namespace AirygenTest\Admin\Rest;

use WP_REST_Response;

/**
 * @coversNothing
 */
final class NotifyRouteTest extends RestRouteTestCase {

	/**
	 * Settings endpoint should be reachable.
	 *
	 * @return void
	 */
	public function test_settings_endpoint_accessible_for_admin(): void {
		$this->acting_as_admin();

		$response = $this->rest_get( '/airygen/v1/notify/settings' );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Send-now should return result payload.
	 *
	 * @return void
	 */
	public function test_send_now_endpoint_returns_results(): void {
		$this->acting_as_admin();

		$response = $this->rest_post( '/airygen/v1/notify/send-now' );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'ok', $data );
		$this->assertArrayHasKey( 'results', $data );
	}

	/**
	 * Logs endpoint should return pagination payload.
	 *
	 * @return void
	 */
	public function test_logs_endpoint_returns_pagination(): void {
		$this->acting_as_admin();

		$response = $this->rest_get(
			'/airygen/v1/notify/logs',
			array(
				'page'     => 1,
				'per_page' => 20,
			)
		);
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'items', $data );
		$this->assertArrayHasKey( 'pagination', $data );
	}
}
