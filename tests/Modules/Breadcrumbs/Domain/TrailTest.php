<?php
/**
 * Unit tests for the breadcrumb trail DTO.
 *
 * @package AirygenTest\Modules\Breadcrumbs\Domain
 */

declare(strict_types=1);

namespace AirygenTest\Modules\Breadcrumbs\Domain;

use Airygen\Modules\Breadcrumbs\Domain\Trail;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Airygen\Modules\Breadcrumbs\Domain\Trail
 */
final class TrailTest extends TestCase {

	public function test_schema_items_skip_hidden_entries(): void {
		$trail = new Trail(
			array(
				array(
					'label' => 'Home',
					'url'   => 'https://example.com/',
				),
				array(
					'label'          => 'Page',
					'url'            => 'https://example.com/page',
					'hide_in_schema' => true,
				),
			)
		);

		$this->assertFalse( $trail->is_empty() );
		$this->assertCount( 2, $trail->items() );

		$schema = $trail->to_schema_items();
		$this->assertCount( 1, $schema );
		$this->assertSame( 'Home', $schema[0]['name'] );
	}

	public function test_empty_items_result_in_empty_trail(): void {
		$trail = new Trail( array() );
		$this->assertTrue( $trail->is_empty() );
		$this->assertSame( array(), $trail->items() );
	}
}
