<?php
/**
 * REST integration tests for score recalculation queue endpoints.
 *
 * @package AirygenTest\Admin\Rest
 */

declare(strict_types=1);

namespace AirygenTest\Admin\Rest;

use Airygen\Modules\ScoreCalculator\Admin\Settings as ScoreSettings;
use WP_REST_Response;

/**
 * @coversNothing
 */
final class ScoreRecalculateRouteTest extends RestRouteTestCase {

	/**
	 * Ensure queue routes are registered.
	 *
	 * @return void
	 */
	public function test_routes_are_registered(): void {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/airygen/v1/score/recalculate', $routes );
		$this->assertArrayHasKey( '/airygen/v1/score/recalculate-step', $routes );
		$this->assertArrayHasKey( '/airygen/v1/score/recalculate-status', $routes );
	}

	/**
	 * Ensure non-admin users cannot start recalculation.
	 *
	 * @return void
	 */
	public function test_recalculate_requires_manage_options(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$response = $this->rest_post( '/airygen/v1/score/recalculate' );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Ensure queue status includes selected scoped post types.
	 *
	 * @return void
	 */
	public function test_recalculate_uses_scope_post_types(): void {
		$this->acting_as_admin();

		self::factory()->post->create_many( 2, array( 'post_type' => 'post' ) );
		self::factory()->post->create_many( 1, array( 'post_type' => 'page' ) );

		ScoreSettings::update(
			array(
				'postTypes' => array( 'post', 'page' ),
			)
		);

		$response = $this->rest_post( '/airygen/v1/score/recalculate' );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'status', $data );
		$this->assertIsArray( $data['status'] );
		$this->assertArrayHasKey( 'postTypes', $data['status'] );
		$this->assertIsArray( $data['status']['postTypes'] );

		$slugs = array_map(
			static function ( array $item ): string {
				return (string) ( $item['slug'] ?? '' );
			},
			$data['status']['postTypes']
		);
		sort( $slugs );

		$this->assertSame( array( 'page', 'post' ), $slugs );
	}

	/**
	 * Ensure recalculation can use currently selected scope from request payload.
	 *
	 * @return void
	 */
	public function test_recalculate_prefers_requested_scope(): void {
		$this->acting_as_admin();

		self::factory()->post->create_many( 1, array( 'post_type' => 'post' ) );
		self::factory()->post->create_many( 1, array( 'post_type' => 'page' ) );

		ScoreSettings::update(
			array(
				'postTypes' => array( 'post', 'page' ),
			)
		);

		$response = $this->rest_post(
			'/airygen/v1/score/recalculate',
			array(
				'postTypes' => array( 'page' ),
			)
		);

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'status', $data );
		$this->assertIsArray( $data['status'] );
		$this->assertArrayHasKey( 'postTypes', $data['status'] );
		$this->assertIsArray( $data['status']['postTypes'] );
		$this->assertCount( 1, $data['status']['postTypes'] );
		$this->assertSame( 'page', (string) ( $data['status']['postTypes'][0]['slug'] ?? '' ) );
	}

	/**
	 * Ensure recalculation step endpoint returns status payload.
	 *
	 * @return void
	 */
	public function test_recalculate_step_returns_status(): void {
		$this->acting_as_admin();
		self::factory()->post->create_many( 1, array( 'post_type' => 'post' ) );

		$this->rest_post( '/airygen/v1/score/recalculate' );
		$response = $this->rest_post( '/airygen/v1/score/recalculate-step' );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'status', $data );
	}
}
