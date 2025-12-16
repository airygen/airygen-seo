<?php
/**
 * Tests for the rule runner evaluator.
 *
 * @package AirygenTest\Modules\ScoreCalculator\Domain
 */

declare(strict_types=1);

namespace AirygenTest\Modules\ScoreCalculator\Domain;

use Airygen\Modules\ScoreCalculator\Domain\DocumentContext;
use Airygen\Modules\ScoreCalculator\Domain\RuleRunner;
use AirygenTest\Support\ScoreContextFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Airygen\Modules\ScoreCalculator\Domain\RuleRunner
 */
class RuleRunnerTest extends TestCase {

	/**
	 * Missing required keys should trigger an exception.
	 *
	 * @return void
	 */
	public function test_requires_id_label_and_weight(): void {
		$this->expectException( InvalidArgumentException::class );
		RuleRunner::evaluate( array(), $this->make_context() );
	}

	/**
	 * Boolean rules should reflect the context metric.
	 *
	 * @return void
	 */
	public function test_boolean_rule_passes_when_metric_true(): void {
		$result = RuleRunner::evaluate(
			array(
				'id'     => 'title_contains_focus',
				'label'  => 'Title contains focus',
				'weight' => 5,
				'type'   => 'boolean',
				'params' => array(
					'field' => 'title_contains_focus',
				),
			),
			$this->make_context()
		);

		$this->assertSame( 'pass', $result['status'] );
		$this->assertSame( 5, $result['score'] );
	}

	/**
	 * Rules that require a focus keyphrase should return NA when missing.
	 *
	 * @return void
	 */
	public function test_rules_require_focus_when_flagged(): void {
		$result = RuleRunner::evaluate(
			array(
				'id'             => 'needs_focus',
				'label'          => 'Needs focus',
				'weight'         => 3,
				'type'           => 'boolean',
				'params'         => array(
					'field' => 'title_contains_focus',
				),
				'requires_focus' => true,
			),
			$this->make_context(
				array(
					'focus_keyphrase' => '',
				)
			)
		);

		$this->assertSame( 'fail', $result['status'] );
		$this->assertSame( 0, $result['score'] );
	}

	/**
	 * Range rules should apply min/max comparisons.
	 *
	 * @return void
	 */
	public function test_range_rule_bounds(): void {
		$result = RuleRunner::evaluate(
			array(
				'id'     => 'meta_length',
				'label'  => 'Meta description length',
				'weight' => 2,
				'type'   => 'range_between',
				'params' => array(
					'value_field' => 'meta_description_length',
					'min'         => 10,
					'max'         => 500,
				),
			),
			$this->make_context()
		);

		$this->assertSame( 'pass', $result['status'] );
		$this->assertSame( 2, $result['score'] );
	}

	/**
	 * Count rules without bounds should return NA.
	 *
	 * @return void
	 */
	public function test_count_rule_without_bounds_returns_na(): void {
		$result = RuleRunner::evaluate(
			array(
				'id'     => 'count_between',
				'label'  => 'Count between',
				'weight' => 4,
				'type'   => 'count_between',
				'params' => array(
					'value_field' => 'internal_links',
				),
			),
			$this->make_context()
		);

		$this->assertSame( 'na', $result['status'] );
	}

	/**
	 * Percent rules targeting H2/H3 usage should return NA when no subheads exist.
	 *
	 * @return void
	 */
	public function test_percent_rule_without_subheads_returns_na(): void {
		$result = RuleRunner::evaluate(
			array(
				'id'     => 'subhead_percent',
				'label'  => 'Subhead percent',
				'weight' => 4,
				'type'   => 'percent_between',
				'params' => array(
					'value_field' => 'subheads_focus_percent',
					'min'         => 30,
					'max'         => 70,
				),
			),
			$this->make_context(
				array(
					'content' => '<h1>Focus Title</h1><p>Focus intro without subheads.</p>',
				)
			)
		);

		$this->assertSame( 'na', $result['status'] );
	}

	/**
	 * Min value rules should pass when the threshold is met.
	 *
	 * @return void
	 */
	public function test_min_value_rule_passes_when_threshold_met(): void {
		$result = RuleRunner::evaluate(
			array(
				'id'     => 'word_count',
				'label'  => 'Word count minimum',
				'weight' => 4,
				'type'   => 'min_value',
				'params' => array(
					'value_field' => 'word_count',
					'min'         => 100,
				),
			),
			$this->make_context(
				array(
					'content' => $this->repeated_content( 200, 5 ),
				)
			)
		);

		$this->assertSame( 'pass', $result['status'] );
		$this->assertSame( 4, $result['score'] );
	}

	/**
	 * Keyword density rules should enter warn range when within soft bounds.
	 *
	 * @return void
	 */
	public function test_keyword_density_soft_range_triggers_warn(): void {
		$focus_phrase = 'unobtrusive';
		$context      = $this->make_context(
			array(
				'focus_keyphrase' => $focus_phrase,
				'content'         => $this->density_content( 250, 1, $focus_phrase ),
			)
		);

		$density = $context->get_keyword_density();
		$this->assertGreaterThan( 0.3, $density, 'Density should exceed soft_min' );
		$this->assertLessThan( 0.5, $density, 'Density should be below ideal_min' );

		$result = RuleRunner::evaluate(
			array(
				'id'             => 'density',
				'label'          => 'Keyword density',
				'weight'         => 8,
				'type'           => 'keyword_density',
				'params'         => array(
					'ideal_min' => 0.5,
					'ideal_max' => 2.0,
					'soft_min'  => 0.3,
					'soft_max'  => 2.5,
				),
				'requires_focus' => true,
			),
			$context
		);

		$this->assertSame( 'warn', $result['status'] );
		$this->assertSame( 4, $result['score'] );
	}

