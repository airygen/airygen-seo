<?php
/**
 * Tests for RSS Feed Signature settings storage.
 *
 * @package AirygenTest\Modules\RssFeedSignature\Admin
 */

declare(strict_types=1);

namespace AirygenTest\Modules\RssFeedSignature\Admin;

use Airygen\Modules\RssFeedSignature\Admin\Settings;
use AirygenTest\BaseTestCase;

/**
 * @covers \Airygen\Modules\RssFeedSignature\Admin\Settings
 */
final class SettingsTest extends BaseTestCase {

	public function set_up(): void {
		parent::set_up();
		delete_option( 'airygen_rss_feed_signature' );
	}

	public function tear_down(): void {
		delete_option( 'airygen_rss_feed_signature' );
		parent::tear_down();
	}

	public function test_defaults_seed_correctly(): void {
		Settings::ensure_exists();

		$config = Settings::get();
		$this->assertFalse( $config['enabled'] );
		$this->assertSame( '', $config['before_content'] );
		$this->assertSame( '', $config['after_content'] );
	}

	public function test_update_sanitizes_payload(): void {
		Settings::update(
			array(
				'enabled'        => true,
				'before_content' => ' <script>alert(1)</script><p><a href="https://example.com" onclick="evil()">Source</a></p> ',
				'after_content'  => '<strong>Thanks</strong><iframe src="https://evil.test"></iframe>',
			)
		);

		$config = Settings::get();

		$this->assertTrue( $config['enabled'] );
		$this->assertSame( '<p><a href="https://example.com">Source</a></p>', $config['before_content'] );
		$this->assertSame( '<strong>Thanks</strong>', $config['after_content'] );
	}
}
