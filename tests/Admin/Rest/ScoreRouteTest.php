<?php
/**
 * REST integration tests for the score endpoint.
 *
 * @package AirygenTest\Admin\Rest
 */

declare(strict_types=1);

namespace AirygenTest\Admin\Rest;

use Airygen\Constants;
use Airygen\Support\Meta\PostData;
use WP_REST_Request;
use WP_REST_Response;

/**
 * @coversNothing
 */
class ScoreRouteTest extends RestRouteTestCase {

	/**
	 * Ensure the /airygen/v1/score route is registered.
	 *
	 * @return void
	 */
	public function test_route_is_registered(): void {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/airygen/v1/score', $routes );
	}

	/**
	 * Users without edit capabilities should receive a 403 error.
	 *
	 * @return void
	 */
	public function test_permission_denied_for_non_editor(): void {
		$post_id = self::factory()->post->create();
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'GET', '/airygen/v1/score' );
		$request->set_param( 'post', $post_id );

		$response = $this->server->dispatch( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 403, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'airygen_forbidden', $data['code'] );
	}

	/**
	 * Valid requests should return the scoring payload.
	 *
	 * @return void
	 */
	public function test_successful_score_response(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Focus Title',
				'post_content' => '<p>Focus keywords keep the score runner occupied.</p>',
				'post_excerpt' => 'Excerpt text',
			)
		);

		PostData::save(
			$post_id,
			array(
				'title'          => 'Custom Title',
				'description'    => 'Custom Description',
				'focusKeyphrase' => 'Focus',
			)
		);

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'GET', '/airygen/v1/score' );
		$request->set_param( 'post', $post_id );

		$response = $this->server->dispatch( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data          = $response->get_data();
		$ruleset       = $this->score_rules();
		$expected_pack = $ruleset['pack'] ?? 'airygen-score-pack';

		$this->assertSame( $post_id, $data['post_id'] );
		$this->assertArrayHasKey( 'base', $data );
		$this->assertArrayHasKey( 'bonus', $data );
		$this->assertArrayHasKey( 'total', $data );
		$this->assertSame( $expected_pack, $data['pack'] );
		$score_cache = get_post_meta( $post_id, Constants::META_SCORE_CACHE, true );
		$this->assertIsArray( $score_cache );
		$this->assertArrayHasKey( 'score', $score_cache );
		$this->assertArrayHasKey( 'max', $score_cache );
		$this->assertArrayHasKey( 'updated_at', $score_cache );
	}

	/**
	 * Load the configured score rules.
	 *
	 * @return array<string,mixed>
	 */
	private function score_rules(): array {
		$path = plugin_dir_path( AIRYGEN_PLUGIN_FILE ) . 'config/score_rules.php';
		$data = require $path;

		return is_array( $data ) ? $data : array();
	}
}
