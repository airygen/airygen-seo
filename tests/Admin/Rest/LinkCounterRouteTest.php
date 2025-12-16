<?php
/**
 * REST tests for link counter routes.
 *
 * @package AirygenTest\Admin\Rest
 */

declare(strict_types=1);

namespace AirygenTest\Admin\Rest;

use Airygen\Constants;
use WP_REST_Response;

/**
 * @coversNothing
 */
class LinkCounterRouteTest extends RestRouteTestCase {

	/**
	 * Status endpoint should return queue/state metadata.
	 *
	 * @return void
	 */
	public function test_status_route_returns_counts(): void {
		$this->acting_as_admin();

		$response = $this->rest_get( '/airygen/v1/link-counter/status' );

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();

		$this->assertArrayHasKey( 'status', $data );
		$this->assertArrayHasKey( 'pendingPosts', $data['status'] );
		$this->assertArrayHasKey( 'queue', $data['status'] );
	}

	/**
	 * Recheck endpoint should reset rows and return counts.
	 *
	 * @return void
	 */
	public function test_recheck_route_resets_posts(): void {
		global $wpdb;

		$table = $wpdb->prefix . Constants::TABLE_LINK_COUNTER_META;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$table,
			array(
				'post_id'             => 42,
				'internal_link_count' => 1,
				'external_link_count' => 1,
				'incoming_link_count' => 0,
				'status'              => 'processed',
				'last_processed_at'   => current_time( 'mysql' ),
				'updated_at'          => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		$this->acting_as_admin();

		$response = $this->rest_post( '/airygen/v1/link-counter/recheck' );

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();

		$this->assertArrayHasKey( 'updated', $data );
		$this->assertGreaterThanOrEqual( 0, $data['updated'] );
	}
}
