<?php
/**
 * Evaluates individual scoring rules against a document context.
 *
 * @package Airygen\Modules\ScoreCalculator\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\ScoreCalculator\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InvalidArgumentException;

/**
 * Applies rule logic for scoring.
 */
final class RuleRunner {

	/**
	 * Evaluate a rule against the provided document context.
	 *
	 * @param array<string, mixed> $rule    Rule definition.
	 * @param DocumentContext      $context Document context.
	 */
	public static function evaluate( array $rule, DocumentContext $context ): array {
		self::assert_rule( $rule );

		$weight         = (int) ( $rule['weight'] ?? 0 );
		$type           = (string) ( $rule['type'] ?? 'boolean' );
		$params         = is_array( $rule['params'] ?? null ) ? $rule['params'] : array();
		$requires_focus = (bool) ( $rule['requires_focus'] ?? false );

		if ( $requires_focus && ! $context->has_focus_keyphrase() ) {
			return self::format_result( $rule, 0, $weight, 'fail', null );
		}

		switch ( $type ) {
			case 'boolean':
				return self::evaluate_boolean( $rule, $context, $params );
			case 'range_between':
				return self::evaluate_range_between( $rule, $context, $params );
			case 'count_between':
				return self::evaluate_count_between( $rule, $context, $params );
			case 'percent_between':
				return self::evaluate_percent_between( $rule, $context, $params );
			case 'min_value':
				return self::evaluate_min_value( $rule, $context, $params );
			case 'keyword_density':
				return self::evaluate_keyword_density( $rule, $context, $params );
			case 'long_tail_density':
				return self::evaluate_long_tail_density( $rule, $context, $params );
			case 'flesch_reading_ease':
				return self::evaluate_flesch_reading_ease( $rule, $context, $params );
			case 'avg_sentence_chars_cjk':
				return self::evaluate_avg_sentence_chars_cjk( $rule, $context, $params );
			case 'h1_one':
				return self::evaluate_h1_one( $rule, $context );
			case 'title_length_px':
				return self::evaluate_title_length_px( $rule, $context, $params );
			case 'count_bracket_score':
				return self::evaluate_count_bracket_score( $rule, $context, $params );
			case 'scaled_score':
				return self::evaluate_scaled_score( $rule, $context, $params );
			default:
				return self::format_result( $rule, 0, $weight, 'na', null );
		}
	}

	/**
	 * Ensure required rule fields are present.
	 *
	 * @param array<string, mixed> $rule Rule definition.
	 * @throws InvalidArgumentException If required fields are missing.
	 */
	private static function assert_rule( array $rule ): void {
		foreach ( array( 'id', 'label', 'weight' ) as $field ) {
			if ( ! array_key_exists( $field, $rule ) ) {
				throw new InvalidArgumentException( 'Rule configuration missing required key.' );
			}
		}
	}

	/**
	 * Evaluate simple boolean rule.
	 *
	 * @param array<string, mixed> $rule    Rule definition.
	 * @param DocumentContext      $context Document context.
	 *
	 * @param array<string, mixed> $params  Rule parameters.
	 */
	private static function evaluate_boolean( array $rule, DocumentContext $context, array $params ): array {
		$field = (string) ( $params['field'] ?? '' );
		if ( '' === $field ) {
			return self::format_result( $rule, 0, (int) $rule['weight'], 'na', null );
		}

		if ( 'subheads_focus_any' === $field && ! $context->has_focus_in_subheads_context() ) {
			return self::format_result( $rule, 0, (int) $rule['weight'], 'na', null );
		}

		if ( 'long_tail_spacing_ok' === $field && ! $context->has_long_tail_keyphrases() ) {
			return self::format_result( $rule, 0, (int) $rule['weight'], 'na', null );
		}

		$pass = false;
		if ( 'long_tail_spacing_ok' === $field ) {
			$min_distance = isset( $params['min_distance'] ) ? (int) $params['min_distance'] : 50;
			$pass         = $context->is_long_tail_spacing_ok_with_distance( $min_distance );
		} else {
			$pass = $context->get_boolean_metric( $field );
		}
		$weight = (int) $rule['weight'];
		$status = $pass ? 'pass' : 'fail';
		$score  = $pass ? $weight : 0;
		$value  = $pass;

		return self::format_result( $rule, $score, $weight, $status, $value );
	}

