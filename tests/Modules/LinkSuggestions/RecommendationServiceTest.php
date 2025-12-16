<?php
/**
 * Tests for RecommendationService.
 *
 * @package AirygenTest\Modules\LinkSuggestions
 */

declare(strict_types=1);

namespace AirygenTest\Modules\LinkSuggestions;

use Airygen\Modules\LinkSuggestions\Application\RecommendationService;
use Airygen\Modules\LinkSuggestions\Domain\SimilarityScorer;
use Airygen\Modules\LinkSuggestions\Persistence\LinkTermsRepository;
use AirygenTest\BaseTestCase;
use AirygenTest\Support\DatabaseHelpers;

/**
 * @coversDefaultClass \Airygen\Modules\LinkSuggestions\Application\RecommendationService
 */
class RecommendationServiceTest extends BaseTestCase {

	protected function setUp(): void {
		parent::setUp();
		DatabaseHelpers::truncate_custom_tables();
	}

	/**
	 * @covers ::recommend
	 */
	public function test_recommend_returns_scores_for_candidates(): void {
		$repo    = new LinkTermsRepository();
		$scorer  = new SimilarityScorer();
		$service = new RecommendationService( $repo, $scorer );

		// Seed current content.
		$repo->save_terms(
			1,
			'post',
			array(
				'run' => 2.0,
				'seo' => 1.0,
			)
		);

		// Seed candidates.
		$repo->save_terms(
			2,
			'post',
			array(
				'run' => 2.0,
			)
		);

		$repo->save_terms(
			3,
			'post',
			array(
				'run' => 2.0,
				'seo' => 1.0,
			)
		);

		$scores = $service->recommend(
			1,
			'post',
			array(
				'run' => 2.0,
				'seo' => 1.0,
			),
			array( 2, 3 ),
			'post'
		);

		$this->assertArrayHasKey( 2, $scores );
		$this->assertArrayHasKey( 3, $scores );
		$this->assertGreaterThan( $scores[2], $scores[3] );
	}
}
