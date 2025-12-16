<?php
/**
 * Tests for llms.txt admin settings behavior.
 *
 * @package AirygenTest\Modules\LlmsTxt\Admin
 */

declare(strict_types=1);

namespace AirygenTest\Modules\LlmsTxt\Admin;

use Airygen\Modules\LlmsTxt\Admin\Settings;
use Airygen\Modules\LlmsTxt\Infrastructure\RenderCache;
use AirygenTest\BaseTestCase;

/**
 * @covers \Airygen\Modules\LlmsTxt\Admin\Settings
 */
final class SettingsTest extends BaseTestCase {

	public function set_up(): void {
		parent::set_up();
		delete_option( 'airygen_llms_txt' );
		RenderCache::invalidate_all();
	}

	public function tear_down(): void {
		delete_option( 'airygen_llms_txt' );
		RenderCache::invalidate_all();
		parent::tear_down();
	}

	/**
	 * Saving llms settings should clear render cache files.
	 *
	 * @return void
	 */
	public function test_update_invalidates_existing_render_cache(): void {
		RenderCache::set( 'base', 'cached-before-update' );
		$this->assertSame( 'cached-before-update', RenderCache::get( 'base' ) );

		Settings::update(
			array(
				'enabled'        => true,
				'index_strategy' => 'curated_only',
				'min_word_count' => 0,
				'post_types'     => array( 'post', 'page' ),
				'sections'       => array(),
				'extensions'     => array(),
			)
		);

		$this->assertNull( RenderCache::get( 'base' ) );
	}
}
