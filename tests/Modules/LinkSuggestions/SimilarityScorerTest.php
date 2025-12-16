<?php
/**
 * Tests for SimilarityScorer.
 *
 * @package AirygenTest\Modules\LinkSuggestions
 */

declare(strict_types=1);

namespace AirygenTest\Modules\LinkSuggestions;

use Airygen\Modules\LinkSuggestions\Domain\SimilarityScorer;
use AirygenTest\BaseTestCase;

/**
 * @coversDefaultClass \Airygen\Modules\LinkSuggestions\Domain\SimilarityScorer
 */
class SimilarityScorerTest extends BaseTestCase {

	/**
	 * @covers ::score
	 */
	public function test_scores_candidates_by_overlap(): void {
		$scorer = new SimilarityScorer();

		$request = array(
			'run' => 2.0,
			'seo' => 1.0,
		);

		$df         = array(
			'run'   => 1,
			'seo'   => 1,
			'other' => 1,
		);
		$total_docs = 5;

		$candidates = array(
			'a' => array( 'run' => 2.0 ),                // partial
			'b' => array(
				'run' => 2.0,
				'seo' => 1.0,
			),  // best overlap
			'c' => array( 'other' => 5.0 ),              // no overlap
		);

		$scores = $scorer->score( $request, $df, $total_docs, $candidates );

		$this->assertGreaterThan( $scores['a'], $scores['b'] );
		$this->assertSame( 0.0, $scores['c'] );
	}
}
