<?php
/**
 * Tests for Site Verification public hooks.
 *
 * @package AirygenTest\Modules\SiteVerification\Public
 */

declare(strict_types=1);

namespace AirygenTest\Modules\SiteVerification\Public;

use Airygen\Modules\SiteVerification\Admin\Settings;
use Airygen\Modules\SiteVerification\Public\Hooks;
use AirygenTest\BaseTestCase;

/**
 * @covers \Airygen\Modules\SiteVerification\Public\Hooks
 */
final class HooksTest extends BaseTestCase {

	public function set_up(): void {
		parent::set_up();
		delete_option( 'airygen_site_verification' );
	}

	public function tear_down(): void {
		delete_option( 'airygen_site_verification' );
		remove_all_actions( 'wp_head' );
		parent::tear_down();
	}

	public function test_emit_head_outputs_configured_verification_meta_tags(): void {
		Settings::update(
			array(
				'google'    => 'google-token',
				'bing'      => 'bing-token',
				'yandex'    => 'yandex-token',
				'baidu'     => 'baidu-token',
				'pinterest' => 'pinterest-token',
			)
		);

		ob_start();
		Hooks::emit_head();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="google-site-verification" content="google-token"', $output );
		$this->assertStringContainsString( 'name="msvalidate.01" content="bing-token"', $output );
		$this->assertStringContainsString( 'name="yandex-verification" content="yandex-token"', $output );
		$this->assertStringContainsString( 'name="baidu-site-verification" content="baidu-token"', $output );
		$this->assertStringContainsString( 'name="p:domain_verify" content="pinterest-token"', $output );
	}

	public function test_register_adds_wp_head_action(): void {
		Hooks::register();
		$this->assertNotFalse( has_action( 'wp_head', array( Hooks::class, 'emit_head' ) ) );
	}
}
