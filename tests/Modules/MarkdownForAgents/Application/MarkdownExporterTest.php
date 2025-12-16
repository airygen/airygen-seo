<?php
/**
 * Tests for Markdown exporter payloads.
 *
 * @package AirygenTest\Modules\MarkdownForAgents\Application
 */

declare(strict_types=1);

namespace AirygenTest\Modules\MarkdownForAgents\Application;

use Airygen\Modules\MarkdownForAgents\Application\MarkdownExporter;
use AirygenTest\BaseTestCase;

/**
 * @covers \Airygen\Modules\MarkdownForAgents\Application\MarkdownExporter
 */
final class MarkdownExporterTest extends BaseTestCase {

	/**
	 * Export should emit snake_case YAML front matter without legacy SEO section.
	 *
	 * @return void
	 */
	public function test_export_uses_snake_case_front_matter_keys(): void {
		$author_id = self::factory()->user->create(
			array(
				'display_name' => 'Markdown Author',
			)
		);
		$post_id   = self::factory()->post->create(
			array(
				'post_author'   => $author_id,
				'post_title'    => 'Markdown Snake Case',
				'post_content'  => '<p>Body content for markdown export.</p>',
				'post_excerpt'  => 'Short summary.',
				'post_status'   => 'publish',
				'post_type'     => 'post',
				'post_date_gmt' => '2026-03-09 01:02:03',
			)
		);

		$payload = MarkdownExporter::export(
			$post_id,
			array(
				'include_frontmatter' => true,
			)
		);

		$this->assertIsArray( $payload );
		$frontmatter = (string) $payload['frontmatter_yaml'];
		$markdown    = (string) $payload['markdown_content'];

		$this->assertStringContainsString( 'title: "Markdown Snake Case"', $frontmatter );
		$this->assertStringContainsString( 'author: "Markdown Author"', $frontmatter );
		$this->assertStringContainsString( 'date: "2026-03-09T01:02:03+00:00"', $frontmatter );
		$this->assertStringContainsString( 'post_type: "post"', $frontmatter );
		$this->assertStringContainsString( 'canonical: "', $frontmatter );
		$this->assertStringContainsString( 'description: "Short summary."', $frontmatter );
		$this->assertStringNotContainsString( 'Title: ', $frontmatter );
		$this->assertStringNotContainsString( 'FocusKeyword:', $frontmatter );
		$this->assertStringNotContainsString( 'LongTailKeyphrases:', $frontmatter );
		$this->assertStringNotContainsString( 'focus_keyword:', $frontmatter );
		$this->assertStringNotContainsString( 'long_tail_keyphrases:', $frontmatter );
		$this->assertStringNotContainsString( '## SEO Metadata', $markdown );
	}
}
