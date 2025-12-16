<?php
/**
 * REST tests for /airygen/v1/settings.
 *
 * @package AirygenTest\Admin\Rest
 */

declare(strict_types=1);

namespace AirygenTest\Admin\Rest;

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Admin\Panels\Order as PanelOrder;
use Airygen\Admin\Panels\Visibility as PanelVisibility;
use Airygen\Modules\AuthorSeo\Admin\Settings as AuthorSeoSettings;
use Airygen\Modules\Breadcrumbs\Admin\Settings as BreadcrumbSettings;
use Airygen\Modules\LocalSeo\Admin\Settings as LocalSeoSettings;
use Airygen\Modules\MarkdownForAgents\Admin\Settings as MarkdownForAgentsSettings;
use Airygen\Modules\LlmsTxt\Admin\Settings as LlmsTxtSettings;
use Airygen\Modules\OnPageSeo\Admin\Settings as OnPageSettings;
use Airygen\Modules\RssFeedSignature\Admin\Settings as RssFeedSignatureSettings;
use Airygen\Modules\SocialCards\Admin\Settings as SocialSettings;
use Airygen\Modules\TaxonomySeo\Admin\Settings as TaxonomySeoSettings;
use Airygen\Modules\CodeSnippetManager\Admin\Settings as CodeSnippetManagerSettings;
use Airygen\Modules\SiteVerification\Admin\Settings as SiteVerificationSettings;
use Airygen\Modules\WooCommerceSeo\Admin\Settings as WooCommerceSeoSettings;
use WP_REST_Response;

/**
 * @coversNothing
 */
class SettingsRouteTest extends RestRouteTestCase {

	/**
	 * The settings endpoint should return the consolidated payload.
	 *
	 * @return void
	 */
	public function test_get_settings_returns_payload(): void {
		$this->acting_as_admin();

		$response = $this->rest_get( '/airygen/v1/settings' );

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();

		$this->assertArrayHasKey( 'settings', $data );
		$this->assertArrayHasKey( 'meta', $data );
		$this->assertArrayHasKey( 'socialCards', $data['settings'] );
		$this->assertArrayHasKey( 'modules', $data['settings'] );
		$this->assertArrayHasKey( 'onPageSeo', $data['settings'] );
		$this->assertArrayHasKey( 'taxonomySeo', $data['settings'] );
		$this->assertArrayHasKey( 'wooCommerceSeo', $data['settings'] );
		$this->assertArrayHasKey( 'localSeo', $data['settings'] );
		$this->assertArrayHasKey( 'codeSnippetManager', $data['settings'] );
		$this->assertArrayHasKey( 'siteVerification', $data['settings'] );
		$this->assertArrayHasKey( 'rssFeedSignature', $data['settings'] );
	}

	/**
	 * Settings response should expose only the expected top-level payload keys.
	 *
	 * @return void
	 */
	public function test_get_settings_returns_expected_top_level_payload(): void {
		$this->acting_as_admin();
		$response = $this->rest_get( '/airygen/v1/settings' );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$data = $response->get_data();
		$keys = array_keys( $data );
		sort( $keys );
		$this->assertSame( array( 'meta', 'settings', 'wizardDismissed' ), $keys );
	}

	/**
	 * Updating Code Snippets settings via REST should persist payload.
	 *
	 * @return void
	 */
	public function test_update_settings_persists_tracking_manager(): void {
		$this->acting_as_admin();

		$payload = array(
			'settings' => array(
				'codeSnippetManager' => array(
					'snippets' => array(
						array(
							'id'          => 'head-1',
							'enabled'     => true,
							'description' => 'Head',
							'code'        => '<script>console.log("head")</script>',
							'placement'   => 'head',
						),
						array(
							'id'          => 'body-1',
							'enabled'     => false,
							'description' => 'Body',
							'code'        => '<script>console.log("body")</script>',
							'placement'   => 'body',
						),
						array(
							'id'          => 'footer-1',
							'enabled'     => true,
							'description' => 'Footer',
							'code'        => '<script>console.log("footer")</script>',
							'placement'   => 'footer',
						),
					),
				),
			),
		);

		$response = $this->rest_post( '/airygen/v1/settings', $payload );
		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$config = CodeSnippetManagerSettings::get();
		$this->assertCount( 3, $config['snippets'] );
		$this->assertSame( '<script>console.log("head")</script>', $config['snippets'][0]['code'] );
		$this->assertFalse( (bool) $config['snippets'][1]['enabled'] );
		$this->assertSame( '<script>console.log("footer")</script>', $config['snippets'][2]['code'] );
	}

