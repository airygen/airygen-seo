<?php
/**
 * Tests for the Schema Markup JSON-LD builder.
 *
 * @package AirygenTest\Modules\SchemaMarkup\Domain
 */

declare(strict_types=1);

namespace AirygenTest\Modules\SchemaMarkup\Domain;

use Airygen\Modules\SchemaMarkup\Domain\Service\BuildJsonLd;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Airygen\Modules\SchemaMarkup\Domain\Service\BuildJsonLd
 */
class BuildJsonLdTest extends TestCase {

	/**
	 * Empty context payloads should return an empty array.
	 *
	 * @return void
	 */
	public function test_returns_empty_array_when_no_nodes(): void {
		$this->assertSame( array(), BuildJsonLd::from_context( array() ) );
	}

	/**
	 * Organization, website, webpage, breadcrumb, and article nodes should be emitted when eligible.
	 *
	 * @return void
	 */
	public function test_builds_complete_graph(): void {
		$payload = BuildJsonLd::from_context( $this->full_context() );

		$this->assertSame( 'https://schema.org', $payload['@context'] );
		$this->assertCount( 5, $payload['@graph'] );

		$types = array_map(
			static function ( array $node ): string {
				return (string) $node['@type'];
			},
			$payload['@graph']
		);

		$this->assertContains( 'Organization', $types );
		$this->assertContains( 'WebSite', $types );
		$this->assertContains( 'WebPage', $types );
		$this->assertContains( 'BreadcrumbList', $types );
		$this->assertContains( 'Article', $types );

		$webpage = null;
		foreach ( $payload['@graph'] as $node ) {
			if ( isset( $node['@type'] ) && 'WebPage' === $node['@type'] ) {
				$webpage = $node;
				break;
			}
		}
		$this->assertNotNull( $webpage );
		$this->assertSame(
			array( '@id' => 'https://example.com/post#breadcrumb' ),
			$webpage['breadcrumb'] ?? null
		);
	}

	/**
	 * Articles that fail the eligibility check should be excluded.
	 *
	 * @return void
	 */
	public function test_skips_ineligible_article_nodes(): void {
		$context                      = $this->full_context();
		$context['article']['status'] = 'draft';

		$payload = BuildJsonLd::from_context( $context );
		$this->assertCount( 4, $payload['@graph'] );
	}

	/**
	 * Article author should reference a dedicated graph node by @id when available.
	 *
	 * @return void
	 */
	public function test_links_article_author_to_dedicated_author_node(): void {
		$context                      = $this->full_context();
		$context['article']['author'] = array(
			'@id'    => 'https://example.com/author/admin#author',
			'type'   => 'Person',
			'name'   => 'Admin',
			'url'    => 'https://example.com/author/admin',
			'sameAs' => array(
				'https://x.com/admin',
				'https://linkedin.com/in/admin',
			),
		);

		$payload = BuildJsonLd::from_context( $context );
		$this->assertCount( 6, $payload['@graph'] );

		$article = null;
		$author  = null;
		foreach ( $payload['@graph'] as $node ) {
			if ( isset( $node['@type'] ) && 'Article' === $node['@type'] ) {
				$article = $node;
				continue;
			}
			if ( isset( $node['@id'] ) && 'https://example.com/author/admin#author' === $node['@id'] ) {
				$author = $node;
			}
		}

		$this->assertNotNull( $article );
		$this->assertNotNull( $author );
		$this->assertSame(
			array( '@id' => 'https://example.com/author/admin#author' ),
			$article['author']
		);
		$this->assertSame( 'Admin', $author['name'] );
		$this->assertSame( 'https://example.com/author/admin', $author['url'] );
		$this->assertSame(
			array( 'https://x.com/admin', 'https://linkedin.com/in/admin' ),
			$author['sameAs']
		);
	}

	/**
	 * Standalone author node should be emitted when provided by context.
	 *
	 * @return void
	 */
	public function test_builds_standalone_author_node(): void {
		$context            = $this->full_context();
		$context['article'] = array();
		$context['author']  = array(
			'@id'         => 'https://example.com/author/admin#author',
			'@type'       => 'Person',
			'name'        => 'Admin',
			'url'         => 'https://example.com/author/admin',
			'description' => 'Author bio',
			'sameAs'      => array( 'https://x.com/admin' ),
		);

		$payload = BuildJsonLd::from_context( $context );
		$this->assertCount( 5, $payload['@graph'] );

		$author = null;
		foreach ( $payload['@graph'] as $node ) {
			if ( isset( $node['@id'] ) && 'https://example.com/author/admin#author' === $node['@id'] ) {
				$author = $node;
				break;
			}
		}

		$this->assertNotNull( $author );
		$this->assertSame( 'Person', $author['@type'] );
		$this->assertSame( 'Admin', $author['name'] );
		$this->assertSame( 'Author bio', $author['description'] );
		$this->assertSame( array( 'https://x.com/admin' ), $author['sameAs'] );
	}

	/**
	 * Helper to build a complete context array.
	 *
	 * @return array<string,mixed>
	 */
	private function full_context(): array {
		return array(
			'organization' => array(
				'name' => 'Airygen',
				'url'  => 'https://example.com',
				'logo' => 'https://example.com/logo.png',
			),
			'website'      => array(
				'name'                  => 'Airygen',
				'url'                   => 'https://example.com',
				'language'              => 'en-US',
				'search_url'            => 'https://example.com/?s={search_term_string}',
				'search_query_param'    => 's',
				'potential_action_name' => 'Search Site',
			),
			'webpage'      => array(
				'name'        => 'Test Article',
				'url'         => 'https://example.com/post',
				'description' => 'Article page',
				'language'    => 'en-US',
			),
			'breadcrumb'   => array(
				'id'    => 'https://example.com/post#breadcrumb',
				'items' => array(
					array(
						'name' => 'Home',
						'url'  => 'https://example.com',
					),
					array(
						'name' => 'Post',
						'url'  => 'https://example.com/post',
					),
				),
			),
			'article'      => array(
				'headline'      => 'Test Article',
				'url'           => 'https://example.com/post',
				'description'   => 'Article description',
				'image'         => 'https://example.com/image.jpg',
				'datePublished' => '2023-01-01T00:00:00+00:00',
				'dateModified'  => '2023-01-02T00:00:00+00:00',
				'status'        => 'publish',
				'author'        => array(
					'name' => 'Author Name',
				),
				'publisher'     => array(
					'name' => 'Publisher',
					'logo' => 'https://example.com/logo.png',
				),
			),
		);
	}
}
