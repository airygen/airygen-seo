<?php
/**
 * Tests for RSS Feed Signature public hooks.
 *
 * @package AirygenTest\Modules\RssFeedSignature\Public
 */

declare(strict_types=1);

namespace AirygenTest\Modules\RssFeedSignature\Public;

use Airygen\Modules\RssFeedSignature\Admin\Settings;
use Airygen\Modules\RssFeedSignature\Public\Hooks;
use AirygenTest\BaseTestCase;

/**
 * @covers \Airygen\Modules\RssFeedSignature\Public\Hooks
 */
final class HooksTest extends BaseTestCase {

	public function set_up(): void {
		parent::set_up();
		delete_option( 'airygen_rss_feed_signature' );
	}

	public function tear_down(): void {
		delete_option( 'airygen_rss_feed_signature' );
		remove_all_filters( 'the_content_feed' );
		remove_all_filters( 'the_excerpt_rss' );
		parent::tear_down();
	}

	public function test_filter_feed_content_wraps_with_configured_signatures(): void {
		Settings::update(
			array(
				'enabled'        => true,
				'before_content' => '<p>Before</p>',
				'after_content'  => '<p>After</p>',
			)
		);

		$filtered = Hooks::filter_feed_content( '<p>Body</p>' );

		$this->assertSame( "<p>Before</p>\n\n<p>Body</p>\n\n<p>After</p>", $filtered );
	}

	public function test_register_hooks_injects_signature_for_content_feed_filter(): void {
		Settings::update(
			array(
				'enabled'        => true,
				'before_content' => '<p>Signature</p>',
			)
		);

		Hooks::register();
		$filtered = apply_filters( 'the_content_feed', '<p>Body</p>', 'rss2' );

		$this->assertStringContainsString( '<p>Signature</p>', $filtered );
		$this->assertStringContainsString( '<p>Body</p>', $filtered );
	}
}