	/**
	 * Evaluate range rule.
	 *
	 * @param array<string, mixed> $rule    Rule definition.
	 * @param DocumentContext      $context Document context.
	 * @param array<string, mixed> $params  Rule parameters.
	 *
	 * @return array<string, mixed> Evaluation result.
	 */
	private static function evaluate_range_between( array $rule, DocumentContext $context, array $params ): array {
		$field = (string) ( $params['value_field'] ?? '' );
		if ( '' === $field ) {
			return self::format_result( $rule, 0, (int) $rule['weight'], 'na', null );
		}

		$value  = $context->get_numeric_metric( $field );
		$min    = isset( $params['min'] ) ? (float) $params['min'] : null;
		$max    = isset( $params['max'] ) ? (float) $params['max'] : null;
		$weight = (int) $rule['weight'];

		$pass = true;
		if ( null !== $min && $value < $min ) {
			$pass = false;
		}

		if ( null !== $max && $value > $max ) {
			$pass = false;
		}

		$status = $pass ? 'pass' : 'fail';
		$score  = $pass ? $weight : 0;

		return self::format_result( $rule, $score, $weight, $status, $value );
	}

	/**
	 * Evaluate count between rule.
	 *
	 * @param array<string, mixed> $rule    Rule definition.
	 * @param DocumentContext      $context Document context.
	 * @param array<string, mixed> $params  Rule parameters.
	 *
	 * @return array<string, mixed> Evaluation result.
	 */
	private static function evaluate_count_between( array $rule, DocumentContext $context, array $params ): array {
		$field = (string) ( $params['value_field'] ?? '' );
		if ( '' === $field ) {
			return self::format_result( $rule, 0, (int) $rule['weight'], 'na', null );
		}

		$value  = $context->get_numeric_metric( $field );
		$min    = isset( $params['min'] ) ? (float) $params['min'] : null;
		$max    = isset( $params['max'] ) ? (float) $params['max'] : null;
		$weight = (int) $rule['weight'];

		if ( null === $min && null === $max ) {
			return self::format_result( $rule, 0, $weight, 'na', null );
		}

		$pass = true;
		if ( null !== $min && $value < $min ) {
			$pass = false;
		}

		if ( null !== $max && $value > $max ) {
			$pass = false;
		}

		$status = $pass ? 'pass' : 'fail';
		$score  = $pass ? $weight : 0;

		return self::format_result( $rule, $score, $weight, $status, $value );
	}

	/**
	 * Evaluate percent between rule.
	 *
	 * @param array<string, mixed> $rule    Rule definition.
	 * @param DocumentContext      $context Document context.
	 * @param array<string, mixed> $params  Rule parameters.
	 *
	 * @return array<string, mixed> Evaluation result.
	 */
	private static function evaluate_percent_between( array $rule, DocumentContext $context, array $params ): array {
		$field = (string) ( $params['value_field'] ?? '' );
		if ( '' === $field ) {
			return self::format_result( $rule, 0, (int) $rule['weight'], 'na', null );
		}

		if ( 'subheads_focus_percent' === $field && ! $context->has_focus_in_subheads_context() ) {
			return self::format_result( $rule, 0, (int) $rule['weight'], 'na', null );
		}

		$value  = $context->get_numeric_metric( $field );
		$min    = isset( $params['min'] ) ? (float) $params['min'] : null;
		$max    = isset( $params['max'] ) ? (float) $params['max'] : null;
		$weight = (int) $rule['weight'];

		$pass = true;
		if ( null !== $min && $value < $min ) {
			$pass = false;
		}

		if ( null !== $max && $value > $max ) {
			$pass = false;
		}

		$status = $pass ? 'pass' : 'fail';
		$score  = $pass ? $weight : 0;

		return self::format_result( $rule, $score, $weight, $status, $value );
	}

