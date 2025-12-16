<?php
/**
 * Tests for Site Verification settings storage.
 *
 * @package AirygenTest\Modules\SiteVerification\Admin
 */

declare(strict_types=1);

namespace AirygenTest\Modules\SiteVerification\Admin;

use Airygen\Modules\SiteVerification\Admin\Settings;
use AirygenTest\BaseTestCase;

/**
 * @covers \Airygen\Modules\SiteVerification\Admin\Settings
 */
final class SettingsTest extends BaseTestCase {

	public function set_up(): void {
		parent::set_up();
		delete_option( 'airygen_site_verification' );
	}

	public function tear_down(): void {
		delete_option( 'airygen_site_verification' );
		parent::tear_down();
	}

	public function test_defaults_seed_correctly(): void {
		Settings::ensure_exists();

		$config = Settings::get();
		$this->assertSame( '', $config['google'] );
		$this->assertSame( '', $config['bing'] );
		$this->assertSame( '', $config['yandex'] );
		$this->assertSame( '', $config['baidu'] );
		$this->assertSame( '', $config['pinterest'] );
	}

	public function test_update_sanitizes_payload(): void {
		Settings::update(
			array(
				'google'    => '  <b>google-token</b>  ',
				'bing'      => "bing-token\n",
				'yandex'    => '<script>alert(1)</script>yandex',
				'baidu'     => 'baidu-token',
				'pinterest' => 'pinterest-token',
			)
		);

		$config = Settings::get();
		$this->assertSame( 'google-token', $config['google'] );
		$this->assertSame( 'bing-token', $config['bing'] );
		$this->assertSame( 'yandex', $config['yandex'] );
		$this->assertSame( 'baidu-token', $config['baidu'] );
		$this->assertSame( 'pinterest-token', $config['pinterest'] );
	}
}