	/**
	 * Updating webmaster verification settings via REST should persist payload.
	 *
	 * @return void
	 */
	public function test_update_settings_persists_site_verification(): void {
		$this->acting_as_admin();

		$payload = array(
			'settings' => array(
				'siteVerification' => array(
					'google'    => 'google-token',
					'bing'      => 'bing-token',
					'yandex'    => 'yandex-token',
					'baidu'     => 'baidu-token',
					'pinterest' => 'pinterest-token',
				),
			),
		);

		$response = $this->rest_post( '/airygen/v1/settings', $payload );
		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$config = SiteVerificationSettings::get();
		$this->assertSame( 'google-token', $config['google'] );
		$this->assertSame( 'bing-token', $config['bing'] );
		$this->assertSame( 'yandex-token', $config['yandex'] );
		$this->assertSame( 'baidu-token', $config['baidu'] );
		$this->assertSame( 'pinterest-token', $config['pinterest'] );
	}

	/**
	 * Updating RSS feed signature settings via REST should persist payload.
	 *
	 * @return void
	 */
	public function test_update_settings_persists_rss_feed_signature(): void {
		$this->acting_as_admin();

		$payload = array(
			'settings' => array(
				'rssFeedSignature' => array(
					'enabled'        => true,
					'before_content' => '<p>Before</p>',
					'after_content'  => '<p>After</p>',
				),
			),
		);

		$response = $this->rest_post( '/airygen/v1/settings', $payload );
		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$config = RssFeedSignatureSettings::get();
		$this->assertTrue( $config['enabled'] );
		$this->assertSame( '<p>Before</p>', $config['before_content'] );
		$this->assertSame( '<p>After</p>', $config['after_content'] );
	}

	/**
	 * Updating the settings endpoint should persist social card config.
	 *
	 * @return void
	 */
	public function test_update_settings_persists_social_cards(): void {
		$this->acting_as_admin();

		$payload = array(
			'settings' => array(
				'socialCards' => array(
					'og'      => array( 'enabled' => false ),
					'twitter' => array( 'enabled' => false ),
				),
			),
		);

		$response = $this->rest_post( '/airygen/v1/settings', $payload );

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$config = SocialSettings::get();

		$this->assertFalse( $config['og']['enabled'] );
		$this->assertFalse( $config['twitter']['enabled'] );
	}

	/**
	 * Host modules can be re-enabled without subscription restrictions.
	 *
	 * @return void
	 */
	public function test_update_settings_allows_reenable_for_host_modules(): void {
		$this->acting_as_admin();

		$current                 = ModuleSettings::get();
		$current['topicCluster'] = false;
		$current['social']       = false;
		ModuleSettings::update( $current );

		$payload = array(
			'settings' => array(
				'modules' => array_merge(
					$current,
					array(
						'topicCluster' => true,
						'social'       => true,
					)
				),
			),
		);

		$response = $this->rest_post( '/airygen/v1/settings', $payload );
		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$updated = ModuleSettings::get();
		$this->assertTrue( $updated['topicCluster'] );
		$this->assertTrue( $updated['social'] );
	}

	/**
	 * Updating the settings should persist OnPage SEO config.
	 *
	 * @return void
	 */
	public function test_update_settings_persists_onpage_config(): void {
		$this->acting_as_admin();

		$payload = array(
			'settings' => array(
				'onPageSeo' => array(
					'output'    => array(
						'title' => false,
					),
					'templates' => array(
						'global'    => array(
							'title' => '%post_title% - %site_name%',
						),
						'separator' => '|',
					),
				),
			),
		);

		$response = $this->rest_post( '/airygen/v1/settings', $payload );

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$config = OnPageSettings::get();

		$this->assertFalse( $config['output']['title'] );
		$this->assertSame( '%post_title% - %site_name%', $config['templates']['global']['title'] );
		$this->assertSame( '|', $config['templates']['separator'] );
	}

