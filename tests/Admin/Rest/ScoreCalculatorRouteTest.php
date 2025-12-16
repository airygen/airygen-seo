<?php
/**
 * REST tests for score calculator route.
 *
 * @package AirygenTest\Admin\Rest
 */

declare(strict_types=1);

namespace AirygenTest\Admin\Rest;

use Airygen\Support\Meta\PostData;

/**
 * @coversNothing
 */
final class ScoreCalculatorRouteTest extends RestRouteTestCase {

	/**
	 * Ensure the context title falls back to post title when meta title is empty.
	 *
	 * @return void
	 */
	public function test_score_context_uses_post_title_when_meta_title_empty(): void {
		$this->acting_as_admin();

		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Focus Keyword Title',
				'post_content' => 'Sample content for scoring.',
			)
		);

		PostData::save(
			$post_id,
			array(
				'title'          => '',
				'focusKeyphrase' => 'Focus Keyword',
			)
		);

		$captured = null;
		$filter   = static function ( array $context_data ) use ( &$captured ): array {
			$captured = $context_data;
			return $context_data;
		};

		add_filter( 'airygen_score_context_data', $filter, 10, 2 );

		$response = $this->rest_get(
			'/airygen/v1/score',
			array(
				'post' => $post_id,
			)
		);

		remove_filter( 'airygen_score_context_data', $filter, 10 );

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $captured );
		$this->assertSame( 'Focus Keyword Title', $captured['title'] ?? '' );
	}
}
