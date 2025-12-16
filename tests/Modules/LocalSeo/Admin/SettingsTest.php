<?php
/**
 * Tests for Local SEO settings storage.
 *
 * @package AirygenTest\Modules\LocalSeo\Admin
 */

declare(strict_types=1);

namespace AirygenTest\Modules\LocalSeo\Admin;

use Airygen\Modules\LocalSeo\Admin\Settings;
use AirygenTest\BaseTestCase;

/**
 * @covers \Airygen\Modules\LocalSeo\Admin\Settings
 */
final class SettingsTest extends BaseTestCase {

	public function set_up(): void {
		parent::set_up();
		delete_option( 'airygen_local_seo' );
	}

	public function tear_down(): void {
		delete_option( 'airygen_local_seo' );
		parent::tear_down();
	}

	public function test_defaults_seed_correctly(): void {
		Settings::ensure_exists();

		$config = Settings::get();

		$this->assertFalse( $config['enabled'] );
		$this->assertSame( 'LocalBusiness', $config['business_type'] );
		$this->assertSame( '', $config['business_name'] );
		$this->assertFalse( $config['enable_geo_tags'] );
		$this->assertSame( 15, $config['map_zoom'] );
		$this->assertSame( '', $config['service_catalog_name'] );
		$this->assertSame( array(), $config['service_catalog_items'] );
		$this->assertSame(
			array(
				'image_url',
				'map',
				'logo_url',
				'business_name',
				'service_catalog',
				'pricing',
				'address',
				'opening_hours',
				'service_areas',
				'special_hours',
				'legal_name',
				'vat_id',
				'phone',
			),
			$config['layout_order']
		);
		$this->assertSame( 13, count( $config['layout_grid'] ) );
		$this->assertSame( 'image_url', $config['layout_grid'][0]['block_id'] );
		$this->assertSame( 1, $config['layout_grid'][0]['row'] );
		$this->assertSame( 1, $config['layout_grid'][0]['col'] );
		$this->assertSame( 5, $config['layout_grid'][0]['span'] );
		$this->assertSame( 1, $config['layout_grid'][0]['row_span'] );
		$this->assertFalse( $config['footer_nap_enabled'] );
		$this->assertFalse( $config['contact_auto_map_embed'] );
		$this->assertFalse( $config['kml_in_sitemap'] );
		$this->assertFalse( $config['contact_detailed_opening_hours'] );
		$this->assertSame( array(), $config['service_area_cities'] );
		$this->assertSame( array(), $config['service_area_postal_codes'] );
		$this->assertSame( 0.0, $config['service_area_radius_km'] );
		$this->assertSame( '', $config['legal_name'] );
		$this->assertSame( '', $config['logo_url'] );
		$this->assertSame( '$$', $config['price_range_level'] );
		$this->assertSame( '', $config['price_range_custom'] );
		$this->assertSame( 0.0, $config['rating_value'] );
		$this->assertSame( 0, $config['review_count'] );
		$this->assertSame( array(), $config['same_as_urls'] );
		$this->assertSame( '', $config['vat_id'] );
		$this->assertFalse( $config['show_vat_in_footer'] );
		$this->assertFalse( $config['click_to_call_enabled'] );
		$this->assertSame( '', $config['special_hours'] );
		$this->assertFalse( $config['vat_validate_checksum'] );
	}

