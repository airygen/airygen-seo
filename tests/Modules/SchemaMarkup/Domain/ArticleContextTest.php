<?php
/**
 * Tests for ArticleContext.
 *
 * @package AirygenTest\Modules\SchemaMarkup\Domain
 */

declare(strict_types=1);

namespace AirygenTest\Modules\SchemaMarkup\Domain;

use Airygen\Modules\SchemaMarkup\Domain\Contexts\ArticleContext;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Airygen\Modules\SchemaMarkup\Domain\Contexts\ArticleContext
 */
class ArticleContextTest extends TestCase {

	/**
	 * Author sameAs URLs should be preserved when exporting context.
	 *
	 * @return void
	 */
	public function test_author_same_as_is_preserved(): void {
		$context = ArticleContext::from_payload(
			'Article',
			array(
				'headline' => 'Headline',
				'url'      => 'https://example.com/post',
			),
			array(
				'@id'    => 'https://example.com/author/admin#author',
				'name'   => 'Admin',
				'type'   => 'Person',
				'sameAs' => array(
					'https://x.com/admin',
					'https://linkedin.com/in/admin',
				),
			),
			array(
				'name' => 'Publisher',
			)
		);

		$payload = $context->to_array();
		$this->assertArrayHasKey( 'author', $payload );
		$this->assertSame(
			array(
				'https://x.com/admin',
				'https://linkedin.com/in/admin',
			),
			$payload['author']['sameAs']
		);
	}
}
