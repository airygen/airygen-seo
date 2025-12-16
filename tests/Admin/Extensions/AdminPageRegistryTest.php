<?php
/**
 * Tests for admin page registry screen ID resolution.
 *
 * @package AirygenTest\Admin\Extensions
 */

declare(strict_types=1);

namespace AirygenTest\Admin\Extensions;

use Airygen\Admin\Extensions\AdminPageRegistry;
use AirygenTest\BaseTestCase;

final class AdminPageRegistryTest extends BaseTestCase {

	public function test_resolves_extension_pages_for_airygen_seo_hook_suffix(): void {
		$callback = static function ( array $pages ): array {
			$pages[] = array(
				'key'        => 'ai',
				'slug'       => 'airygen-ai',
				'title'      => 'AI Toolkit',
				'capability' => 'manage_options',
				'order'      => 25,
			);

			return $pages;
		};

		add_filter( 'airygen_admin_pages', $callback );

		try {
			$this->assertContains(
				'airygen-seo_page_airygen-ai',
				AdminPageRegistry::screen_ids()
			);
			$this->assertSame(
				'ai',
				AdminPageRegistry::resolve_current_page( 'airygen-seo_page_airygen-ai' )
			);
		} finally {
			remove_filter( 'airygen_admin_pages', $callback );
		}
	}
}