	/**
	 * Evaluate minimum value rule.
	 *
	 * @param array<string, mixed> $rule    Rule definition.
	 * @param DocumentContext      $context Document context.
	 * @param array<string, mixed> $params  Rule parameters.
	 *
	 * @return array<string, mixed> Evaluation result.
	 */
	private static function evaluate_min_value( array $rule, DocumentContext $context, array $params ): array {
		$field = (string) ( $params['value_field'] ?? '' );
		if ( '' === $field ) {
			return self::format_result( $rule, 0, (int) $rule['weight'], 'na', null );
		}

		$value  = $context->get_numeric_metric( $field );
		$min    = isset( $params['min'] ) ? (float) $params['min'] : 0.0;
		$weight = (int) $rule['weight'];
		$pass   = $value >= $min;
		$status = $pass ? 'pass' : 'fail';
		$score  = $pass ? $weight : 0;

		return self::format_result( $rule, $score, $weight, $status, $value );
	}

	/**
	 * Evaluate keyword density rule with soft ranges.
	 *
	 * @param array<string, mixed> $rule    Rule definition.
	 * @param DocumentContext      $context Document context.
	 * @param array<string, mixed> $params  Rule parameters.
	 *
	 * @return array<string, mixed> Evaluation result.
	 */
	private static function evaluate_keyword_density( array $rule, DocumentContext $context, array $params ): array {
		$value     = $context->get_numeric_metric( (string) ( $params['value_field'] ?? 'keyword_density' ) );
		$weight    = (int) $rule['weight'];
		$ideal_min = isset( $params['ideal_min'] ) ? (float) $params['ideal_min'] : 0.0;
		$ideal_max = isset( $params['ideal_max'] ) ? (float) $params['ideal_max'] : 0.0;
		$soft_min  = isset( $params['soft_min'] ) ? (float) $params['soft_min'] : $ideal_min;
		$soft_max  = isset( $params['soft_max'] ) ? (float) $params['soft_max'] : $ideal_max;

		if ( $ideal_min <= $value && $value <= $ideal_max ) {
			return self::format_result( $rule, $weight, $weight, 'pass', $value );
		}

		if ( $soft_min <= $value && $value <= $soft_max ) {
			$score = (int) round( $weight * 0.5 );
			return self::format_result( $rule, $score, $weight, 'warn', $value );
		}

		return self::format_result( $rule, 0, $weight, 'fail', $value );
	}

	/**
	 * Evaluate long-tail keyword density rule.
	 *
	 * @param array<string, mixed> $rule    Rule definition.
	 * @param DocumentContext      $context Document context.
	 * @param array<string, mixed> $params  Rule parameters.
	 *
	 * @return array<string, mixed> Evaluation result.
	 */
	private static function evaluate_long_tail_density( array $rule, DocumentContext $context, array $params ): array {
		$weight = (int) $rule['weight'];

		if ( ! $context->has_long_tail_keyphrases() ) {
			return self::format_result( $rule, 0, $weight, 'na', null );
		}

		$min     = isset( $params['min'] ) ? (float) $params['min'] : 0.0;
		$max     = isset( $params['max'] ) ? (float) $params['max'] : 0.0;
		$sum_max = isset( $params['sum_max'] ) ? (float) $params['sum_max'] : 0.0;

		$max_density = $context->get_numeric_metric( 'long_tail_density_max' );
		$sum_density = $context->get_numeric_metric( 'long_tail_density_sum' );

		if ( $sum_max > 0 && $sum_density > $sum_max ) {
			return self::format_result( $rule, 0, $weight, 'fail', $sum_density );
		}

		if ( $max < $min ) {
			return self::format_result( $rule, 0, $weight, 'na', null );
		}

		if ( $max_density >= $min && $max_density <= $max ) {
			return self::format_result( $rule, $weight, $weight, 'pass', $max_density );
		}

		if ( $max_density > 0 && $max_density < $min ) {
			$score = (int) round( $weight * 0.5 );
			return self::format_result( $rule, $score, $weight, 'warn', $max_density );
		}

		return self::format_result( $rule, 0, $weight, 'fail', $max_density );
	}