	/**
	 * Updating breadcrumbs via REST should persist the payload.
	 *
	 * @return void
	 */
	public function test_update_settings_persists_breadcrumbs(): void {
		$this->acting_as_admin();

		$payload = array(
			'settings' => array(
				'breadcrumbs' => array(
					'manual_output_enabled'  => false,
					'auto_injection_enabled' => true,
					'injection_position'     => 'after_content',
					'separator'              => '/',
					'home'                   => array(
						'display' => false,
						'label'   => 'Start',
						'url'     => 'https://example.com/start',
					),
					'display'                => array(
						'showCurrent'   => false,
						'showAncestors' => true,
					),
				),
			),
		);

		$response = $this->rest_post( '/airygen/v1/settings', $payload );
		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$config = BreadcrumbSettings::get();
		$this->assertFalse( $config['manual_output_enabled'] );
		$this->assertTrue( $config['auto_injection_enabled'] );
		$this->assertSame( 'after_content', $config['injection_position'] );
		$this->assertSame( 'Start', $config['home']['label'] );
		$this->assertFalse( $config['display']['showCurrent'] );
		$this->assertTrue( $config['display']['showAncestors'] );
	}

	/**
	 * Updating Topic Cluster via REST should persist the payload.
	 *
	 * @return void
	 */
	public function test_update_settings_persists_topic_cluster(): void {
		$this->acting_as_admin();

		$payload = array(
			'settings' => array(
				'topicCluster' => array(
					'manual_output_enabled'  => true,
					'auto_injection_enabled' => true,
					'override_breadcrumbs'   => true,
					'override_wp_adjacent'   => true,
					'insert_position'        => 'before-content',
					'post_types'             => array( 'post', 'page' ),
				),
			),
		);

		$response = $this->rest_post( '/airygen/v1/settings', $payload );
		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$config = \Airygen\Modules\TopicCluster\Admin\Settings::get();
		$this->assertTrue( $config['manual_output_enabled'] );
		$this->assertTrue( $config['auto_injection_enabled'] );
		$this->assertTrue( $config['override_breadcrumbs'] );
		$this->assertTrue( $config['override_wp_adjacent'] );
		$this->assertSame( 'before-content', $config['insert_position'] );
		$this->assertSame( array( 'post', 'page' ), $config['post_types'] );
	}

	/**
	 * Updating the settings should persist Author SEO config.
	 *
	 * @return void
	 */
	public function test_update_settings_persists_author_seo(): void {
		$this->acting_as_admin();

		$payload = array(
			'settings' => array(
				'authorSeo' => array(
					'enabled'                 => true,
					'noindex_author_archives' => true,
					'title_template'          => '%author_name% - %site_name%',
					'description_template'    => '%author_bio%',
					'social_profiles'         => array(
						'https://x.com/global_profile',
						'https://linkedin.com/company/global-profile',
					),
				),
			),
		);

		$response = $this->rest_post( '/airygen/v1/settings', $payload );
		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$config = AuthorSeoSettings::get();
		$this->assertTrue( $config['enabled'] );
		$this->assertTrue( $config['noindex_author_archives'] );
		$this->assertSame( '%author_name% - %site_name%', $config['title_template'] );
		$this->assertCount( 2, $config['social_profiles'] );
	}

