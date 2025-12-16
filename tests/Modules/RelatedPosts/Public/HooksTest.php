<?php
/**
 * Tests for Related Posts public hooks.
 *
 * @package AirygenTest\Modules\RelatedPosts\Public
 */

declare(strict_types=1);

namespace AirygenTest\Modules\RelatedPosts\Public;

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Modules\RelatedPosts\Admin\Settings;
use Airygen\Modules\RelatedPosts\Public\Hooks;
use AirygenTest\BaseTestCase;

/**
 * @covers \Airygen\Modules\RelatedPosts\Public\Hooks
 */
final class HooksTest extends BaseTestCase {

	public function set_up(): void {
		parent::set_up();
		ModuleSettings::ensure_exists();
		Settings::update( array() );
	}

	public function tear_down(): void {
		$modules                    = ModuleSettings::get();
		$modules['relatedPosts']    = false;
		$modules['linkSuggestions'] = false;
		ModuleSettings::update( $modules );
		Settings::update( array() );
		wp_dequeue_style( 'airygen-related-posts-inline' );
		wp_deregister_style( 'airygen-related-posts-inline' );
		parent::tear_down();
	}

	public function test_enqueue_assets_registers_inline_styles_when_output_can_run(): void {
		$modules                    = ModuleSettings::get();
		$modules['relatedPosts']    = true;
		$modules['linkSuggestions'] = true;
		ModuleSettings::update( $modules );

		Settings::update(
			array(
				'enabled'             => true,
				'auto_inject_enabled' => false,
				'title_color'         => '#112233',
			)
		);

		Hooks::enqueue_assets();

		$styles = wp_styles();
		$this->assertContains( 'airygen-related-posts-inline', $styles->queue );

		$css = implode( "\n", (array) $styles->get_data( 'airygen-related-posts-inline', 'after' ) );
		$this->assertStringContainsString( '.airygen-auto-related-posts__grid', $css );
		$this->assertStringContainsString( 'color:#112233', $css );
	}

	public function test_enqueue_assets_skips_when_output_is_disabled(): void {
		$modules                    = ModuleSettings::get();
		$modules['relatedPosts']    = true;
		$modules['linkSuggestions'] = true;
		ModuleSettings::update( $modules );

		Settings::update(
			array(
				'enabled'             => false,
				'auto_inject_enabled' => false,
			)
		);

		Hooks::enqueue_assets();

		$styles = wp_styles();
		$this->assertNotContains( 'airygen-related-posts-inline', $styles->queue );
	}
}
