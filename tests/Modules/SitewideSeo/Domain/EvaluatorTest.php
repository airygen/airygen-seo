<?php
/**
 * Tests for the SitewideSeo evaluator.
 *
 * @package AirygenTest\Modules\SitewideSeo\Domain
 */

declare(strict_types=1);

namespace AirygenTest\Modules\SitewideSeo\Domain;

use Airygen\Modules\SitewideSeo\Domain\Service\Evaluator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Airygen\Modules\SitewideSeo\Domain\Service\Evaluator
 */
class EvaluatorTest extends TestCase {

	public function test_core_sitemap_reachable(): void {
		$result = Evaluator::core_sitemap(
			array(
				'sitemap_enabled' => true,
				'http_status'     => 200,
				'error'           => null,
			)
		);

		$this->assertSame( Evaluator::STATUS_GOOD, $result['status'] );
		$this->assertSame( 'reachable', $result['code'] );
	}

	public function test_score_rest_missing_route(): void {
		$result = Evaluator::score_rest(
			array(
				'route_registered' => false,
			)
		);

		$this->assertSame( Evaluator::STATUS_CRITICAL, $result['status'] );
		$this->assertSame( 'missing_route', $result['code'] );
	}

	public function test_robots_visibility_non_production(): void {
		$result = Evaluator::robots_visibility(
			array(
				'environment' => 'staging',
				'blog_public' => false,
			)
		);

		$this->assertSame( Evaluator::STATUS_GOOD, $result['status'] );
		$this->assertSame( 'non_production', $result['code'] );
	}

	public function test_permalink_structure_plain(): void {
		$result = Evaluator::permalink_structure(
			array(
				'structure' => '',
			)
		);

		$this->assertSame( Evaluator::STATUS_CRITICAL, $result['status'] );
		$this->assertSame( 'plain', $result['code'] );
	}

	public function test_ssl_status_warning(): void {
		$result = Evaluator::ssl_status(
			array(
				'status'  => 'warning',
				'message' => 'Expiring soon',
			)
		);

		$this->assertSame( Evaluator::STATUS_RECOMMENDED, $result['status'] );
		$this->assertSame( 'warning', $result['code'] );
	}

	public function test_search_console_not_configured(): void {
		$result = Evaluator::search_console(
			array(
				'linked' => false,
			)
		);

		$this->assertSame( Evaluator::STATUS_RECOMMENDED, $result['status'] );
		$this->assertSame( 'not_configured', $result['code'] );
	}
}
