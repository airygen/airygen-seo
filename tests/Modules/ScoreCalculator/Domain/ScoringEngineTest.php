<?php
/**
 * Tests for the scoring engine aggregator.
 *
 * @package AirygenTest\Modules\ScoreCalculator\Domain
 */

declare(strict_types=1);

namespace AirygenTest\Modules\ScoreCalculator\Domain;

use Airygen\Modules\ScoreCalculator\Domain\ScoringEngine;
use AirygenTest\Support\ScoreContextFactory;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Airygen\Modules\ScoreCalculator\Domain\ScoringEngine
 */
class ScoringEngineTest extends TestCase {

	/**
	 * The engine should aggregate base and bonus packs independently.
	 *
	 * @return void
	 */
	public function test_scores_document_and_combines_packs(): void {
		$spec = array(
			'pack'            => 'test-pack',
			'version'         => '9.9.9',
			'language'        => 'en',
			'base_total_hint' => 10,
			'bonus_max'       => 3,
			'rules'           => array(
				array(
					'id'     => 'title_focus',
					'label'  => 'Title has focus',
					'weight' => 5,
					'type'   => 'boolean',
					'params' => array(
						'field' => 'title_contains_focus',
					),
				),
				array(
					'id'     => 'meta_length',
					'label'  => 'Meta length',
					'weight' => 5,
					'type'   => 'range_between',
					'params' => array(
						'value_field' => 'meta_description_length',
						'min'         => 10,
						'max'         => 400,
					),
				),
			),
			'bonus'           => array(
				array(
					'id'     => 'word_count_bonus',
					'label'  => 'Word count bonus',
					'weight' => 3,
					'type'   => 'min_value',
					'params' => array(
						'value_field' => 'word_count',
						'min'         => 120,
					),
				),
			),
		);

		$engine  = new ScoringEngine( $spec );
		$context = ScoreContextFactory::make(
			array(
				'content' => $this->long_form_content(),
			)
		);

		$result = $engine->score( $context );

		$this->assertSame( 'test-pack', $result['pack'] );
		$this->assertSame( '9.9.9', $result['version'] );
		$this->assertSame( 'en', $result['language'] );
		$this->assertSame( 100, $result['base']['percentage'] );
		$this->assertSame( 10, $result['base']['score'] );
		$this->assertSame( 10, $result['base']['max'] );
		$this->assertSame( 3, $result['bonus']['score'] );
		$this->assertSame( 3, $result['bonus']['max'] );
		$this->assertSame( 103, $result['total']['score'] );
		$this->assertSame( 103, $result['total']['max'] );
	}

	/**
	 * Generate long-form content to ensure high word count.
	 */
	private function long_form_content(): string {
		$paragraph = 'Focus strategy ensures this paragraph contains plenty of valuable words for the test case.';
		$chunks    = array_fill( 0, 20, $paragraph );

		return '<h1>Focus Title Example</h1><p>' . implode( '</p><p>', $chunks ) . '</p>';
	}
}
