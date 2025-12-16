<?php
/**
 * Tests for Code Snippets settings storage.
 *
 * @package AirygenTest\Modules\CodeSnippetManager\Admin
 */

declare(strict_types=1);

namespace AirygenTest\Modules\CodeSnippetManager\Admin;

use Airygen\Modules\CodeSnippetManager\Admin\Settings;
use AirygenTest\BaseTestCase;

/**
 * @covers \Airygen\Modules\CodeSnippetManager\Admin\Settings
 */
final class SettingsTest extends BaseTestCase {

	public function set_up(): void {
		parent::set_up();
		delete_option( 'airygen_code_snippet_manager' );
	}

	public function tear_down(): void {
		delete_option( 'airygen_code_snippet_manager' );
		parent::tear_down();
	}

	public function test_defaults_seed_correctly(): void {
		Settings::ensure_exists();

		$config = Settings::get();
		$this->assertSame( array(), $config['snippets'] );
	}

	public function test_update_sanitizes_payload(): void {
		Settings::update(
			array(
				'snippets' => array(
					array(
						'id'          => 'A-1',
						'enabled'     => true,
						'description' => 'Script with src',
						'code'        => '<script async src="https://example.com/example.js"></script>', // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
						'placement'   => 'head',
					),
					array(
						'id'          => 'B-2',
						'enabled'     => true,
						'description' => 'Style tag (blocked)',
						'code'        => '<style>body{color:red}</style>',
						'placement'   => 'head',
					),
					array(
						'id'          => 'C-3',
						'enabled'     => true,
						'description' => 'Iframe (blocked)',
						'code'        => '<iframe src="https://example.com"></iframe>',
						'placement'   => 'body',
					),
					array(
						'id'          => 'D-4',
						'enabled'     => true,
						'description' => 'Noscript (blocked)',
						'code'        => '<noscript>Fallback</noscript>',
						'placement'   => 'body',
					),
					array(
						'id'          => 'E-5',
						'enabled'     => true,
						'description' => 'Inline JS',
						'code'        => 'console.log("safe");',
						'placement'   => 'footer',
					),
					array(
						'id'          => 'F-6',
						'enabled'     => true,
						'description' => 'Wrapped inline JS',
						'code'        => '<script>alert("ok");</script>',
						'placement'   => 'footer',
					),
				),
			)
		);

		$config = Settings::get();
		$this->assertCount( 3, $config['snippets'] );
		$this->assertSame( 'a-1', $config['snippets'][0]['id'] );
		$this->assertStringContainsString( 'example.com/example.js', $config['snippets'][0]['code'] );
		$this->assertSame( 'e-5', $config['snippets'][1]['id'] );
		$this->assertSame( 'console.log("safe");', $config['snippets'][1]['code'] );
		$this->assertSame( 'f-6', $config['snippets'][2]['id'] );
		$this->assertSame( '<script>alert("ok");</script>', $config['snippets'][2]['code'] );
	}
}