	/**
	 * Updating the settings should persist Taxonomy SEO config.
	 *
	 * @return void
	 */
	public function test_update_settings_persists_taxonomy_seo(): void {
		$this->acting_as_admin();

		$payload = array(
			'settings' => array(
				'taxonomySeo' => array(
					'enabled'            => true,
					'enabled_taxonomies' => array( 'category' ),
					'templates'          => array(
						'global'        => array(
							'title'       => '%term_name% %separator% %site_name%',
							'description' => '%term_description%',
						),
						'separator'     => '|',
						'custom_tokens' => array(
							'custom_1' => 'Docs',
							'custom_2' => 'Guides',
							'custom_3' => 'Archive',
						),
					),
				),
			),
		);

		$response = $this->rest_post( '/airygen/v1/settings', $payload );
		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$config = TaxonomySeoSettings::get();
		$this->assertTrue( $config['enabled'] );
		$this->assertSame( array( 'category' ), $config['enabled_taxonomies'] );
		$this->assertSame( '|', $config['templates']['separator'] );
		$this->assertSame( 'Docs', $config['templates']['custom_tokens']['custom_1'] );
		$this->assertSame( 'Guides', $config['templates']['custom_tokens']['custom_2'] );
		$this->assertSame( 'Archive', $config['templates']['custom_tokens']['custom_3'] );
	}

	/**
	 * Updating the settings should persist WooCommerce SEO config.
	 *
	 * @return void
	 */
	public function test_update_settings_persists_woocommerce_seo(): void {
		$this->acting_as_admin();

		$payload = array(
			'settings' => array(
				'wooCommerceSeo' => array(
					'enabled'         => true,
					'enable_schema'   => true,
					'brand_attribute' => 'pa_brand',
					'templates'       => array(
						'product'       => array(
							'title'       => '%product_name% %separator% %site_name%',
							'description' => '%product_name% in %category_name%. SKU: %sku%.',
						),
						'separator'     => '|',
						'custom_tokens' => array(
							'custom_1' => 'Store',
							'custom_2' => 'Catalog',
							'custom_3' => 'Products',
						),
					),
				),
			),
		);

		$response = $this->rest_post( '/airygen/v1/settings', $payload );
		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$config = WooCommerceSeoSettings::get();
		$this->assertTrue( $config['enabled'] );
		$this->assertSame( 'pa_brand', $config['brand_attribute'] );
		$this->assertSame( '|', $config['templates']['separator'] );
		$this->assertSame( 'Store', $config['templates']['custom_tokens']['custom_1'] );
		$this->assertSame( 'Catalog', $config['templates']['custom_tokens']['custom_2'] );
		$this->assertSame( 'Products', $config['templates']['custom_tokens']['custom_3'] );
	}

	/**
	 * Updating the settings should persist Local SEO config.
	 *
	 * @return void
	 */
	public function test_update_settings_persists_local_seo(): void {
		$this->acting_as_admin();

		$payload = array(
			'settings' => array(
				'localSeo' => array(
					'enabled'                        => true,
					'business_type'                  => 'Restaurant',
					'business_name'                  => 'Airygen Bistro',
					'legal_name'                     => 'Airygen Bistro Co., Ltd.',
					'image_url'                      => 'https://example.com/bistro.png',
					'logo_url'                       => 'https://example.com/logo.png',
					'phone'                          => '+1-555-200-3000',
					'price_range_level'              => '$$$',
					'price_range_custom'             => 'TWD 500 - 1200',
					'rating_value'                   => 4.7,
					'review_count'                   => 237,
					'same_as_urls'                   => array(
						'https://facebook.com/airygen',
						'https://instagram.com/airygen',
					),
					'street_address'                 => '12 Main St',
					'city'                           => 'Austin',
					'region'                         => 'TX',
					'postal_code'                    => '78701',
					'country'                        => 'US',
					'latitude'                       => 30.2672,
					'longitude'                      => -97.7431,
					'opening_hours'                  => "Mo-Fr 09:00-18:00\nSa 10:00-15:00",
					'enable_geo_tags'                => true,
					'geo_region_code'                => 'US-TX',
					'geo_placename'                  => 'Austin',
					'map_zoom'                       => 14,
					'service_catalog_name'           => 'Home Services',
					'service_catalog_items'          => array(
						array(
							'name'        => 'Drain cleaning',
							'description' => 'Fast unclogging service',
						),
					),
					'layout_order'                   => array( 'map', 'business_name', 'phone', 'address' ),
					'layout_grid'                    => array(
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
							'span'     => 3,
							'row_span' => 1,
						),
					),
					'footer_nap_enabled'             => true,
					'contact_auto_map_embed'         => true,
					'kml_in_sitemap'                 => false,
					'contact_detailed_opening_hours' => true,
					'service_area_cities'            => array( 'Austin', 'Round Rock' ),
					'service_area_postal_codes'      => array( '78701', '78664' ),
					'service_area_radius_km'         => 20,
					'vat_id'                         => '12345675',
					'vat_validate_checksum'          => true,
					'show_vat_in_footer'             => true,
					'click_to_call_enabled'          => true,
					'special_hours'                  => "2026-12-25|closed\n2026-12-31|09:00-15:00",
				),
			),
		);