	/**
	 * Flesch rules should be NA when the sample is too short.
	 *
	 * @return void
	 */
	public function test_flesch_rule_returns_na_for_short_posts(): void {
		$result = RuleRunner::evaluate(
			array(
				'id'     => 'flesch',
				'label'  => 'Flesch score',
				'weight' => 6,
				'type'   => 'flesch_reading_ease',
				'params' => array(
					'min' => 60,
				),
			),
			$this->make_context(
				array(
					'content' => '<h1>Focus</h1><p>Short focus sentence.</p>',
				)
			)
		);

		$this->assertSame( 'na', $result['status'] );
	}

	/**
	 * H1 rules should enforce a single heading.
	 *
	 * @return void
	 */
	public function test_h1_rule_requires_single_heading(): void {
		$result = RuleRunner::evaluate(
			array(
				'id'     => 'h1',
				'label'  => 'H1 check',
				'weight' => 4,
				'type'   => 'h1_one',
			),
			$this->make_context()
		);

		$this->assertSame( 'pass', $result['status'] );
	}

	/**
	 * Title length rules should consider both characters and pixels.
	 *
	 * @return void
	 */
	public function test_title_length_rule_can_fail_on_min_chars(): void {
		$result = RuleRunner::evaluate(
			array(
				'id'     => 'title_length',
				'label'  => 'Title length',
				'weight' => 5,
				'type'   => 'title_length_px',
				'params' => array(
					'min_chars' => 80,
					'max_px'    => 1000,
				),
			),
			$this->make_context()
		);

		$this->assertSame( 'fail', $result['status'] );
		$this->assertSame( 0, $result['score'] );
	}

	/**
	 * Count bracket rules should downgrade to warn when falling into lower ratios.
	 *
	 * @return void
	 */
	public function test_count_bracket_rule_warns_when_ratio_less_than_one(): void {
		$result = RuleRunner::evaluate(
			array(
				'id'     => 'internal_links',
				'label'  => 'Internal links',
				'weight' => 6,
				'type'   => 'count_bracket_score',
				'params' => array(
					'value_field' => 'internal_links',
					'brackets'    => array(
						array(
							'min'   => 1,
							'max'   => 3,
							'score' => 1.0,
						),
						array(
							'min'   => 4,
							'max'   => 999,
							'score' => 0.67,
						),
					),
					'else'        => 0,
				),
			),
			$this->make_context(
				array(
					'content' => $this->link_rich_content( 4, 0 ),
				)
			)
		);

		$this->assertSame( 'warn', $result['status'] );
		$this->assertSame( 4, $result['score'] );
	}

	/**
	 * Helper for instantiating a context.
	 *
	 * @param array<string,mixed> $overrides Payload overrides.
	 */
	private function make_context( array $overrides = array() ): DocumentContext {
		return ScoreContextFactory::make( $overrides );
	}

	/**
	 * Build repeated paragraph content for density and word count scenarios.
	 *
	 * @param int $word_count   Total word count.
	 * @param int $focus_count  Number of focus occurrences.
	 */
	private function repeated_content( int $word_count, int $focus_count ): string {
		$word_count  = max( $word_count, $focus_count );
		$words       = array();
		$focus_added = 0;

		while ( $focus_added < $focus_count ) {
			$words[] = 'Focus';
			++$focus_added;
		}

		for ( $i = count( $words ); $i < $word_count; ++$i ) {
			$words[] = 'word' . $i;
		}

		$html = '<h1>Focus Title Example</h1>';
		foreach ( array_chunk( $words, 40 ) as $chunk ) {
			$html .= '<p>' . implode( ' ', $chunk ) . '.</p>';
		}

		return $html;
	}

	/**
	 * Build link-heavy content for internal link tests.
	 *
	 * @param int $internal Number of internal links.
	 * @param int $external Number of external links.
	 */
	private function link_rich_content( int $internal, int $external ): string {
		$html = '<h1>Focus Title</h1><p>Focus keeps context alive.</p>';

		for ( $i = 0; $i < $external; ++$i ) {
			$html .= sprintf( '<a href="https://example%d.com/item-%d" rel="noopener">External %d</a>', $i, $i, $i );
		}

		for ( $i = 0; $i < $internal; ++$i ) {
			$html .= sprintf( '<a href="/internal-%d">Internal %d</a>', $i, $i );
		}

		return $html;
	}

	/**
	 * Build content where keyword density can be controlled precisely.
	 *
	 * @param int    $word_count     Total number of words.
	 * @param int    $focus_count    Number of times the focus phrase should appear.
	 * @param string $focus_phrase   The focus keyphrase.
	 */
	private function density_content( int $word_count, int $focus_count, string $focus_phrase ): string {
		$words = array();

		for ( $i = 0; $i < $focus_count; ++$i ) {
			$words[] = $focus_phrase;
		}

		for ( $i = count( $words ); $i < $word_count; ++$i ) {
			$words[] = 'word' . $i;
		}

		return '<h1>Example Heading</h1><p>' . implode( ' ', $words ) . '</p>';
	}
}
