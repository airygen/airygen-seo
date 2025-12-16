<?php
/**
 * Tests for llms render cache behavior.
 *
 * @package AirygenTest\Modules\LlmsTxt\Infrastructure
 */

declare(strict_types=1);

namespace AirygenTest\Modules\LlmsTxt\Infrastructure;

use Airygen\Modules\LlmsTxt\Infrastructure\RenderCache;
use AirygenTest\BaseTestCase;
use ReflectionMethod;

/**
 * @covers \Airygen\Modules\LlmsTxt\Infrastructure\RenderCache
 */
final class RenderCacheTest extends BaseTestCase {

	public function set_up(): void {
		parent::set_up();
		RenderCache::invalidate_all();
	}

	public function tear_down(): void {
		RenderCache::invalidate_all();
		parent::tear_down();
	}

	/**
	 * Cache should round-trip base content.
	 *
	 * @return void
	 */
	public function test_set_and_get_round_trip_for_base_target(): void {
		RenderCache::set( 'base', 'cached llms base content' );

		$this->assertSame( 'cached llms base content', RenderCache::get( 'base' ) );
	}

	/**
	 * Invalidating all cache files should remove both base and extension entries.
	 *
	 * @return void
	 */
	public function test_invalidate_all_removes_base_and_extension_files(): void {
		RenderCache::set( 'base', 'base content' );
		RenderCache::set( 'extension-ext_123', 'extension content' );

		$this->assertSame( 'base content', RenderCache::get( 'base' ) );
		$this->assertSame( 'extension content', RenderCache::get( 'extension-ext_123' ) );

		RenderCache::invalidate_all();

		$this->assertNull( RenderCache::get( 'base' ) );
		$this->assertNull( RenderCache::get( 'extension-ext_123' ) );
	}

	/**
	 * Cache files should be isolated under the current blog directory.
	 *
	 * @return void
	 */
	public function test_cache_file_path_uses_current_blog_id_directory(): void {
		$method = new ReflectionMethod( RenderCache::class, 'cache_file_path' );
		$method->setAccessible( true );
		$path = $method->invoke( null, 'base' );

		$this->assertIsString( $path );
		$this->assertStringContainsString( '/airygen-cache/llms/' . get_current_blog_id() . '/', $path );
		$this->assertStringEndsWith( '/base.txt', $path );
	}
}
