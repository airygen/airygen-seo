<?php
/**
 * TF-IDF cosine similarity scorer.
 *
 * @package Airygen\Modules\LinkSuggestions\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkSuggestions\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use function log;
use function sqrt;

/**
 * Computes similarity scores between a request vector and candidates.
 */
class SimilarityScorer {

	/**
	 * Score candidates.
	 *
	 * @param array<string,float>               $request_terms stem => tf
	 * @param array<string,int>                 $df_lookup     stem => doc_count
	 * @param int                               $total_docs    Total documents (for IDF).
	 * @param array<string,array<string,float>> $candidates id => [stem => tf]
	 *
	 * @return array<string,float> id => score (desc sorted)
	 */
	public function score(
		array $request_terms,
		array $df_lookup,
		int $total_docs,
		array $candidates
	): array {
		if ( $total_docs <= 0 ) {
			return array();
		}

		$request_vector = array();
		foreach ( $request_terms as $stem => $tf ) {
			$idf                     = $this->idf( $df_lookup[ $stem ] ?? 0, $total_docs );
			$request_vector[ $stem ] = $tf * $idf;
		}

		$request_length = $this->vector_length( $request_vector );
		if ( 0.0 === $request_length ) {
			return array();
		}

		$scores = array();

		foreach ( $candidates as $id => $terms ) {
			$candidate_vector = array();
			foreach ( $terms as $stem => $tf ) {
				$idf                       = $this->idf( $df_lookup[ $stem ] ?? 0, $total_docs );
				$candidate_vector[ $stem ] = $tf * $idf;
			}

			$candidate_length = $this->vector_length( $candidate_vector );
			if ( 0.0 === $candidate_length ) {
				$scores[ $id ] = 0.0;
				continue;
			}

			$dot = 0.0;
			foreach ( $candidate_vector as $stem => $value ) {
				if ( ! isset( $request_vector[ $stem ] ) ) {
					continue;
				}
				$dot += $value * $request_vector[ $stem ];
			}

			if ( 0.0 === $dot ) {
				$scores[ $id ] = 0.0;
				continue;
			}

			$scores[ $id ] = $dot / ( $request_length * $candidate_length );
		}

		arsort( $scores );

		return $scores;
	}

	/**
	 * Compute IDF with smoothing.
	 *
	 * @param int $doc_count Stem doc_count.
	 * @param int $total_docs Total docs.
	 *
	 * @return float
	 */
	private function idf( int $doc_count, int $total_docs ): float {
		return (float) log( ( $total_docs + 1 ) / ( $doc_count + 1 ) );
	}

	/**
	 * Vector length.
	 *
	 * @param array<string,float> $vector Vector.
	 *
	 * @return float
	 */
	private function vector_length( array $vector ): float {
		$sum = 0.0;
		foreach ( $vector as $value ) {
			$sum += $value * $value;
		}

		return (float) sqrt( $sum );
	}
}