	public function test_update_sanitizes_payload(): void {
		Settings::update(
			array(
				'enabled'                        => true,
				'business_type'                  => 'InvalidType',
				'business_name'                  => '  <b>Airygen Lab</b>  ',
				'opening_hours'                  => "Mo-Fr 09:00-18:00\n<script>alert(1)</script>",
				'enable_geo_tags'                => true,
				'map_zoom'                       => 99,
				'service_catalog_name'           => ' <b>Core Services</b> ',
				'service_catalog_items'          => array(
					array(
						'name'        => '  Drain cleaning  ',
						'description' => ' Fast and safe <script>alert(1)</script> ',
					),
					array(
						'name'        => '',
						'description' => '',
					),
				),
				'layout_order'                   => array( 'phone', 'business_name', 'phone', 'invalid' ),
				'layout_grid'                    => array(
					array(
						'block_id' => 'phone',
						'row'      => 1,
						'col'      => 3,
						'span'     => 3,
						'row_span' => 2,
					),
					array(
						'block_id' => 'business_name',
						'row'      => 2,
						'col'      => 1,
						'span'     => 2,
						'row_span' => 1,
					),
					array(
						'block_id' => 'invalid',
						'row'      => 1,
						'col'      => 1,
						'span'     => 1,
						'row_span' => 1,
					),
				),
				'footer_nap_enabled'             => true,
				'contact_auto_map_embed'         => true,
				'kml_in_sitemap'                 => false,
				'contact_detailed_opening_hours' => true,
				'service_area_cities'            => array( '  Taipei City ', 'New Taipei City' ),
				'service_area_postal_codes'      => array( ' 100 ', '220' ),
				'service_area_radius_km'         => 25.2,
				'legal_name'                     => '  Airygen Bistro Co., Ltd.  ',
				'logo_url'                       => ' https://example.com/logo.png ',
				'rating_value'                   => 4.89,
				'review_count'                   => 152,
				'same_as_urls'                   => array( ' https://facebook.com/airygen ', 'https://instagram.com/airygen' ),
				'vat_id'                         => '12345675',
				'vat_validate_checksum'          => true,
				'show_vat_in_footer'             => true,
				'click_to_call_enabled'          => true,
				'special_hours'                  => "2026-12-25|closed\n2026-12-31|09:00-15:00",
				'price_range_level'              => '$$$$',
				'price_range_custom'             => ' TWD 900 - 1800 ',
			)
		);

		$config = Settings::get();

		$this->assertTrue( $config['enabled'] );
		$this->assertSame( 'LocalBusiness', $config['business_type'] );
		$this->assertSame( 'Airygen Lab', $config['business_name'] );
		$this->assertSame( 'Mo-Fr 09:00-18:00', $config['opening_hours'] );
		$this->assertTrue( $config['enable_geo_tags'] );
		$this->assertSame( 21, $config['map_zoom'] );
		$this->assertSame( 'Core Services', $config['service_catalog_name'] );
		$this->assertSame( 1, count( $config['service_catalog_items'] ) );
		$this->assertSame( 'Drain cleaning', $config['service_catalog_items'][0]['name'] );
		$this->assertSame( 'Fast and safe', $config['service_catalog_items'][0]['description'] );
		$this->assertSame(
			array(
				'phone',
				'business_name',
			),
			$config['layout_order']
		);
		$this->assertSame( 'phone', $config['layout_grid'][0]['block_id'] );
		$this->assertSame( 1, $config['layout_grid'][0]['row'] );
		$this->assertSame( 3, $config['layout_grid'][0]['col'] );
		$this->assertSame( 3, $config['layout_grid'][0]['span'] );
		$this->assertSame( 2, $config['layout_grid'][0]['row_span'] );
		$this->assertSame( 'business_name', $config['layout_grid'][1]['block_id'] );
		$this->assertSame( 2, $config['layout_grid'][1]['row'] );
		$this->assertSame( 1, $config['layout_grid'][1]['col'] );
		$this->assertSame( 2, $config['layout_grid'][1]['span'] );
		$this->assertSame( 1, $config['layout_grid'][1]['row_span'] );
		$this->assertTrue( $config['footer_nap_enabled'] );
		$this->assertFalse( $config['contact_auto_map_embed'] );
		$this->assertFalse( $config['kml_in_sitemap'] );
		$this->assertTrue( $config['contact_detailed_opening_hours'] );
		$this->assertSame( array( 'Taipei City', 'New Taipei City' ), $config['service_area_cities'] );
		$this->assertSame( array( '100', '220' ), $config['service_area_postal_codes'] );
		$this->assertSame( 25.2, $config['service_area_radius_km'] );
		$this->assertSame( 'Airygen Bistro Co., Ltd.', $config['legal_name'] );
		$this->assertSame( 'https://example.com/logo.png', $config['logo_url'] );
		$this->assertSame( '$$$$', $config['price_range_level'] );
		$this->assertSame( 'TWD 900 - 1800', $config['price_range_custom'] );
		$this->assertSame( 4.89, $config['rating_value'] );
		$this->assertSame( 152, $config['review_count'] );
		$this->assertSame( array( 'https://facebook.com/airygen', 'https://instagram.com/airygen' ), $config['same_as_urls'] );
		$this->assertSame( '12345675', $config['vat_id'] );
		$this->assertTrue( $config['show_vat_in_footer'] );
		$this->assertTrue( $config['click_to_call_enabled'] );
		$this->assertSame( "2026-12-25|closed\n2026-12-31|09:00-15:00", $config['special_hours'] );
		$this->assertTrue( $config['vat_validate_checksum'] );
	}

	public function test_price_range_level_falls_back_to_default_when_invalid(): void {
		Settings::update(
			array(
				'price_range_level' => '$$$$$',
			)
		);

		$config = Settings::get();
		$this->assertSame( '$$', $config['price_range_level'] );
	}

	public function test_kml_in_sitemap_is_disabled_for_zero_or_invalid_coordinates(): void {
		Settings::update(
			array(
				'kml_in_sitemap' => true,
				'latitude'       => 0,
				'longitude'      => 0,
			)
		);

		$config = Settings::get();
		$this->assertFalse( $config['kml_in_sitemap'] );
		$this->assertFalse( $config['contact_auto_map_embed'] );

		Settings::update(
			array(
				'kml_in_sitemap' => true,
				'latitude'       => 'not-a-number',
				'longitude'      => '121.5654',
			)
		);

		$config = Settings::get();
		$this->assertFalse( $config['kml_in_sitemap'] );
		$this->assertFalse( $config['contact_auto_map_embed'] );
	}

	public function test_kml_in_sitemap_can_be_enabled_with_valid_coordinates(): void {
		Settings::update(
			array(
				'kml_in_sitemap'         => true,
				'contact_auto_map_embed' => true,
				'latitude'               => 25.033,
				'longitude'              => 121.5654,
			)
		);

		$config = Settings::get();
		$this->assertTrue( $config['kml_in_sitemap'] );
		$this->assertTrue( $config['contact_auto_map_embed'] );
	}
}