		$response = $this->rest_post( '/airygen/v1/settings', $payload );
		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$config = LocalSeoSettings::get();

		$this->assertTrue( $config['enabled'] );
		$this->assertSame( 'Restaurant', $config['business_type'] );
		$this->assertSame( 'Airygen Bistro', $config['business_name'] );
		$this->assertSame( 'Airygen Bistro Co., Ltd.', $config['legal_name'] );
		$this->assertSame( 'https://example.com/logo.png', $config['logo_url'] );
		$this->assertSame( '$$$', $config['price_range_level'] );
		$this->assertSame( 'TWD 500 - 1200', $config['price_range_custom'] );
		$this->assertSame( 4.7, $config['rating_value'] );
		$this->assertSame( 237, $config['review_count'] );
		$this->assertSame( array( 'https://facebook.com/airygen', 'https://instagram.com/airygen' ), $config['same_as_urls'] );
		$this->assertSame( 'US-TX', $config['geo_region_code'] );
		$this->assertTrue( $config['enable_geo_tags'] );
		$this->assertSame( 14, $config['map_zoom'] );
		$this->assertSame( 'Home Services', $config['service_catalog_name'] );
		$this->assertSame( 1, count( $config['service_catalog_items'] ) );
		$this->assertSame( 'Drain cleaning', $config['service_catalog_items'][0]['name'] );
		$this->assertSame(
			array(
				'map',
				'business_name',
				'phone',
				'address',
			),
			$config['layout_order']
		);
		$this->assertSame( 'map', $config['layout_grid'][0]['block_id'] );
		$this->assertSame( 1, $config['layout_grid'][0]['row'] );
		$this->assertSame( 1, $config['layout_grid'][0]['col'] );
		$this->assertSame( 5, $config['layout_grid'][0]['span'] );
		$this->assertSame( 2, $config['layout_grid'][0]['row_span'] );
		$this->assertTrue( $config['footer_nap_enabled'] );
		$this->assertTrue( $config['contact_auto_map_embed'] );
		$this->assertFalse( $config['kml_in_sitemap'] );
		$this->assertTrue( $config['contact_detailed_opening_hours'] );
		$this->assertSame( array( 'Austin', 'Round Rock' ), $config['service_area_cities'] );
		$this->assertSame( array( '78701', '78664' ), $config['service_area_postal_codes'] );
		$this->assertSame( 20.0, $config['service_area_radius_km'] );
		$this->assertSame( '12345675', $config['vat_id'] );
		$this->assertTrue( $config['vat_validate_checksum'] );
		$this->assertTrue( $config['show_vat_in_footer'] );
		$this->assertTrue( $config['click_to_call_enabled'] );
		$this->assertSame( "2026-12-25|closed\n2026-12-31|09:00-15:00", $config['special_hours'] );
	}

	/**
	 * Updating Markdown for Agents settings should persist prompts toggle.
	 *
	 * @return void
	 */
	public function test_update_settings_persists_markdown_for_agents_prompts_toggle(): void {
		$this->acting_as_admin();

		$payload = array(
			'settings' => array(
				'markdownForAgents' => array(
					'enabled'             => true,
					'prompts_for_agents'  => true,
					'include_frontmatter' => false,
					'post_types'          => array( 'post' ),
				),
			),
		);

		$response = $this->rest_post( '/airygen/v1/settings', $payload );
		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$config = MarkdownForAgentsSettings::get();
		$this->assertTrue( (bool) $config['enabled'] );
		$this->assertTrue( (bool) $config['prompts_for_agents'] );
		$this->assertFalse( (bool) $config['include_frontmatter'] );
		$this->assertSame( array( 'post' ), $config['post_types'] );
	}

	/**
	 * Updating panel settings should persist Prompts for Agents visibility and order.
	 *
	 * @return void
	 */
	public function test_update_settings_persists_prompts_for_agents_panel_preferences(): void {
		$this->acting_as_admin();

		$order = array(
			'scoreCalculator',
			'promptsForAgents',
			'serpSnippet',
			'keyphrases',
			'canonical',
			'schemaMarkup',
			'robots',
			'toc',
			'linkSuggestions',
			'topicCluster',
		);

		$visibility                     = PanelVisibility::get();
		$visibility['promptsForAgents'] = false;
		$visibility['scoreCalculator']  = true;

		$payload = array(
			'settings' => array(
				'panelOrder'      => $order,
				'panelVisibility' => $visibility,
			),
		);

		$response = $this->rest_post( '/airygen/v1/settings', $payload );
		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$stored_order      = PanelOrder::get();
		$stored_visibility = PanelVisibility::get();

		$this->assertSame( 'promptsForAgents', $stored_order[1] );
		$this->assertArrayHasKey( 'promptsForAgents', $stored_visibility );
		$this->assertFalse( $stored_visibility['promptsForAgents'] );
	}

	/**
	 * Updating LLMs.txt settings should persist selection-based fields.
	 *
	 * @return void
	 */
	public function test_update_settings_persists_llms_txt_v1_fields(): void {
		$this->acting_as_admin();
		$current                      = ModuleSettings::get();
		$current['topicCluster']      = false;
		$current['markdownForAgents'] = true;
		ModuleSettings::update( $current );

		$payload = array(
			'settings' => array(
				'llmsTxt' => array(
					'enabled'                    => true,
					'custom_declaration'         => 'Official AI index for this site.',
					'auto_section_title'         => 'Auto-loaded items',
					'index_strategy'             => 'curated_plus_auto',
					'auto_topic_cluster_groups'  => true,
					'use_markdown_links'         => true,
					'add_to_sitemap'             => true,
					'exclude_noindex'            => true,
					'exclude_password_protected' => true,
					'min_word_count'             => 280,
					'sections'                   => array(
						array(
							'id'          => 'start_here',
							'title'       => 'Start Here',
							'description' => 'Key pages for AI systems.',
							'post_ids'    => array( 200, 300 ),
							'max_items'   => 5,
						),
					),
					'post_types'                 => array( 'post', 'page' ),
				),
			),
		);

		$response = $this->rest_post( '/airygen/v1/settings', $payload );
		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$config = LlmsTxtSettings::get();
		$this->assertTrue( (bool) $config['enabled'] );
		$this->assertSame( 'Official AI index for this site.', $config['custom_declaration'] );
		$this->assertSame( 'Auto-loaded items', $config['auto_section_title'] );
		$this->assertSame( 'curated_plus_auto', $config['index_strategy'] );
		$this->assertFalse( (bool) $config['auto_topic_cluster_groups'] );
		$this->assertTrue( (bool) $config['use_markdown_links'] );
		$this->assertTrue( (bool) $config['add_to_sitemap'] );
		$this->assertTrue( (bool) $config['exclude_noindex'] );
		$this->assertTrue( (bool) $config['exclude_password_protected'] );
		$this->assertSame( 280, $config['min_word_count'] );
		$this->assertCount( 1, $config['sections'] );
		$this->assertSame( 'start_here', $config['sections'][0]['id'] );
		$this->assertSame( 'Start Here', $config['sections'][0]['title'] );
		$this->assertSame( array( 200, 300 ), $config['sections'][0]['post_ids'] );
		$this->assertSame( array( 'post', 'page' ), $config['post_types'] );
	}
}
