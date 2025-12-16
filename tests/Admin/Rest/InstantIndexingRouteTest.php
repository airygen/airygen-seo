<?php
/**
 * REST tests for IndexNow routes.
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
class InstantIndexingRouteTest extends RestRouteTestCase {

	/**
	 * Status endpoint should provide summary data.
	 *
	 * @return void
	 */
	public function test_status_route_returns_summary(): void {
		$this->acting_as_admin();

		$response = $this->rest_get( '/airygen/v1/indexnow/status' );

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();

		$this->assertArrayHasKey( 'summary', $data );
		$this->assertArrayHasKey( 'recent', $data );
		$this->assertArrayHasKey( 'logs', $data );
	}

	/**
	 * Manual submission endpoint should queue valid URLs.
	 *
	 * @return void
	 */
	public function test_manual_route_queues_urls(): void {
		global $wpdb;

		$this->acting_as_admin();

		$valid_url = home_url( '/manual-index-test' );

		$response = $this->rest_post(
			'/airygen/v1/indexnow/manual',
			array(
				'urls'   => array( $valid_url, 'https://external.com/ignore' ),
				'action' => 'update',
			)
		);

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();
		$this->assertSame( 1, $data['queued'] );

		$table = $wpdb->prefix . Constants::TABLE_INDEXNOW_EVENTS;
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->assertSame( 1, $count );
	}

	/**
	 * Manual submissions should reject invalid payloads.
	 *
	 * @return void
	 */
	public function test_manual_route_requires_urls(): void {
		$this->acting_as_admin();

		$response = $this->rest_post(
			'/airygen/v1/indexnow/manual',
			array(
				'urls' => array(),
			)
		);

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 400, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'airygen_indexnow_missing_urls', $data['code'] ?? '' );
	}

	/**
	 * Backfill endpoint should accept valid post types.
	 *
	 * @return void
	 */
	public function test_backfill_route_queues_job(): void {
		self::factory()->post->create();
		$this->acting_as_admin();

		$response = $this->rest_post(
			'/airygen/v1/indexnow/backfill',
			array(
				'post_types' => array( 'post' ),
			)
		);

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertTrue( $response->get_data()['queued'] );
	}

	/**
	 * Rotate key endpoint should issue a new key.
	 *
	 * @return void
	 */
	public function test_rotate_key_updates_option(): void {
		$this->acting_as_admin();

		$response = $this->rest_post( '/airygen/v1/indexnow/rotate-key' );

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$key = $response->get_data()['key'];
		$this->assertNotEmpty( $key );

		$option = get_option( 'airygen_indexnow', array() );
		$this->assertSame( $key, $option['key'] ?? '' );
	}
}
