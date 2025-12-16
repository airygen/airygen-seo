<?php
/**
 * REST tests for broken link log routes.
 *
 * @package AirygenTest\Admin\Rest
 */

declare(strict_types=1);

namespace AirygenTest\Admin\Rest;

use Airygen\Modules\BrokenLinkChecker\Domain\LogRepository;
use WP_REST_Response;

/**
 * @coversNothing
 */
class BrokenLinksRouteTest extends RestRouteTestCase {

	/**
	 * Logs endpoint should return paginated results.
	 *
	 * @return void
	 */
	public function test_logs_endpoint_returns_entries(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title' => 'Broken Link Host',
			)
		);

		$repo = new LogRepository();
		$repo->upsert(
			array(
				'link_id'       => 100,
				'post_id'       => $post_id,
				'url'           => 'https://example.com/broken',
				'status_code'   => 404,
				'status_label'  => 'error',
				'error_message' => 'Not found',
				'data_source'   => 'http_request',
				'checked_at'    => gmdate( 'Y-m-d H:i:s' ),
				'created_at'    => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		$this->acting_as_admin();

		$response = $this->rest_get(
			'/airygen/v1/broken-links/logs',
			array(
				'page'     => 1,
				'statuses' => 'error',
			)
		);

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();

		$this->assertArrayHasKey( 'logs', $data );
		$this->assertNotEmpty( $data['logs'] );
		$this->assertSame( 'https://example.com/broken', $data['logs'][0]['url'] );
		$this->assertArrayHasKey( 'pagination', $data );
	}
}
