<?php
/**
 * Tests for the title pixel estimator helper.
 *
 * @package AirygenTest\Modules\ScoreCalculator\Domain
 */

declare(strict_types=1);

namespace AirygenTest\Modules\ScoreCalculator\Domain;

use Airygen\Modules\ScoreCalculator\Domain\TitlePixelEstimator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Airygen\Modules\ScoreCalculator\Domain\TitlePixelEstimator
 */
class TitlePixelEstimatorTest extends TestCase {

	/**
	 * Known characters should use the width chart.
	 *
	 * @return void
	 */
	public function test_estimates_known_characters(): void {
		$this->assertSame( 25, TitlePixelEstimator::estimate( 'abc' ) );
		$this->assertSame( 0, TitlePixelEstimator::estimate( '' ) );
	}

	/**
	 * Unknown characters should use the default width.
	 *
	 * @return void
	 */
	public function test_falls_back_to_default_width(): void {
		$this->assertSame( 9, TitlePixelEstimator::estimate( '🚀' ) );
	}
}
