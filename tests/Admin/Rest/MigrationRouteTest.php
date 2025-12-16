<?php
/**
 * Tests for migration REST endpoints.
 *
 * @package AirygenTest\Admin\Rest
 */

declare(strict_types=1);

namespace AirygenTest\Admin\Rest;

use Airygen\Constants;
use Airygen\Modules\Redirects\Admin\Settings as RedirectsSettings;
use Airygen\Modules\RssFeedSignature\Admin\Settings as RssFeedSignatureSettings;
use Airygen\Modules\SocialCards\Admin\Settings as SocialSettings;
use Airygen\Modules\SiteVerification\Admin\Settings as SiteVerificationSettings;
use Airygen\Support\Meta\PostData;

/**
 * @coversNothing
 */
final class MigrationRouteTest extends RestRouteTestCase {

	/**
	 * Ensure status endpoint returns progress payload.
	 *
	 * @return void
	 */
	public function test_status_returns_progress(): void {
		$this->acting_as_admin();

		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, Constants::META_YOAST_MIGRATED, gmdate( 'c' ) );

		$response = $this->rest_get( '/airygen/v1/migration/yoast' );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'progress', $data );
		$this->assertSame( 1, $data['progress']['migrated'] ?? null );
	}

	/**
	 * Ensure import endpoint migrates Yoast meta.
	 *
	 * @return void
	 */
	public function test_import_migrates_post_meta(): void {
		$this->acting_as_admin();

		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_yoast_wpseo_title', 'Yoast Title' );

		$response = $this->rest_post( '/airygen/v1/migration/yoast/import', array() );
		$this->assertSame( 200, $response->get_status() );

		$this->assertSame( 'Yoast Title', PostData::get_field( $post_id, 'title' ) );
		$this->assertNotEmpty(
			get_post_meta( $post_id, Constants::META_YOAST_MIGRATED, true )
		);
	}

	/**
	 * Ensure Yoast multi-keyphrase data is split to focus + long-tail.
	 *
	 * @return void
	 */
	public function test_import_splits_yoast_multi_keyphrases(): void {
		$this->acting_as_admin();

		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_yoast_wpseo_focuskw', 'main keyword' );
		update_post_meta(
			$post_id,
			'_yoast_wpseo_focuskeywords',
			maybe_serialize(
				array(
					array( 'keyword' => 'long tail keyword' ),
					array( 'keyword' => 'seo' ),
				)
			)
		);

		$response = $this->rest_post( '/airygen/v1/migration/yoast/import', array() );
		$this->assertSame( 200, $response->get_status() );

		$this->assertSame( 'seo', PostData::get_field( $post_id, 'focusKeyphrase' ) );
		$this->assertSame( 'main keyword, long tail keyword', PostData::get_field( $post_id, 'focusLongTail' ) );
	}

	/**
	 * Ensure settings import endpoint responds.
	 *
	 * @return void
	 */
	public function test_import_settings_returns_summary(): void {
		$this->acting_as_admin();

		$response = $this->rest_post( '/airygen/v1/migration/yoast/settings', array() );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'settings', $data );
	}

	/**
	 * Ensure Yoast settings import maps webmaster tokens and RSS signatures.
	 *
	 * @return void
	 */
	public function test_import_settings_maps_yoast_webmaster_and_rss(): void {
		$this->acting_as_admin();

		update_option(
			'wpseo',
			array(
				'googleverify' => 'yoast-google-token',
				'msverify'     => 'yoast-bing-token',
			)
		);

		update_option(
			'wpseo_titles',
			array(
				'rssbefore' => 'Before %%sitename%%',
				'rssafter'  => 'After %%title%%',
			)
		);

		update_option(
			'wpseo_social',
			array(
				'opengraph' => '0',
				'twitter'   => '0',
			)
		);

		$this->rest_post( '/airygen/v1/migration/yoast/settings', array() );

		$webmaster = SiteVerificationSettings::get();
		$rss       = RssFeedSignatureSettings::get();
		$social    = SocialSettings::get();

		$this->assertSame( 'yoast-google-token', $webmaster['google'] );
		$this->assertSame( 'yoast-bing-token', $webmaster['bing'] );
		$this->assertStringContainsString( '%site_name%', $rss['before_content'] );
		$this->assertStringContainsString( '%post_title%', $rss['after_content'] );
		$this->assertTrue( $rss['enabled'] );
		$this->assertFalse( $social['og']['enabled'] );
		$this->assertFalse( $social['twitter']['enabled'] );
	}

	/**
	 * Ensure redirects import marks posts.
	 *
	 * @return void
	 */
	public function test_import_redirects_marks_posts(): void {
		$this->acting_as_admin();

		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_yoast_wpseo_redirect', 'https://example.com/new-url' );

		$response = $this->rest_post( '/airygen/v1/migration/yoast/redirects', array() );
		$this->assertSame( 200, $response->get_status() );

		$this->assertNotEmpty(
			get_post_meta( $post_id, Constants::META_YOAST_REDIRECT_MIGRATED, true )
		);
	}

	/**
	 * Ensure Rank Math status endpoint returns progress payload.
	 *
	 * @return void
	 */
	public function test_rankmath_status_returns_progress(): void {
		$this->acting_as_admin();

		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, Constants::META_RANK_MATH_MIGRATED, gmdate( 'c' ) );

		$response = $this->rest_get( '/airygen/v1/migration/rankmath' );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'progress', $data );
		$this->assertSame( 1, $data['progress']['migrated'] ?? null );
	}

	/**
	 * Ensure Rank Math import endpoint migrates post meta.
	 *
	 * @return void
	 */
	public function test_rankmath_import_migrates_post_meta(): void {
		$this->acting_as_admin();

		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'rank_math_title', 'Rank Math Title' );

		$response = $this->rest_post( '/airygen/v1/migration/rankmath/import', array() );
		$this->assertSame( 200, $response->get_status() );

		$this->assertSame( 'Rank Math Title', PostData::get_field( $post_id, 'title' ) );
		$this->assertNotEmpty(
			get_post_meta( $post_id, Constants::META_RANK_MATH_MIGRATED, true )
		);
	}

	/**
	 * Ensure Rank Math multi-keyphrase data is split to focus + long-tail.
	 *
	 * @return void
	 */
	public function test_rankmath_import_splits_multi_keyphrases(): void {
		$this->acting_as_admin();

		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'rank_math_focus_keyword', 'very long keyword, short, medium phrase' );

		$response = $this->rest_post( '/airygen/v1/migration/rankmath/import', array() );
		$this->assertSame( 200, $response->get_status() );

		$this->assertSame( 'short', PostData::get_field( $post_id, 'focusKeyphrase' ) );
		$this->assertSame( 'very long keyword, medium phrase', PostData::get_field( $post_id, 'focusLongTail' ) );
	}

	/**
	 * Ensure Rank Math settings import endpoint responds.
	 *
	 * @return void
	 */
	public function test_rankmath_import_settings_returns_summary(): void {
		$this->acting_as_admin();

		$response = $this->rest_post( '/airygen/v1/migration/rankmath/settings', array() );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'settings', $data );
	}

	/**
	 * Ensure Rank Math settings import reads canonical option names and maps extras.
	 *
	 * @return void
	 */
	public function test_rankmath_import_settings_maps_webmaster_and_rss_from_hyphen_options(): void {
		$this->acting_as_admin();

		update_option(
			'rank-math-options-general',
			array(
				'google_verify'      => 'rank-google-token',
				'pinterest_verify'   => 'rank-pinterest-token',
				'rss_before_content' => 'Before %sitename%',
			)
		);

		$this->rest_post( '/airygen/v1/migration/rankmath/settings', array() );

		$webmaster = SiteVerificationSettings::get();
		$rss       = RssFeedSignatureSettings::get();

		$this->assertSame( 'rank-google-token', $webmaster['google'] );
		$this->assertSame( 'rank-pinterest-token', $webmaster['pinterest'] );
		$this->assertStringContainsString( '%site_name%', $rss['before_content'] );
		$this->assertTrue( $rss['enabled'] );
	}

	/**
	 * Ensure Rank Math redirects import handles table data.
	 *
	 * @return void
	 */
	public function test_rankmath_import_redirects_adds_rules(): void {
		global $wpdb;

		$this->acting_as_admin();
		delete_option( 'airygen_rank_math_redirect_cursor' );

		$table = $wpdb->prefix . 'rank_math_redirections';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange -- Test fixture setup.
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Test fixture setup.
			"CREATE TABLE IF NOT EXISTS {$table} (
				id bigint(20) unsigned NOT NULL auto_increment,
				sources longtext NOT NULL,
				url_to text NOT NULL,
				header_code smallint(4) unsigned NOT NULL,
				status varchar(25) NOT NULL default 'active',
				PRIMARY KEY  (id)
			)"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Test fixture setup.
			$table,
			array(
				'sources'     => maybe_serialize(
					array(
						array(
							'pattern'    => '/old-url',
							'comparison' => 'exact',
						),
					)
				),
				'url_to'      => 'https://example.com/new-url',
				'header_code' => 301,
				'status'      => 'active',
			)
		);

		$response = $this->rest_post( '/airygen/v1/migration/rankmath/redirects', array() );
		$this->assertSame( 200, $response->get_status() );

		$rules = RedirectsSettings::get_rules();
		$this->assertNotEmpty( $rules['rules'] ?? array() );
	}

	/**
	 * Ensure AIOSEO status endpoint returns progress payload.
	 *
	 * @return void
	 */
	public function test_aioseo_status_returns_progress(): void {
		$this->acting_as_admin();

		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, Constants::META_AIOSEO_MIGRATED, gmdate( 'c' ) );

		$response = $this->rest_get( '/airygen/v1/migration/aioseo' );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'progress', $data );
		$this->assertSame( 1, $data['progress']['migrated'] ?? null );
	}

	/**
	 * Ensure AIOSEO import endpoint migrates post meta.
	 *
	 * @return void
	 */
	public function test_aioseo_import_migrates_post_meta(): void {
		$this->acting_as_admin();

		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_aioseo_title', 'AIOSEO Title' );

		$response = $this->rest_post( '/airygen/v1/migration/aioseo/import', array() );
		$this->assertSame( 200, $response->get_status() );

		$this->assertSame( 'AIOSEO Title', PostData::get_field( $post_id, 'title' ) );
		$this->assertNotEmpty(
			get_post_meta( $post_id, Constants::META_AIOSEO_MIGRATED, true )
		);
	}

	/**
	 * Ensure AIOSEO import falls back to legacy v3 post meta keys.
	 *
	 * @return void
	 */
	public function test_aioseo_import_supports_legacy_meta_fallbacks(): void {
		$this->acting_as_admin();

		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_aioseop_title', 'Legacy AIO title' );
		update_post_meta( $post_id, '_aioseop_custom_link', 'https://example.com/legacy-canonical' );
		update_post_meta( $post_id, '_aioseop_noindex', 'on' );
		update_post_meta( $post_id, '_aioseop_nofollow', 'on' );

		$this->rest_post( '/airygen/v1/migration/aioseo/import', array() );

		$this->assertSame( 'Legacy AIO title', PostData::get_field( $post_id, 'title' ) );
		$this->assertSame( 'https://example.com/legacy-canonical', PostData::get_field( $post_id, 'canonical' ) );
		$this->assertSame( 'noindex,nofollow', PostData::get_field( $post_id, 'robots' ) );
	}

	/**
	 * Ensure AIOSEO multi-keyphrase data is split to focus + long-tail.
	 *
	 * @return void
	 */
	public function test_aioseo_import_splits_multi_keyphrases(): void {
		$this->acting_as_admin();

		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_aioseo_keywords', 'long phrase, tiny, medium keyword' );

		$response = $this->rest_post( '/airygen/v1/migration/aioseo/import', array() );
		$this->assertSame( 200, $response->get_status() );

		$this->assertSame( 'tiny', PostData::get_field( $post_id, 'focusKeyphrase' ) );
		$this->assertSame( 'long phrase, medium keyword', PostData::get_field( $post_id, 'focusLongTail' ) );
	}

	/**
	 * Ensure AIOSEO settings import endpoint responds.
	 *
	 * @return void
	 */
	public function test_aioseo_import_settings_returns_summary(): void {
		$this->acting_as_admin();

		update_option(
			'aioseo_options',
			wp_json_encode(
				array(
					'searchAppearance' => array(
						'global' => array(
							'separator' => '&raquo;',
							'siteTitle' => '#site_title #separator_sa #tagline',
						),
					),
				)
			)
		);

		$response = $this->rest_post( '/airygen/v1/migration/aioseo/settings', array() );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'settings', $data );
	}

	/**
	 * Ensure AIOSEO settings import maps webmaster and RSS fields.
	 *
	 * @return void
	 */
	public function test_aioseo_import_settings_maps_webmaster_and_rss(): void {
		$this->acting_as_admin();

		update_option(
			'aioseo_options',
			wp_json_encode(
				array(
					'siteVerification' => array(
						'google'    => 'aio-google-token',
						'baidu'     => 'aio-baidu-token',
						'pinterest' => 'aio-pinterest-token',
					),
					'rssContent'       => array(
						'before' => 'Before #site_title',
						'after'  => 'After #post_title',
					),
				)
			)
		);

		$this->rest_post( '/airygen/v1/migration/aioseo/settings', array() );

		$webmaster = SiteVerificationSettings::get();
		$rss       = RssFeedSignatureSettings::get();

		$this->assertSame( 'aio-google-token', $webmaster['google'] );
		$this->assertSame( 'aio-baidu-token', $webmaster['baidu'] );
		$this->assertSame( 'aio-pinterest-token', $webmaster['pinterest'] );
		$this->assertStringContainsString( '%site_name%', $rss['before_content'] );
		$this->assertStringContainsString( '%post_title%', $rss['after_content'] );
		$this->assertTrue( $rss['enabled'] );
	}

	/**
	 * Ensure AIOSEO redirects import handles table data.
	 *
	 * @return void
	 */
	public function test_aioseo_import_redirects_adds_rules(): void {
		global $wpdb;

		$this->acting_as_admin();
		delete_option( 'airygen_aioseo_redirect_cursor' );

		$table = $wpdb->prefix . 'aioseo_redirects';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange -- Test fixture setup.
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Test fixture setup.
			"DROP TABLE IF EXISTS {$table}"
		);
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Test fixture setup.
			"CREATE TABLE IF NOT EXISTS {$table} (
				id bigint(20) unsigned NOT NULL auto_increment,
				source_url text NOT NULL,
				target_url text NOT NULL,
				http_code smallint(4) unsigned NOT NULL,
				status varchar(25) NOT NULL default 'active',
				regex tinyint(1) NOT NULL default 0,
				PRIMARY KEY  (id)
			)"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Test fixture setup.
			$table,
			array(
				'source_url' => '/aio-old',
				'target_url' => 'https://example.com/aio-new',
				'http_code'  => 301,
				'status'     => 'active',
				'regex'      => 0,
			)
		);

		$response = $this->rest_post( '/airygen/v1/migration/aioseo/redirects', array() );
		$this->assertSame( 200, $response->get_status() );

		$rules = RedirectsSettings::get_rules();
		$this->assertNotEmpty( $rules['rules'] ?? array() );
	}

	/**
	 * Ensure SEOPress status endpoint returns progress payload.
	 *
	 * @return void
	 */
	public function test_seopress_status_returns_progress(): void {
		$this->acting_as_admin();

		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, Constants::META_SEOPRESS_MIGRATED, gmdate( 'c' ) );

		$response = $this->rest_get( '/airygen/v1/migration/seopress' );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'progress', $data );
		$this->assertSame( 1, $data['progress']['migrated'] ?? null );
	}

	/**
	 * Ensure SEOPress import endpoint migrates post meta.
	 *
	 * @return void
	 */
	public function test_seopress_import_migrates_post_meta(): void {
		$this->acting_as_admin();

		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_seopress_titles_title', 'SEOPress Title' );

		$response = $this->rest_post( '/airygen/v1/migration/seopress/import', array() );
		$this->assertSame( 200, $response->get_status() );

		$this->assertSame( 'SEOPress Title', PostData::get_field( $post_id, 'title' ) );
		$this->assertNotEmpty(
			get_post_meta( $post_id, Constants::META_SEOPRESS_MIGRATED, true )
		);
	}

	/**
	 * Ensure SEOPress multi-keyphrase data is split to focus + long-tail.
	 *
	 * @return void
	 */
	public function test_seopress_import_splits_multi_keyphrases(): void {
		$this->acting_as_admin();

		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_seopress_analysis_target_kw', 'long phrase, tiny, medium keyword' );

		$response = $this->rest_post( '/airygen/v1/migration/seopress/import', array() );
		$this->assertSame( 200, $response->get_status() );

		$this->assertSame( 'tiny', PostData::get_field( $post_id, 'focusKeyphrase' ) );
		$this->assertSame( 'long phrase, medium keyword', PostData::get_field( $post_id, 'focusLongTail' ) );
	}

	/**
	 * Ensure SEOPress settings import endpoint responds.
	 *
	 * @return void
	 */
	public function test_seopress_import_settings_returns_summary(): void {
		$this->acting_as_admin();

		update_option(
			'seopress_titles_option_name',
			array(
				'seopress_titles_home_site_title' => '%%post_title%% %%sep%% %%sitetitle%%',
			)
		);

		$response = $this->rest_post( '/airygen/v1/migration/seopress/settings', array() );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'settings', $data );
	}

	/**
	 * Ensure SEOPress settings import maps webmaster and RSS fields.
	 *
	 * @return void
	 */
	public function test_seopress_import_settings_maps_webmaster_and_rss(): void {
		$this->acting_as_admin();

		update_option(
			'seopress_advanced_option_name',
			array(
				'seopress_advanced_advanced_google' => 'seopress-google-token',
				'seopress_advanced_advanced_baidu'  => 'seopress-baidu-token',
			)
		);
		update_option(
			'seopress_pro_option_name',
			array(
				'seopress_rss_before_html' => 'Before %%sitetitle%%',
				'seopress_rss_after_html'  => 'After %%post_title%%',
			)
		);

		$this->rest_post( '/airygen/v1/migration/seopress/settings', array() );

		$webmaster = SiteVerificationSettings::get();
		$rss       = RssFeedSignatureSettings::get();

		$this->assertSame( 'seopress-google-token', $webmaster['google'] );
		$this->assertSame( 'seopress-baidu-token', $webmaster['baidu'] );
		$this->assertStringContainsString( '%site_name%', $rss['before_content'] );
		$this->assertStringContainsString( '%post_title%', $rss['after_content'] );
		$this->assertTrue( $rss['enabled'] );
	}

	/**
	 * Ensure SEOPress redirects import adds rules.
	 *
	 * @return void
	 */
	public function test_seopress_import_redirects_adds_rules(): void {
		$this->acting_as_admin();

		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_seopress_redirections_enabled', 'yes' );
		update_post_meta( $post_id, '_seopress_redirections_type', 302 );
		update_post_meta( $post_id, '_seopress_redirections_value', 'https://example.com/seopress-new' );

		$response = $this->rest_post( '/airygen/v1/migration/seopress/redirects', array() );
		$this->assertSame( 200, $response->get_status() );

		$rules = RedirectsSettings::get_rules();
		$this->assertNotEmpty( $rules['rules'] ?? array() );
		$this->assertNotEmpty(
			get_post_meta( $post_id, Constants::META_SEOPRESS_REDIRECT_MIGRATED, true )
		);
	}
}
