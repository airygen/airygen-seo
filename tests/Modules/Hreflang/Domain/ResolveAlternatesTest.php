<?php
/**
 * Tests for the hreflang resolver.
 *
 * @package AirygenTest\Modules\Hreflang\Domain
 */

declare(strict_types=1);

namespace AirygenTest\Modules\Hreflang\Domain;

use Airygen\Modules\Hreflang\Domain\Service\ResolveAlternates;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Airygen\Modules\Hreflang\Domain\Service\ResolveAlternates
 */
class ResolveAlternatesTest extends TestCase {

	/**
	 * Integrations, manual mappings, and x-default should be merged with deduplication.
	 *
	 * @return void
	 */
	public function test_merges_integrations_manual_map_and_x_default(): void {
		$alternates = ResolveAlternates::for_entry(
			array(
				'integrations'      => array(
					array(
						'resolver' => static function (): array {
							return array(
								array(
									'hreflang' => 'en',
									'url'      => 'https://example.com/en',
								),
							);
						},
					),
				),
				'manual_map'        => array(
					'en' => 'https://example.com/en-gb',
					'es' => 'https://example.com/es',
				),
				'include_x_default' => true,
				'self_url'          => 'https://example.com/current',
			)
		);

		$this->assertCount( 3, $alternates );

		$this->assertSame(
			array(
				array(
					'hreflang' => 'en',
					'url'      => 'https://example.com/en-gb',
				),
				array(
					'hreflang' => 'es',
					'url'      => 'https://example.com/es',
				),
				array(
					'hreflang' => 'x-default',
					'url'      => 'https://example.com/current',
				),
			),
			$alternates
		);
	}

	/**
	 * Invalid codes or URLs should be dropped.
	 *
	 * @return void
	 */
	public function test_ignores_invalid_entries(): void {
		$alternates = ResolveAlternates::for_entry(
			array(
				'manual_map' => array(
					''   => 'https://example.com/blank',
					'de' => '',
					'jp' => '   ',
					'pt' => 'https://example.com/pt',
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'hreflang' => 'pt',
					'url'      => 'https://example.com/pt',
				),
			),
			$alternates
		);
	}
}
