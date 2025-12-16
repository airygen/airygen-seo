<?php
/**
 * Tests for sitemap controller behavior.
 *
 * @package AirygenTest\Modules\Sitemap\Public
 */

declare(strict_types=1);

namespace AirygenTest\Modules\Sitemap\Public;

use Airygen\Constants;
use Airygen\Modules\LocalSeo\Admin\Settings as LocalSeoSettings;
use Airygen\Modules\LlmsTxt\Admin\Settings as LlmsTxtSettings;
use Airygen\Modules\Sitemap\Admin\Settings;
use Airygen\Modules\Sitemap\Public\Controller;
use AirygenTest\BaseTestCase;
use ReflectionMethod;

/**
 * @covers \Airygen\Modules\Sitemap\Public\Controller
 */
final class ControllerTest extends BaseTestCase {

	public function set_up(): void {
		parent::set_up();
		delete_option( 'airygen_local_seo' );
		delete_option( 'airygen_llms_txt' );
	}

	public function tear_down(): void {
		delete_option( 'airygen_local_seo' );
		delete_option( 'airygen_llms_txt' );
		parent::tear_down();
	}

	/**
	 * Terms marked as noindex should be excluded from taxonomy sitemap pages.
	 *
	 * @return void
	 */
	public function test_fetch_taxonomy_page_excludes_noindex_terms(): void {
		register_taxonomy(
			'airygen_taxonomy_noindex',
			array( 'post' ),
			array(
				'public'            => true,
				'show_ui'           => false,
				'show_in_rest'      => false,
				'rewrite'           => false,
				'hierarchical'      => false,
				'query_var'         => true,
				'show_admin_column' => false,
				'label'             => 'Airygen Noindex Taxonomy',
			)
		);

		Settings::update(
			array(
				'enabled_taxonomies'       => array( 'airygen_taxonomy_noindex' ),
				'exclude_empty_taxonomies' => false,
			)
		);

		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'airygen_taxonomy_noindex',
				'name'     => 'Noindex Term',
				'slug'     => 'noindex-term',
			)
		);

		update_term_meta( $term_id, Constants::META_TERM_ROBOTS, 'noindex,follow' );

		$method = new ReflectionMethod( Controller::class, 'fetch_taxonomy_page' );
		$method->setAccessible( true );
		$data = $method->invoke( null, 'airygen_taxonomy_noindex', 1 );

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'items', $data );
		$this->assertSame( array(), $data['items'] );

		unregister_taxonomy( 'airygen_taxonomy_noindex' );
	}

	/**
	 * Local KML entry should be added when Local SEO coordinates are available.
	 *
	 * @return void
	 */
	public function test_build_local_kml_index_entry_returns_kml_url_when_local_seo_enabled(): void {
		LocalSeoSettings::update(
			array(
				'enabled'        => true,
				'kml_in_sitemap' => true,
				'business_name'  => 'Airygen Local',
				'latitude'       => 25.033,
				'longitude'      => 121.5654,
			)
		);

		$method = new ReflectionMethod( Controller::class, 'build_local_kml_index_entry' );
		$method->setAccessible( true );
		$entry = $method->invoke( null );

		$this->assertIsArray( $entry );
		$this->assertSame( home_url( '/local.kml' ), $entry['loc'] );
		$this->assertArrayHasKey( 'lastmod', $entry );
	}

	/**
	 * KML entry should be empty when Local SEO is disabled.
	 *
	 * @return void
	 */
	public function test_build_local_kml_index_entry_returns_empty_when_local_seo_disabled(): void {
		LocalSeoSettings::update(
			array(
				'enabled'   => false,
				'latitude'  => 25.033,
				'longitude' => 121.5654,
			)
		);

		$method = new ReflectionMethod( Controller::class, 'build_local_kml_index_entry' );
		$method->setAccessible( true );
		$entry = $method->invoke( null );

		$this->assertSame( array(), $entry );
	}

	/**
	 * KML entry should be empty when Local SEO KML sitemap toggle is disabled.
	 *
	 * @return void
	 */
	public function test_build_local_kml_index_entry_returns_empty_when_kml_toggle_disabled(): void {
		LocalSeoSettings::update(
			array(
				'enabled'        => true,
				'kml_in_sitemap' => false,
				'latitude'       => 25.033,
				'longitude'      => 121.5654,
			)
		);

		$method = new ReflectionMethod( Controller::class, 'build_local_kml_index_entry' );
		$method->setAccessible( true );
		$entry = $method->invoke( null );

		$this->assertSame( array(), $entry );
	}

	/**
	 * local.kml path detection should handle query strings.
	 *
	 * @return void
	 */
	public function test_is_local_kml_request_path_matches_expected_paths(): void {
		$method = new ReflectionMethod( Controller::class, 'is_local_kml_request_path' );
		$method->setAccessible( true );

		$this->assertTrue( $method->invoke( null, '/local.kml' ) );
		$this->assertTrue( $method->invoke( null, '/local.kml?foo=bar' ) );
		$this->assertFalse( $method->invoke( null, '/sitemap.xml' ) );
		$this->assertFalse( $method->invoke( null, '' ) );
	}

	/**
	 * llms.txt root and enabled extensions should be included in sitemap index.
	 *
	 * @return void
	 */
	public function test_build_llms_index_entries_returns_root_and_enabled_extensions(): void {
		LlmsTxtSettings::update(
			array(
				'enabled'        => true,
				'add_to_sitemap' => true,
				'extensions'     => array(
					array(
						'id'       => 'ext_1',
						'title'    => 'Docs',
						'path'     => 'docs/ja',
						'filename' => 'llms-small.txt',
						'enabled'  => true,
						'sections' => array(),
					),
					array(
						'id'       => 'ext_2',
						'title'    => 'Disabled',
						'path'     => 'docs/en',
						'filename' => 'llms-full.txt',
						'enabled'  => false,
						'sections' => array(),
					),
				),
			)
		);

		$method = new ReflectionMethod( Controller::class, 'build_llms_index_entries' );
		$method->setAccessible( true );
		$entries = $method->invoke( null );

		$this->assertCount( 2, $entries );
		$this->assertSame( home_url( '/llms.txt' ), $entries[0]->get_loc() );
		$this->assertSame( home_url( '/docs/ja/llms-small.txt' ), $entries[1]->get_loc() );
	}

	/**
	 * llms.txt root and extensions should be skipped when add_to_sitemap is disabled.
	 *
	 * @return void
	 */
	public function test_build_llms_index_entries_returns_empty_when_add_to_sitemap_is_disabled(): void {
		LlmsTxtSettings::update(
			array(
				'enabled'        => true,
				'add_to_sitemap' => false,
				'extensions'     => array(
					array(
						'id'       => 'ext_1',
						'title'    => 'Docs',
						'path'     => 'docs/ja',
						'filename' => 'llms-small.txt',
						'enabled'  => true,
						'sections' => array(),
					),
				),
			)
		);

		$method = new ReflectionMethod( Controller::class, 'build_llms_index_entries' );
		$method->setAccessible( true );
		$entries = $method->invoke( null );

		$this->assertSame( array(), $entries );
	}
}