	/**
	 * Evaluate Flesch reading ease rule.
	 *
	 * @param array<string, mixed> $rule    Rule definition.
	 * @param DocumentContext      $context Document context.
	 * @param array<string, mixed> $params  Rule parameters.
	 *
	 * @return array<string, mixed> Evaluation result.
	 */
	private static function evaluate_flesch_reading_ease( array $rule, DocumentContext $context, array $params ): array {
		$min    = isset( $params['min'] ) ? (float) $params['min'] : 0.0;
		$value  = $context->get_numeric_metric( 'flesch_reading_ease' );
		$weight = (int) $rule['weight'];

		if ( $context->is_cjk_language() ) {
			return self::format_result( $rule, 0, $weight, 'na', null );
		}

		if ( $context->get_word_count() < 50 ) {
			return self::format_result( $rule, 0, $weight, 'na', null );
		}

		$pass   = $value >= $min;
		$status = $pass ? 'pass' : 'fail';
		$score  = $pass ? $weight : 0;

		return self::format_result( $rule, $score, $weight, $status, $value );
	}

	/**
	 * Evaluate average sentence length rule for CJK content.
	 *
	 * @param array<string, mixed> $rule    Rule definition.
	 * @param DocumentContext      $context Document context.
	 * @param array<string, mixed> $params  Rule parameters.
	 *
	 * @return array<string, mixed> Evaluation result.
	 */
	private static function evaluate_avg_sentence_chars_cjk( array $rule, DocumentContext $context, array $params ): array {
		$max           = isset( $params['max'] ) ? (float) $params['max'] : 0.0;
		$min_sentences = isset( $params['min_sentences'] ) ? (int) $params['min_sentences'] : 0;
		$value         = $context->get_numeric_metric( 'avg_sentence_chars' );
		$weight        = (int) $rule['weight'];

		if ( ! $context->is_cjk_language() ) {
			return self::format_result( $rule, 0, $weight, 'na', null );
		}

		if ( $context->get_sentence_count() < $min_sentences ) {
			return self::format_result( $rule, 0, $weight, 'na', null );
		}

		$pass   = $value <= $max;
		$status = $pass ? 'pass' : 'fail';
		$score  = $pass ? $weight : 0;

		return self::format_result( $rule, $score, $weight, $status, $value );
	}

	/**
	 * Evaluate H1 rule.
	 *
	 * @param array<string, mixed> $rule    Rule definition.
	 * @param DocumentContext      $context Document context.
	 *
	 * @return array<string, mixed> Evaluation result.
	 */
	private static function evaluate_h1_one( array $rule, DocumentContext $context ): array {
		$count  = $context->get_h1_count();
		$weight = (int) $rule['weight'];
		$pass   = 0 === $count;
		$status = $pass ? 'pass' : 'fail';
		$score  = $pass ? $weight : 0;

		return self::format_result( $rule, $score, $weight, $status, $count );
	}

	/**
	 * Evaluate title length (pixels + min characters).
	 *
	 * @param array<string, mixed> $rule    Rule definition.
	 * @param DocumentContext      $context Document context.
	 *
	 * @param array<string, mixed> $params  Rule parameters.
	 */
	private static function evaluate_title_length_px( array $rule, DocumentContext $context, array $params ): array {
		$max_px_param = $params['max_px'] ?? $params['px_max'] ?? 0.0;
		$max_px       = is_numeric( $max_px_param ) ? (float) $max_px_param : 0.0;
		$min_px_param = $params['min_px'] ?? $params['px_min'] ?? 0.0;
		$min_px       = is_numeric( $min_px_param ) ? (float) $min_px_param : 0.0;
		$min_chars    = isset( $params['min_chars'] ) ? (int) $params['min_chars'] : 0;
		$weight       = (int) $rule['weight'];
		$value_px     = $context->get_title_length_px();
		$title_length = $context->get_title_length_chars();

		$pass = true;
		if ( $min_px > 0 && $value_px < $min_px ) {
			$pass = false;
		}

		if ( $max_px > 0 && $value_px > $max_px ) {
			$pass = false;
		}

		if ( $min_chars > 0 && $title_length < $min_chars ) {
			$pass = false;
		}

		$status = $pass ? 'pass' : 'fail';
		$score  = $pass ? $weight : 0;

		return self::format_result( $rule, $score, $weight, $status, $value_px );
	}

