<?php
/**
 * REST integration tests for the schema preview endpoint.
 *
 * @package AirygenTest\Admin\Rest
 */

declare(strict_types=1);

namespace AirygenTest\Admin\Rest;

use Airygen\Support\Meta\PostData;
use WP_REST_Request;
use WP_REST_Response;

/**
 * @coversNothing
 */
class SchemaPreviewRouteTest extends RestRouteTestCase {

	/**
	 * Ensure the route is registered.
	 *
	 * @return void
	 */
	public function test_route_is_registered(): void {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/airygen/v1/schema/preview', $routes );
	}

	/**
	 * Subscribers should not be allowed to preview.
	 *
	 * @return void
	 */
	public function test_permission_denied_for_non_editor(): void {
		$post_id = self::factory()->post->create();
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'GET', '/airygen/v1/schema/preview' );
		$request->set_param( 'post', $post_id );

		$response = $this->server->dispatch( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Admins should receive JSON-LD payload.
	 *
	 * @return void
	 */
	public function test_successful_preview_response(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Preview Schema Title',
				'post_content' => '<p>Example content for schema preview.</p>',
				'post_excerpt' => 'Excerpt text',
			)
		);

		update_option(
			'airygen_schema',
			array(
				'organization_name' => 'Preview Org',
				'organization_type' => 'Organization',
				'article_type'      => 'Article',
				'visibility'        => array(
					'organization' => true,
					'website'      => true,
					'breadcrumb'   => false,
					'article'      => true,
				),
			)
		);

		PostData::save(
			$post_id,
			array(
				'title'       => 'Custom Schema Title',
				'description' => 'Custom Schema Description',
			)
		);

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'GET', '/airygen/v1/schema/preview' );
		$request->set_param( 'post', $post_id );

		$response = $this->server->dispatch( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'jsonld', $data );
		$this->assertIsArray( $data['jsonld'] );
		$this->assertSame( $post_id, $data['post_id'] );
	}
}
