<?php
/**
 * Tests for Code Snippets public hooks.
 *
 * @package AirygenTest\Modules\CodeSnippetManager\Public
 */

declare(strict_types=1);

namespace AirygenTest\Modules\CodeSnippetManager\Public;

use Airygen\Modules\CodeSnippetManager\Admin\Settings;
use Airygen\Modules\CodeSnippetManager\Public\Hooks;
use AirygenTest\BaseTestCase;

/**
 * @covers \Airygen\Modules\CodeSnippetManager\Public\Hooks
 */
final class HooksTest extends BaseTestCase {

	public function set_up(): void {
		parent::set_up();
		delete_option( 'airygen_code_snippet_manager' );
	}

	public function tear_down(): void {
		delete_option( 'airygen_code_snippet_manager' );
		remove_all_actions( 'wp_head' );
		remove_all_actions( 'wp_body_open' );
		remove_all_actions( 'wp_footer' );
		parent::tear_down();
	}

	public function test_emit_head_outputs_script_snippet(): void {
		Settings::update(
			array(
				'snippets' => array(
					array(
						'id'          => 'head-1',
						'enabled'     => true,
						'description' => 'External script',
						'code'        => '<script async src="https://example.com/analytics.js"></script>', // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
						'placement'   => 'head',
					),
				),
			)
		);

		ob_start();
		Hooks::emit_head();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '<script async src="https://example.com/analytics.js"></script>', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
	}

	public function test_emit_head_wraps_raw_js_in_script_tag(): void {
		Settings::update(
			array(
				'snippets' => array(
					array(
						'id'          => 'head-1',
						'enabled'     => true,
						'description' => 'Inline JS',
						'code'        => 'console.log("hello");',
						'placement'   => 'head',
					),
				),
			)
		);

		ob_start();
		Hooks::emit_head();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'console.log("hello");', $output );
		$this->assertStringContainsString( '<script', $output );
	}

	public function test_emit_snippets_outputs_each_snippet_individually(): void {
		Settings::update(
			array(
				'snippets' => array(
					array(
						'id'          => 'head-1',
						'enabled'     => true,
						'description' => 'First',
						'code'        => '<script>console.log("first");</script>',
						'placement'   => 'head',
					),
					array(
						'id'          => 'head-2',
						'enabled'     => true,
						'description' => 'Second',
						'code'        => '<script>console.log("second");</script>',
						'placement'   => 'head',
					),
				),
			)
		);

		ob_start();
		Hooks::emit_head();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '<script>console.log("first");</script>', $output );
		$this->assertStringContainsString( '<script>console.log("second");</script>', $output );
	}

	public function test_emit_body_open_outputs_body_snippet(): void {
		Settings::update(
			array(
				'snippets' => array(
					array(
						'id'          => 'body-1',
						'enabled'     => true,
						'description' => 'Body',
						'code'        => '<script>console.log("body");</script>',
						'placement'   => 'body',
					),
				),
			)
		);

		ob_start();
		Hooks::emit_body_open();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '<script>console.log("body");</script>', $output );
	}

	public function test_emit_footer_outputs_footer_snippet(): void {
		Settings::update(
			array(
				'snippets' => array(
					array(
						'id'          => 'footer-1',
						'enabled'     => true,
						'description' => 'Footer',
						'code'        => '<script>console.log("footer");</script>',
						'placement'   => 'footer',
					),
				),
			)
		);

		ob_start();
		Hooks::emit_footer();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '<script>console.log("footer");</script>', $output );
	}

	public function test_disabled_snippet_is_not_output(): void {
		Settings::update(
			array(
				'snippets' => array(
					array(
						'id'          => 'head-1',
						'enabled'     => false,
						'description' => 'Disabled',
						'code'        => '<script>console.log("hidden");</script>',
						'placement'   => 'head',
					),
				),
			)
		);

		ob_start();
		Hooks::emit_head();
		$output = (string) ob_get_clean();

		$this->assertSame( '', $output );
	}
}
