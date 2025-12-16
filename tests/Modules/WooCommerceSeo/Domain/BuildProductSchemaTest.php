<?php
/**
 * Tests for Product schema builder.
 *
 * @package AirygenTest\Modules\WooCommerceSeo\Domain
 */

declare(strict_types=1);

namespace AirygenTest\Modules\WooCommerceSeo\Domain;

use Airygen\Modules\WooCommerceSeo\Domain\BuildProductSchema;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Airygen\Modules\WooCommerceSeo\Domain\BuildProductSchema
 */
final class BuildProductSchemaTest extends TestCase {

	/**
	 * Builds a simple product with Offer.
	 *
	 * @return void
	 */
	public function test_builds_simple_offer_schema(): void {
		$node = BuildProductSchema::build(
			array(
				'name'         => 'Airygen Tee',
				'url'          => 'https://example.com/product/tee',
				'description'  => 'Basic shirt',
				'sku'          => 'TEE-01',
				'brand'        => 'Airygen',
				'currency'     => 'USD',
				'offer_type'   => 'offer',
				'price'        => '39.00',
				'stock_status' => 'instock',
			)
		);

		$this->assertIsArray( $node );
		$this->assertSame( 'Product', $node['@type'] );
		$this->assertSame( 'Airygen Tee', $node['name'] );
		$this->assertSame( 'Offer', $node['offers']['@type'] );
		$this->assertSame( '39.00', $node['offers']['price'] );
	}

	/**
	 * Builds variable product aggregate offer.
	 *
	 * @return void
	 */
	public function test_builds_aggregate_offer_schema(): void {
		$node = BuildProductSchema::build(
			array(
				'name'         => 'Airygen Hoodie',
				'url'          => 'https://example.com/product/hoodie',
				'currency'     => 'USD',
				'offer_type'   => 'aggregate',
				'min_price'    => '49.00',
				'max_price'    => '69.00',
				'offer_count'  => 4,
				'stock_status' => 'onbackorder',
			)
		);

		$this->assertIsArray( $node );
		$this->assertSame( 'AggregateOffer', $node['offers']['@type'] );
		$this->assertSame( '49.00', $node['offers']['lowPrice'] );
		$this->assertSame( '69.00', $node['offers']['highPrice'] );
		$this->assertSame( 'https://schema.org/BackOrder', $node['offers']['availability'] );
	}

	/**
	 * Returns null when required fields are missing.
	 *
	 * @return void
	 */
	public function test_returns_null_without_name_or_url(): void {
		$this->assertNull(
			BuildProductSchema::build(
				array(
					'name' => 'No URL Product',
				)
			)
		);
	}
}
