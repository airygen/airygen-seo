<?php
/**
 * Aggregates rule evaluations into a final score.
 *
 * @package Airygen\Modules\ScoreCalculator\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\ScoreCalculator\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs the scoring algorithm across all rules.
 */
final class ScoringEngine {

	/**
	 * Base rule definitions from the manifest.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $base_rules;

	/**
	 * Bonus rule definitions from the manifest.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $bonus_rules;

	/**
	 * Rule pack identifier.
	 *
	 * @var string
	 */
	private string $pack_name;

	/**
	 * Manifest version label.
	 *
	 * @var string
	 */
	private string $version_label;

	/**
	 * Language code used for rule execution.
	 *
	 * @var string
	 */
	private string $language_code;


	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $spec Rule specification manifest.
	 */
	public function __construct( array $spec ) {
		$this->base_rules    = is_array( $spec['rules'] ?? null ) ? $spec['rules'] : array();
		$this->bonus_rules   = is_array( $spec['bonus'] ?? null ) ? $spec['bonus'] : array();
		$this->pack_name     = (string) ( $spec['pack'] ?? 'airygen-score-pack' );
		$this->version_label = (string) ( $spec['version'] ?? 'unknown' );
		$this->language_code = (string) ( $spec['language'] ?? 'auto' );
	}

	/**
	 * Score the provided document context.
	 *
	 * @param DocumentContext $context Analysed document context.
	 *
	 * @return array<string, mixed>
	 */
	public function score( DocumentContext $context ): array {
		$base_results  = $this->evaluate_rules( $this->base_rules, $context );
		$base_score    = $this->sum_scores( $base_results );
		$base_possible = $this->sum_applicable_weights( $base_results );

		$base_percentage = 0;
		if ( $base_possible > 0 ) {
			$base_percentage = (int) round( ( $base_score / $base_possible ) * 100 );
		}

		$bonus_results = $this->evaluate_rules( $this->bonus_rules, $context );
		$bonus_score   = $this->sum_scores( $bonus_results );
		$bonus_max     = $this->sum_applicable_weights( $bonus_results );

		$total_score = $base_percentage + $bonus_score;
		$total_max   = 100 + $bonus_max;
		if ( $total_score > $total_max ) {
			$total_score = $total_max;
		}

		return array(
			'pack'     => $this->pack_name,
			'version'  => $this->version_label,
			'language' => $this->language_code,
			'base'     => array(
				'score'      => $base_score,
				'max'        => $base_possible,
				'percentage' => $base_percentage,
				'rules'      => $base_results,
			),
			'bonus'    => array(
				'score' => $bonus_score,
				'max'   => $bonus_max,
				'rules' => $bonus_results,
			),
			'total'    => array(
				'score' => $total_score,
				'max'   => $total_max,
			),
		);
	}

	/**
	 * Evaluate a set of rules.
	 *
	 * @param array<int, array<string, mixed>> $rules Rules to evaluate.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	/**
	 * Evaluate a set of rules.
	 *
	 * @param array<int, array<string, mixed>> $rules   Rules to evaluate.
	 * @param DocumentContext                  $context Document context.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function evaluate_rules( array $rules, DocumentContext $context ): array {
		$results = array();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$results[] = RuleRunner::evaluate( $rule, $context );
		}

		return $results;
	}

	/**
	 * Sum score values.
	 *
	 * @param array<int, array<string, mixed>> $results Rule results.
	 */
	private function sum_scores( array $results ) {
		$total = 0.0;
		foreach ( $results as $result ) {
			$total += (float) ( $result['score'] ?? 0 );
		}

		return $this->normalize_score( $total );
	}

	/**
	 * Sum applicable weights (status not na).
	 *
	 * @param array<int, array<string, mixed>> $results Rule results.
	 */
	private function sum_applicable_weights( array $results ): int {
		$total = 0;
		foreach ( $results as $result ) {
			if ( 'na' === ( $result['status'] ?? '' ) ) {
				continue;
			}

			$total += (int) ( $result['weight'] ?? 0 );
		}

		return $total;
	}

	/**
	 * Sum rule weights helper.
	 *
	 * @param array<int, array<string, mixed>> $rules Rules definitions.
	 */
	private function sum_rule_weights( array $rules ): int {
		$total = 0;
		foreach ( $rules as $rule ) {
			$total += (int) ( $rule['weight'] ?? 0 );
		}

		return $total;
	}

	/**
	 * Normalize a numeric score to int when it has no fractional component.
	 *
	 * @param float $score Raw score.
	 *
	 * @return int|float
	 */
	private function normalize_score( float $score ) {
		$int_score = (int) $score;
		if ( (float) $int_score === $score ) {
			return $int_score;
		}

		return $score;
	}
}
