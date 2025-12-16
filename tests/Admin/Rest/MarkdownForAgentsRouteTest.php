<?php
/**
 * REST tests for Markdown for Agents endpoints.
 *
 * @package AirygenTest\Admin\Rest
 */

declare(strict_types=1);

namespace AirygenTest\Admin\Rest;

use WP_REST_Response;

/**
 * @coversNothing
 */
final class MarkdownForAgentsRouteTest extends RestRouteTestCase {

	/**
	 * Preview endpoint should return markdown payload.
	 *
	 * @return void
	 */
	public function test_preview_endpoint_returns_markdown(): void {
		$this->acting_as_admin();
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Markdown Preview Test',
				'post_content' => '<h2>Heading</h2><p>Hello <strong>world</strong>.</p>',
				'post_status'  => 'publish',
			)
		);

		$response = $this->rest_get(
			'/airygen/v1/markdown-for-agents/preview',
			array( 'post_id' => $post_id )
		);

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'markdown', $data );
		$this->assertStringContainsString( '# Markdown Preview Test', (string) $data['markdown'] );
	}

	/**
	 * Export endpoint should return markdown payload.
	 *
	 * @return void
	 */
	public function test_export_endpoint_returns_markdown(): void {
		$this->acting_as_admin();
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Markdown Export Test',
				'post_content' => '<p>Sample content.</p>',
				'post_status'  => 'publish',
			)
		);

		$response = $this->rest_get(
			'/airygen/v1/markdown-for-agents/export',
			array( 'post_id' => $post_id )
		);

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'markdown', $data );
	}

	/**
	 * Rebuild endpoint should return sync summary.
	 *
	 * @return void
	 */
	public function test_rebuild_endpoint_returns_summary(): void {
		$this->acting_as_admin();

		$response = $this->rest_post( '/airygen/v1/markdown-for-agents/rebuild' );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'total', $data );
		$this->assertArrayHasKey( 'synced', $data );
		$this->assertArrayHasKey( 'failed', $data );
	}

	/**
	 * Records endpoint should return paginated snapshot rows.
	 *
	 * @return void
	 */
	public function test_records_endpoint_returns_records(): void {
		$this->acting_as_admin();
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Markdown Records Test',
				'post_content' => '<p>Snapshot body.</p>',
				'post_status'  => 'publish',
			)
		);
		$this->rest_post(
			'/airygen/v1/markdown-for-agents/rebuild',
			array( 'post_ids' => array( $post_id ) )
		);

		$response = $this->rest_get(
			'/airygen/v1/markdown-for-agents/records',
			array(
				'page'      => 1,
				'per_page'  => 20,
				'post_type' => 'post',
			)
		);
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'records', $data );
		$this->assertArrayHasKey( 'pagination', $data );
		$this->assertIsArray( $data['records'] );
	}
}
