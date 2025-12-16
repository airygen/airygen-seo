<?php
/**
 * Tests for the Sitemap XML builder.
 *
 * @package AirygenTest\Modules\Sitemap\Domain
 */

declare(strict_types=1);

namespace AirygenTest\Modules\Sitemap\Domain;

use Airygen\Modules\Sitemap\Domain\Dto\SitemapIndexEntry;
use Airygen\Modules\Sitemap\Domain\Dto\SitemapUrlEntry;
use Airygen\Modules\Sitemap\Domain\Service\BuildSitemap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Airygen\Modules\Sitemap\Domain\Service\BuildSitemap
 */
class BuildSitemapTest extends TestCase {

	/**
	 * The index renderer should include each location and normalized lastmod.
	 *
	 * @return void
	 */
	public function test_builds_sitemap_index_xml(): void {
		$xml = BuildSitemap::index(
			array(
				new SitemapIndexEntry( 'https://example.com/sitemap-posts.xml', '2024-01-01' ),
				new SitemapIndexEntry( 'https://example.com/sitemap-pages.xml', null ),
			)
		);

		$this->assertStringContainsString( '<sitemapindex', $xml );
		$this->assertStringContainsString( '<loc>https://example.com/sitemap-posts.xml</loc>', $xml );
		$this->assertStringContainsString( '<loc>https://example.com/sitemap-pages.xml</loc>', $xml );
		$this->assertStringContainsString( '2024-01-01T00:00:00+00:00', $xml );
	}

	/**
	 * The URL set renderer should include URL entries with optional lastmod values.
	 *
	 * @return void
	 */
	public function test_builds_urlset_xml(): void {
		$xml = BuildSitemap::urlset(
			array(
				new SitemapUrlEntry( 'https://example.com/post-1', '2024-02-01T02:00:00+00:00' ),
				new SitemapUrlEntry( 'https://example.com/post-2', null ),
			)
		);

		$this->assertStringContainsString( '<urlset', $xml );
		$this->assertStringContainsString( '<loc>https://example.com/post-1</loc>', $xml );
		$this->assertStringContainsString( '<lastmod>2024-02-01T02:00:00+00:00</lastmod>', $xml );
		$this->assertStringContainsString( '<loc>https://example.com/post-2</loc>', $xml );
	}
}
