<?php
/**
 * Service to compute related post suggestions from stored keyphrases.
 *
 * @package Airygen\Modules\LinkSuggestions\Application
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkSuggestions\Application;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\LinkSuggestions\Domain\SimilarityScorer;
use Airygen\Modules\LinkSuggestions\Persistence\LinkTermsRepository;

/**
 * Builds a scored list of related content IDs.
 */
class RecommendationService {

	/** @var LinkTermsRepository */
	private $repository;

	/** @var SimilarityScorer */
	private $scorer;

	/**
	 * @param LinkTermsRepository $repository Term storage.
	 * @param SimilarityScorer    $scorer     Similarity calculator.
	 */
	public function __construct( LinkTermsRepository $repository, SimilarityScorer $scorer ) {
		$this->repository = $repository;
		$this->scorer     = $scorer;
	}

	/**
	 * Compute related content scores.
	 *
	 * @param int                 $content_id   Current content ID.
	 * @param string              $content_type Current content type.
	 * @param array<string,float> $request_terms Current content stem => tf.
	 * @param array<int,string>   $candidate_ids Optional candidate IDs to restrict to.
	 * @param string              $candidate_type Candidate content type (e.g. post).
	 * @param array<int,int>      $exclude_ids Optional IDs to exclude (self or already linked).
	 *
	 * @return array<int,float> content_id => score (desc)
	 */
	public function recommend(
		int $content_id,
		string $content_type,
		array $request_terms,
		array $candidate_ids,
		string $candidate_type,
		array $exclude_ids = array()
	): array {
		if ( empty( $request_terms ) || empty( $candidate_ids ) ) {
			return array();
		}

		$total_docs = $this->repository->total_documents();
		if ( $total_docs <= 0 ) {
			return array();
		}

		// Load candidate terms.
		$candidates = array();
		foreach ( $candidate_ids as $id ) {
			if ( $id === $content_id || in_array( $id, $exclude_ids, true ) ) {
				continue;
			}
			$terms = $this->repository->get_terms_for_content( (int) $id, $candidate_type );
			if ( empty( $terms ) ) {
				continue;
			}
			$candidates[ (int) $id ] = $terms;
		}

		if ( empty( $candidates ) ) {
			return array();
		}

		// Merge stems from request and candidates to pull df.
		$stems = array_keys( $request_terms );
		foreach ( $candidates as $terms ) {
			$stems = array_merge( $stems, array_keys( $terms ) );
		}
		$stems = array_values( array_unique( $stems ) );

		$df = $this->repository->get_df_for_stems( $stems );

		$scores = $this->scorer->score(
			$request_terms,
			$df,
			$total_docs,
			$candidates
		);

		return array_map(
			static function ( $score ) {
				return (float) $score;
			},
			$scores
		);
	}
}
