<?php
/**
 * REST tests for llms.txt endpoints.
 *
 * @package AirygenTest\Admin\Rest
 */

declare(strict_types=1);

namespace AirygenTest\Admin\Rest;

use Airygen\Modules\LlmsTxt\Admin\Settings;
use WP_REST_Response;

/**
 * @coversNothing
 */
final class LlmsTxtRouteTest extends RestRouteTestCase {

	/**
	 * Preview endpoint should return plain text content payload.
	 *
	 * @return void
	 */
	public function test_preview_endpoint_returns_content(): void {
		$this->acting_as_admin();
		$post_one = self::factory()->post->create(
			array(
				'post_title'   => 'Tokyo Guide',
				'post_content' => str_repeat( 'Japan travel tips. ', 30 ),
				'post_status'  => 'publish',
			)
		);
		$post_two = self::factory()->post->create(
			array(
				'post_title'   => 'Kyoto Guide',
				'post_content' => str_repeat( 'Kyoto travel tips. ', 30 ),
				'post_status'  => 'publish',
			)
		);

		Settings::update(
			array(
				'enabled'         => true,
				'index_strategy'  => 'curated_only',
				'exclude_noindex' => false,
				'min_word_count'  => 0,
				'sections'        => array(
					array(
						'id'          => 'start_here',
						'title'       => 'Start Here',
						'description' => '',
						'post_ids'    => array( $post_two ),
						'max_items'   => 10,
					),
					array(
						'id'          => 'featured_like',
						'title'       => 'Important Articles',
						'description' => '',
						'post_ids'    => array( $post_one ),
						'max_items'   => 10,
					),
				),
				'post_types'      => array( 'post' ),
			)
		);

		$response = $this->rest_post(
			'/airygen/v1/llms-txt/preview',
			array(
				'settings' => array(
					'enabled'         => true,
					'index_strategy'  => 'curated_only',
					'exclude_noindex' => false,
					'min_word_count'  => 0,
					'post_types'      => array( 'post' ),
					'sections'        => array(
						array(
							'id'          => 'start_here',
							'title'       => 'Start Here',
							'description' => '',
							'post_ids'    => array( $post_two ),
							'max_items'   => 10,
							'hidden'      => false,
						),
						array(
							'id'          => 'featured_like',
							'title'       => 'Important Articles',
							'description' => '',
							'post_ids'    => array( $post_one ),
							'max_items'   => 10,
							'hidden'      => false,
						),
					),
				),
			)
		);
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'type', $data );
		$this->assertArrayHasKey( 'content', $data );
		$this->assertStringContainsString( '## Start Here', (string) $data['content'] );
		$this->assertStringContainsString( '## Important Articles', (string) $data['content'] );
	}

	/**
	 * Post lookup endpoint should return searchable post list.
	 *
	 * @return void
	 */
	public function test_posts_endpoint_returns_items(): void {
		$this->acting_as_admin();
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Osaka itinerary',
				'post_content' => 'Trip notes',
				'post_status'  => 'publish',
			)
		);

		$response = $this->rest_get(
			'/airygen/v1/llms-txt/posts',
			array(
				'q' => 'Osaka',
			)
		);

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'items', $data );
		$this->assertIsArray( $data['items'] );
		$this->assertNotEmpty( $data['items'] );
		$this->assertSame( $post_id, (int) $data['items'][0]['id'] );
	}

	/**
	 * Clear-cache endpoint should return success payload.
	 *
	 * @return void
	 */
	public function test_clear_cache_endpoint_returns_success(): void {
		$this->acting_as_admin();

		$response = $this->rest_post( '/airygen/v1/llms-txt/clear-cache' );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertTrue( (bool) $data['cleared'] );
	}
}
