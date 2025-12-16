<?php
/**
 * Tests for Local SEO public hooks.
 *
 * @package AirygenTest\Modules\LocalSeo\Public
 */

declare(strict_types=1);

namespace AirygenTest\Modules\LocalSeo\Public;

use Airygen\Modules\LocalSeo\Admin\Settings;
use Airygen\Modules\LocalSeo\Public\Hooks;
use Airygen\Support\Meta\PostData;
use AirygenTest\BaseTestCase;

/**
 * @covers \Airygen\Modules\LocalSeo\Public\Hooks
 */
final class HooksTest extends BaseTestCase {

	public function set_up(): void {
		parent::set_up();
		delete_option( 'airygen_local_seo' );
		Hooks::register();
	}

	public function tear_down(): void {
		delete_option( 'airygen_local_seo' );
		wp_dequeue_style( 'airygen-local-business-css' );
		wp_deregister_style( 'airygen-local-business-css' );
		wp_dequeue_style( 'airygen-local-nap-css' );
		wp_deregister_style( 'airygen-local-nap-css' );
		parent::tear_down();
	}

	public function test_emit_head_outputs_schema_and_geo_meta_and_accepts_en_dash_hours(): void {
		$en_dash = "\xE2\x80\x93";

		Settings::update(
			array(
				'enabled'                   => true,
				'business_type'             => 'Restaurant',
				'business_name'             => 'Airygen Bistro',
				'legal_name'                => 'Airygen Bistro Co., Ltd.',
				'street_address'            => '12 Main St',
				'city'                      => 'Austin',
				'region'                    => 'TX',
				'postal_code'               => '78701',
				'country'                   => 'US',
				'logo_url'                  => 'https://example.com/logo.png',
				'latitude'                  => 30.2672,
				'longitude'                 => -97.7431,
				'opening_hours'             => sprintf( 'Mo%sFr 09:00%s18:00', $en_dash, $en_dash ),
				'special_hours'             => "2026-12-25|closed\n2026-12-31|09:00-15:00",
				'rating_value'              => 4.8,
				'review_count'              => 103,
				'same_as_urls'              => array( 'https://facebook.com/airygen', 'https://instagram.com/airygen' ),
				'vat_id'                    => '12345675',
				'enable_geo_tags'           => true,
				'geo_region_code'           => 'US-TX',
				'geo_placename'             => 'Austin',
				'service_catalog_name'      => 'Home Services',
				'service_catalog_items'     => array(
					array(
						'name'        => 'Emergency plumbing',
						'description' => '24/7 leak and pipe repair',
					),
				),
				'service_area_cities'       => array( 'Austin', 'Round Rock' ),
				'service_area_postal_codes' => array( '78701' ),
				'service_area_radius_km'    => 20,
			)
		);

		ob_start();
		Hooks::emit_head();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '<script type="application/ld+json">', $output );
		$this->assertStringContainsString( '"@type":"Restaurant"', $output );
		$this->assertStringContainsString( '"openingHoursSpecification"', $output );
		$this->assertStringContainsString( '"Monday"', $output );
		$this->assertStringContainsString( '"hasOfferCatalog"', $output );
		$this->assertStringContainsString( '"OfferCatalog"', $output );
		$this->assertStringContainsString( '"Emergency plumbing"', $output );
		$this->assertStringContainsString( '"areaServed"', $output );
		$this->assertStringContainsString( '"@type":"City"', $output );
		$this->assertStringContainsString( '"@type":"GeoCircle"', $output );
		$this->assertStringContainsString( '"logo":{"@type":"ImageObject","url":"https:\/\/example.com\/logo.png"}', $output );
		$this->assertStringContainsString( '"legalName":"Airygen Bistro Co., Ltd."', $output );
		$this->assertStringContainsString( '"aggregateRating"', $output );
		$this->assertStringContainsString( '"sameAs"', $output );
		$this->assertStringContainsString( '"specialOpeningHoursSpecification"', $output );
		$this->assertStringContainsString( '"vatID":"12345675"', $output );
		$this->assertStringContainsString( '"taxID":"12345675"', $output );
		$this->assertStringContainsString( 'meta name="geo.region" content="US-TX"', $output );
		$this->assertStringContainsString( 'meta name="geo.placename" content="Austin"', $output );
	}

	public function test_emit_head_prefers_custom_price_range_over_level(): void {
		Settings::update(
			array(
				'enabled'            => true,
				'business_name'      => 'Airygen Bistro',
				'city'               => 'Austin',
				'country'            => 'US',
				'price_range_level'  => '$$$$',
				'price_range_custom' => 'TWD 500 - 1200',
			)
		);

		ob_start();
		Hooks::emit_head();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '"priceRange":"TWD 500 - 1200"', $output );
		$this->assertStringNotContainsString( '"priceRange":"$$$$"', $output );
	}

	public function test_emit_head_uses_price_level_when_custom_price_range_is_empty(): void {
		Settings::update(
			array(
				'enabled'            => true,
				'business_name'      => 'Airygen Bistro',
				'city'               => 'Austin',
				'country'            => 'US',
				'price_range_level'  => '$$$',
				'price_range_custom' => '',
			)
		);

		ob_start();
		Hooks::emit_head();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '"priceRange":"$$$"', $output );
	}

	public function test_emit_head_outputs_minimal_localbusiness_schema_on_regular_non_shortcode_pages(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Regular post',
				'post_content' => 'No local seo shortcode here.',
			)
		);

		Settings::update(
			array(
				'enabled'        => true,
				'business_type'  => 'BeautySalon',
				'business_name'  => 'Airygen Beauty',
				'street_address' => '12 Main St',
				'city'           => 'Austin',
				'country'        => 'US',
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		ob_start();
		Hooks::emit_head();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '"@type":"BeautySalon"', $output );
		$this->assertStringContainsString( '"@id":"' . addcslashes( trailingslashit( home_url( '/' ) ), '/' ) . '#identity"', $output );
		$this->assertStringNotContainsString( '"name":"Airygen Beauty"', $output );
		$this->assertStringNotContainsString( '"address":', $output );
	}

	public function test_merge_local_business_into_schema_payload_uses_identity_id_and_merges_images(): void {
		Settings::update(
			array(
				'enabled'       => true,
				'business_name' => 'Airygen Local',
				'business_type' => 'LocalBusiness',
				'image_url'     => 'https://example.com/local.jpg',
				'city'          => 'Austin',
				'country'       => 'US',
			)
		);

		$payload = array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				array(
					'@type' => 'Organization',
					'@id'   => 'https://example.com/#organization',
					'name'  => 'Airygen Inc.',
					'image' => 'https://example.com/org.jpg',
				),
				array(
					'@type'     => 'Article',
					'headline'  => 'Hello',
					'url'       => 'https://example.com/post',
					'publisher' => array(
						'name' => 'Legacy Publisher',
					),
				),
			),
		);

		$merged = Hooks::merge_local_business_into_schema_payload( $payload, array() );

		$this->assertSame( 'https://schema.org', $merged['@context'] );
		$this->assertIsArray( $merged['@graph'] );
		$this->assertCount( 2, $merged['@graph'] );

		$identity_id = trailingslashit( home_url( '/' ) ) . '#identity';

		$identity_node = null;
		$article_node  = null;
		foreach ( $merged['@graph'] as $node ) {
			if ( isset( $node['@id'] ) && $identity_id === $node['@id'] ) {
				$identity_node = $node;
				continue;
			}
			if ( isset( $node['@type'] ) && 'Article' === $node['@type'] ) {
				$article_node = $node;
			}
		}

		$this->assertNotNull( $identity_node );
		$this->assertSame( array( 'Organization', 'LocalBusiness' ), $identity_node['@type'] );
		$this->assertSame(
			array( 'https://example.com/org.jpg', 'https://example.com/local.jpg' ),
			$identity_node['image']
		);

		$this->assertNotNull( $article_node );
		$this->assertSame( array( '@id' => $identity_id ), $article_node['publisher'] );
	}

	public function test_merge_local_business_into_schema_payload_keeps_single_type_when_same(): void {
		Settings::update(
			array(
				'enabled'       => true,
				'business_name' => 'Airygen Local',
				'business_type' => 'LocalBusiness',
				'city'          => 'Austin',
				'country'       => 'US',
			)
		);

		$identity_id = trailingslashit( home_url( '/' ) ) . '#identity';
		$payload     = array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				array(
					'@type' => 'LocalBusiness',
					'@id'   => $identity_id,
					'name'  => 'Airygen Inc.',
				),
			),
		);

		$merged = Hooks::merge_local_business_into_schema_payload( $payload, array() );
		$this->assertIsArray( $merged['@graph'] );
		$this->assertCount( 1, $merged['@graph'] );
		$this->assertSame( 'LocalBusiness', $merged['@graph'][0]['@type'] );
	}

	public function test_merge_local_business_into_schema_payload_uses_type_array_when_org_and_localbusiness_type_differ(): void {
		Settings::update(
			array(
				'enabled'       => true,
				'business_name' => 'Airygen Beauty',
				'business_type' => 'BeautySalon',
				'city'          => 'Austin',
				'country'       => 'US',
			)
		);

		$identity_id = trailingslashit( home_url( '/' ) ) . '#identity';
		$payload     = array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				array(
					'@type' => 'LocalBusiness',
					'@id'   => $identity_id,
					'name'  => 'Airygen Org',
				),
			),
		);

		$merged = Hooks::merge_local_business_into_schema_payload( $payload, array() );
		$this->assertIsArray( $merged['@graph'] );
		$this->assertCount( 1, $merged['@graph'] );
		$this->assertSame( array( 'LocalBusiness', 'BeautySalon' ), $merged['@graph'][0]['@type'] );
	}

	public function test_unified_shortcode_renders_single_card_output(): void {
		Settings::update(
			array(
				'enabled'        => true,
				'business_name'  => 'Airygen HQ',
				'street_address' => '100 Market St',
				'city'           => 'San Francisco',
				'region'         => 'CA',
				'postal_code'    => '94105',
				'country'        => 'US',
			)
		);

		$html = Hooks::render_localseo_shortcode( array() );
		$this->assertStringContainsString( 'Airygen HQ', $html );
		$this->assertStringContainsString( '100 Market St San Francisco CA 94105 US', $html );
	}

	public function test_unified_shortcode_returns_empty_without_renderable_data(): void {
		Settings::update(
			array(
				'enabled'   => true,
				'map_zoom'  => 15,
				'latitude'  => 0,
				'longitude' => 0,
			)
		);

		$this->assertSame( '', Hooks::render_localseo_shortcode( array() ) );
	}

	public function test_unified_shortcode_default_card_renders_info_and_map(): void {
		Settings::update(
			array(
				'enabled'        => true,
				'business_name'  => 'Airygen HQ',
				'street_address' => '100 Market St',
				'city'           => 'San Francisco',
				'region'         => 'CA',
				'postal_code'    => '94105',
				'country'        => 'US',
				'phone'          => '+1-555-100-2000',
				'latitude'       => 37.7749,
				'longitude'      => -122.4194,
			)
		);

		$html = Hooks::render_localseo_shortcode( array() );
		$this->assertStringContainsString( 'Airygen HQ', $html );
		$this->assertStringContainsString( '100 Market St San Francisco CA 94105 US', $html );
		$this->assertStringContainsString( 'google.com/maps', $html );
	}

	public function test_unified_shortcode_respects_layout_order(): void {
		Settings::update(
			array(
				'enabled'        => true,
				'business_name'  => 'Airygen HQ',
				'street_address' => '100 Market St',
				'city'           => 'San Francisco',
				'region'         => 'CA',
				'postal_code'    => '94105',
				'country'        => 'US',
				'phone'          => '+1-555-100-2000',
				'latitude'       => 37.7749,
				'longitude'      => -122.4194,
				'layout_order'   => array( 'phone', 'business_name', 'address', 'map' ),
			)
		);

		$html = Hooks::render_localseo_shortcode( array() );
		$this->assertIsInt( strpos( $html, '+1-555-100-2000' ) );
		$this->assertIsInt( strpos( $html, 'Airygen HQ' ) );
		$this->assertIsInt( strpos( $html, '100 Market St San Francisco CA 94105 US' ) );

		$this->assertTrue( strpos( $html, '+1-555-100-2000' ) < strpos( $html, 'Airygen HQ' ) );
		$this->assertTrue(
			strpos( $html, 'Airygen HQ' ) < strpos( $html, '100 Market St San Francisco CA 94105 US' )
		);
	}

	public function test_unified_shortcode_applies_layout_grid_position_and_span(): void {
		Settings::update(
			array(
				'enabled'        => true,
				'business_name'  => 'Airygen HQ',
				'street_address' => '100 Market St',
				'city'           => 'San Francisco',
				'region'         => 'CA',
				'postal_code'    => '94105',
				'country'        => 'US',
				'phone'          => '+1-555-100-2000',
				'latitude'       => 37.7749,
				'longitude'      => -122.4194,
				'layout_grid'    => array(
					array(
						'block_id' => 'map',
						'row'      => 1,
						'col'      => 1,
						'span'     => 5,
						'row_span' => 2,
					),
					array(
						'block_id' => 'business_name',
						'row'      => 2,
						'col'      => 1,
						'span'     => 2,
						'row_span' => 1,
					),
				),
			)
		);

		$html = Hooks::render_localseo_shortcode( array() );
		$this->assertStringContainsString( 'airygen-local-business__layout', $html );
		$this->assertStringContainsString( 'airygen-local-business__lane', $html );
		$this->assertStringContainsString( 'airygen-local-business__item--map', $html );
		$this->assertStringContainsString( 'airygen-local-business__item--business_name', $html );
		$this->assertStringContainsString( 'grid-column:1 / span 3;grid-row:1 / span 2;', $html );
		$this->assertStringContainsString( 'grid-column:1 / span 2;grid-row:1 / span 1;', $html );
	}

	public function test_unified_shortcode_renders_service_areas_as_unordered_list(): void {
		Settings::update(
			array(
				'enabled'                   => true,
				'business_name'             => 'Airygen HQ',
				'street_address'            => '100 Market St',
				'city'                      => 'San Francisco',
				'region'                    => 'CA',
				'postal_code'               => '94105',
				'country'                   => 'US',
				'service_area_cities'       => array( 'San Francisco', 'Oakland' ),
				'service_area_postal_codes' => array( '94105' ),
				'service_area_radius_km'    => 25,
				'layout_grid'               => array(
					array(
						'block_id' => 'service_areas',
						'row'      => 1,
						'col'      => 1,
						'span'     => 3,
						'row_span' => 1,
					),
				),
			)
		);

		$html = Hooks::render_localseo_shortcode( array() );
		$this->assertStringContainsString( 'airygen-local-business__item--service_areas', $html );
		$this->assertStringContainsString( '<ul><li>San Francisco, Oakland</li><li>Postal: 94105</li><li>Radius: 25 km</li></ul>', $html );
		$this->assertStringNotContainsString( 'Service Areas:', $html );
	}

	public function test_emit_head_outputs_service_schema_with_provider_link_for_service_pages(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Emergency plumbing',
				'post_excerpt' => 'Fast onsite repair service.',
			)
		);

		PostData::save(
			$post_id,
			array(
				'schemaArticleType' => 'Service',
			)
		);

		Settings::update(
			array(
				'enabled'               => true,
				'business_name'         => 'Airygen Services',
				'city'                  => 'Austin',
				'region'                => 'TX',
				'country'               => 'US',
				'service_catalog_name'  => 'Home Services',
				'service_catalog_items' => array(
					array(
						'name'        => 'Emergency plumbing',
						'description' => '24/7 leak and pipe repair',
					),
				),
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		ob_start();
		Hooks::emit_head();
		$output = (string) ob_get_clean();

		$business_id = addcslashes( trailingslashit( home_url( '/' ) ), '/' ) . '#identity';
		$this->assertStringContainsString( '"@id":"' . $business_id . '"', $output );
		$this->assertStringContainsString( '"@type":"Service"', $output );
		$this->assertStringContainsString( '"mainEntityOfPage":"' . addcslashes( get_permalink( $post_id ), '/' ) . '"', $output );
		$this->assertStringContainsString( '"provider":{"@id":"' . $business_id . '"}', $output );
	}

	public function test_emit_footer_nap_outputs_address_when_enabled(): void {
		$page_id = self::factory()->post->create(
			array(
				'post_type' => 'page',
			)
		);

		Settings::update(
			array(
				'enabled'            => true,
				'footer_nap_enabled' => true,
				'show_vat_in_footer' => true,
				'vat_id'             => '12345675',
				'business_name'      => 'Airygen Local',
				'street_address'     => '12 Main St',
				'city'               => 'Austin',
				'region'             => 'TX',
				'postal_code'        => '78701',
				'country'            => 'US',
				'phone'              => '+1-555-200-3000',
			)
		);

		$this->go_to( get_permalink( $page_id ) );

		ob_start();
		Hooks::emit_footer_nap();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '<address', $output );
		$this->assertStringContainsString( 'Airygen Local', $output );
		$this->assertStringContainsString( '+1-555-200-3000', $output );
		$this->assertStringContainsString( 'Tax ID: 12345675', $output );
	}

	public function test_enqueue_assets_registers_local_business_and_footer_nap_styles(): void {
		Settings::update(
			array(
				'enabled'                    => true,
				'footer_nap_enabled'         => true,
				'layout_label_font_size'     => 15,
				'footer_nap_text_align'      => 'left',
				'footer_nap_text_color'      => '#123456',
				'footer_nap_margin_y'        => 18,
				'footer_nap_container_width' => 720,
			)
		);

		Hooks::enqueue_assets();

		$styles = wp_styles();
		$this->assertContains( 'airygen-local-business-css', $styles->queue );
		$this->assertContains( 'airygen-local-nap-css', $styles->queue );

		$business_css = implode( "\n", (array) $styles->get_data( 'airygen-local-business-css', 'after' ) );
		$nap_css      = implode( "\n", (array) $styles->get_data( 'airygen-local-nap-css', 'after' ) );

		$this->assertStringContainsString( '.airygen-local-business__layout', $business_css );
		$this->assertStringContainsString( 'font-size:15px', $business_css );
		$this->assertStringContainsString( '.airygen-local-nap__address', $nap_css );
		$this->assertStringContainsString( 'text-align:left', $nap_css );
		$this->assertStringContainsString( 'color:#123456', $nap_css );
	}

	public function test_emit_head_outputs_contact_opening_hours_schema(): void {
		$contact_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_name'    => 'contact',
				'post_content' => '[airygen_localseo]',
			)
		);

		Settings::update(
			array(
				'enabled'       => true,
				'business_name' => 'Airygen Contact',
				'opening_hours' => "Mo 09:00-12:00,13:00-18:00\nTu 10:00-16:00",
			)
		);

		$this->go_to( get_permalink( $contact_id ) );

		ob_start();
		Hooks::emit_head();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '"openingHoursSpecification"', $output );
		$this->assertStringContainsString( '"opens":"09:00"', $output );
		$this->assertStringContainsString( '"closes":"12:00"', $output );
		$this->assertStringContainsString( '"opens":"13:00"', $output );
		$this->assertSame( 1, substr_count( $output, '"@type":"LocalBusiness"' ) );
	}

	public function test_inject_click_to_call_wraps_plain_phone_numbers_but_skips_existing_tel_links(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_type' => 'post',
			)
		);

		Settings::update(
			array(
				'enabled'               => true,
				'click_to_call_enabled' => true,
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		$content = '<p>Call us at +1 (555) 200-3000 now.</p><p><a href="tel:+15553001111">+1 555 300 1111</a></p>';
		$output  = Hooks::inject_click_to_call_links( $content );

		$this->assertStringContainsString( '<a href="tel:+15552003000">+1 (555) 200-3000</a>', $output );
		$this->assertStringContainsString( '<a href="tel:+15553001111">+1 555 300 1111</a>', $output );
	}
}
