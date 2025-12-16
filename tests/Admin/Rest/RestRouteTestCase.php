<?php
/**
 * Base test case for REST API integration tests.
 *
 * @package AirygenTest\Admin\Rest
 */

declare(strict_types=1);

namespace AirygenTest\Admin\Rest;

use AirygenTest\BaseTestCase;
use AirygenTest\Support\DatabaseHelpers;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Provides helpers for dispatching REST requests in tests.
 */
abstract class RestRouteTestCase extends BaseTestCase {

	/**
	 * REST server instance under test.
	 *
	 * @var WP_REST_Server|null
	 */
	protected ?WP_REST_Server $server = null;

	/**
	 * Prepare REST server + required tables.
	 */
	public function set_up(): void {
		parent::set_up();

		DatabaseHelpers::ensure_custom_tables();
		DatabaseHelpers::truncate_custom_tables();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down REST server instance.
	 */
	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		$this->server   = null;

		parent::tear_down();
	}

	/**
	 * Create and authenticate an administrator user for REST calls.
	 *
	 * @return int User ID.
	 */
	protected function acting_as_admin(): int {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		return $user_id;
	}

	/**
	 * Dispatch a REST GET request to the server.
	 *
	 * @param string               $route Route path.
	 * @param array<string, mixed> $params Query args.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	protected function rest_get( string $route, array $params = array() ) {
		$request = new WP_REST_Request( 'GET', $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $this->server->dispatch( $request );
	}

	/**
	 * Dispatch a REST POST request with an optional JSON payload.
	 *
	 * @param string               $route Route path.
	 * @param array<string, mixed> $payload JSON payload.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	protected function rest_post( string $route, array $payload = array() ) {
		$request = new WP_REST_Request( 'POST', $route );

		if ( ! empty( $payload ) ) {
			$request->set_body( wp_json_encode( $payload ) );
			$request->set_header( 'Content-Type', 'application/json' );
		}

		return $this->server->dispatch( $request );
	}

	/**
	 * Dispatch a REST DELETE request.
	 *
	 * @param string $route Route path.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	protected function rest_delete( string $route ) {
		$request = new WP_REST_Request( 'DELETE', $route );
		return $this->server->dispatch( $request );
	}
}