	/**
	 * Evaluate count bracket score rule.
	 *
	 * @param array<string, mixed> $rule    Rule definition.
	 * @param DocumentContext      $context Document context.
	 * @param array<string, mixed> $params  Rule parameters.
	 *
	 * @return array<string, mixed> Evaluation result.
	 */
	private static function evaluate_count_bracket_score( array $rule, DocumentContext $context, array $params ): array {
		$field = (string) ( $params['value_field'] ?? '' );
		if ( '' === $field ) {
			return self::format_result( $rule, 0, (int) $rule['weight'], 'na', null );
		}

		$value      = $context->get_numeric_metric( $field );
		$weight     = (int) $rule['weight'];
		$brackets   = is_array( $params['brackets'] ?? null ) ? $params['brackets'] : array();
		$else_score = isset( $params['else'] ) ? (float) $params['else'] : 0.0;

		$ratio = $else_score;
		foreach ( $brackets as $bracket ) {
			if ( ! is_array( $bracket ) ) {
				continue;
			}

			$min = isset( $bracket['min'] ) ? (float) $bracket['min'] : null;
			$max = isset( $bracket['max'] ) ? (float) $bracket['max'] : null;

			if ( ( null === $min || $value >= $min ) && ( null === $max || $value <= $max ) ) {
				$ratio = isset( $bracket['score'] ) ? (float) $bracket['score'] : 0.0;
				break;
			}
		}

		$ratio  = max( 0.0, min( 1.0, $ratio ) );
		$score  = (int) round( $weight * $ratio );
		$status = 'fail';
		if ( $ratio >= 1.0 ) {
			$status = 'pass';
		} elseif ( $ratio > 0.0 ) {
			$status = 'warn';
		}

		return self::format_result( $rule, $score, $weight, $status, $value );
	}

	/**
	 * Format a rule evaluation result structure.
	 *
	 * @param array<string, mixed> $rule   Rule definition.
	 * @param float                $score  Score achieved.
	 * @param int                  $weight Max score for rule.
	 * @param string               $status pass|fail|na|warn.
	 * @param mixed                $value  Raw value observed.
	 */
	private static function format_result( array $rule, float $score, int $weight, string $status, $value ): array {
		$normalized_score = self::normalize_score( $score );

		return array(
			'id'     => (string) $rule['id'],
			'label'  => (string) $rule['label'],
			'weight' => $weight,
			'score'  => $normalized_score,
			'status' => $status,
			'value'  => $value,
		);
	}

	/**
	 * Cast score to int when it represents a whole number to satisfy strict comparisons.
	 *
	 * @param float $score Raw score value.
	 *
	 * @return int|float
	 */
	private static function normalize_score( float $score ) {
		$int_score = (int) $score;
		if ( (float) $int_score === $score ) {
			return $int_score;
		}

		return $score;
	}

	/**
	 * Evaluate a scaled score rule that multiplies a ratio by the weight.
	 *
	 * @param array<string, mixed> $rule    Rule definition.
	 * @param DocumentContext      $context Document context.
	 * @param array<string, mixed> $params  Rule parameters.
	 */
	private static function evaluate_scaled_score( array $rule, DocumentContext $context, array $params ): array {
		$field = (string) ( $params['value_field'] ?? '' );
		if ( '' === $field ) {
			return self::format_result( $rule, 0, (int) $rule['weight'], 'na', null );
		}

		$value  = $context->get_numeric_metric( $field );
		$weight = (int) $rule['weight'];
		$ratio  = max( 0.0, min( 1.0, $value ) );

		$score  = $weight * $ratio;
		$status = 'fail';
		if ( $ratio >= 1.0 ) {
			$status = 'pass';
		} elseif ( $ratio > 0.0 ) {
			$status = 'warn';
		}

		return self::format_result( $rule, $score, $weight, $status, $ratio );
	}
}
