<?php
/**
 * Builds markdown payloads for posts/pages.
 *
 * @package Airygen\Modules\MarkdownForAgents\Application
 */

declare(strict_types=1);

namespace Airygen\Modules\MarkdownForAgents\Application;

use Airygen\Support\Meta\PostData;
use League\HTMLToMarkdown\HtmlConverter;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stateless markdown exporter.
 */
final class MarkdownExporter {

	/**
	 * Export a post into markdown payload.
	 *
	 * @param int                 $post_id  Post ID.
	 * @param array<string,mixed> $settings Module settings.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function export( int $post_id, array $settings ): ?array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		$canonical = PostData::get_field( $post_id, 'canonical' );
		$canonical = is_string( $canonical ) && '' !== trim( $canonical )
		? trim( $canonical )
		: get_permalink( $post_id );

		$rendered_html = apply_filters( 'the_content', (string) $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core content filter.
		$summary       = has_excerpt( $post ) ? (string) $post->post_excerpt : wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 40 );
		$summary       = trim( preg_replace( '/\s+/', ' ', $summary ) ?? '' );

		$markdown_body = self::convert_html_to_markdown( $rendered_html );
		$title         = (string) get_the_title( $post );

		$sections   = array();
		$sections[] = '# ' . $title;
		if ( '' !== $summary ) {
			$sections[] = '## Summary' . "\n\n" . $summary;
		}
		$sections[] = '## Main Content' . "\n\n" . trim( $markdown_body );

		$markdown = trim( implode( "\n\n", array_filter( $sections, static fn( $part ) => is_string( $part ) && '' !== trim( $part ) ) ) );

		$frontmatter = '';
		if ( ! empty( $settings['include_frontmatter'] ) ) {
			$frontmatter = self::build_frontmatter( $post, $summary, (string) $canonical );
			$markdown    = $frontmatter . "\n\n" . $markdown;
		}

		return array(
			'post_id'             => $post_id,
			'post_type'           => (string) $post->post_type,
			'post_status'         => (string) $post->post_status,
			'locale'              => (string) get_locale(),
			'canonical_url'       => (string) $canonical,
			'title'               => $title,
			'excerpt'             => $summary,
			'frontmatter_yaml'    => $frontmatter,
			'markdown_content'    => $markdown,
			'content_hash'        => hash( 'sha256', $markdown ),
			'source_modified_gmt' => (string) $post->post_modified_gmt,
		);
	}

	/**
	 * Convert HTML into concise markdown.
	 *
	 * @param string $html Rendered HTML.
	 *
	 * @return string
	 */
	private static function convert_html_to_markdown( string $html ): string {
		$html      = self::strip_non_content_tags( $html );
		$converter = new HtmlConverter(
			array(
				'header_style'            => 'atx',
				'hard_break'              => false,
				'strip_tags'              => false,
				'strip_placeholder_links' => true,
				'list_item_style'         => '-',
				'suppress_errors'         => true,
				'remove_nodes'            => 'script style nav aside footer form noscript',
				'use_autolinks'           => false,
			)
		);

		$markdown = $converter->convert( $html );
		$markdown = preg_replace( "/\r\n?/", "\n", $markdown );
		$markdown = preg_replace( "/\n{3,}/", "\n\n", (string) $markdown );
		$markdown = preg_replace( "/[ \t]+\n/", "\n", (string) $markdown );

		return trim( (string) $markdown );
	}

	/**
	 * Build YAML frontmatter.
	 *
	 * @param WP_Post $post      Post object.
	 * @param string  $summary   Summary text.
	 * @param string  $canonical Canonical URL.
	 *
	 * @return string
	 */
	private static function build_frontmatter( WP_Post $post, string $summary, string $canonical ): string {
		$author = get_the_author_meta( 'display_name', (int) $post->post_author );

		$lines   = array();
		$lines[] = '---';
		$lines[] = 'title: ' . self::yaml_quote( get_the_title( $post ) );
		$lines[] = 'author: ' . self::yaml_quote( is_string( $author ) ? $author : '' );
		$lines[] = 'date: ' . self::yaml_quote( mysql2date( 'c', (string) $post->post_date_gmt, false ) );
		$lines[] = 'post_type: ' . self::yaml_quote( (string) $post->post_type );
		$lines[] = 'canonical: ' . self::yaml_quote( $canonical );
		$lines[] = 'description: ' . self::yaml_quote( $summary );
		$lines[] = '---';

		return implode( "\n", $lines );
	}

	/**
	 * Quote YAML value.
	 *
	 * @param string $value Raw value.
	 *
	 * @return string
	 */
	private static function yaml_quote( string $value ): string {
		$escaped = str_replace( '"', '\"', $value );
		return '"' . $escaped . '"';
	}

	/**
	 * Remove non-content tags.
	 *
	 * @param string $html Input HTML.
	 *
	 * @return string
	 */
	private static function strip_non_content_tags( string $html ): string {
		$patterns = array(
			'#<script\b[^>]*>.*?</script>#is',
			'#<style\b[^>]*>.*?</style>#is',
			'#<nav\b[^>]*>.*?</nav>#is',
			'#<aside\b[^>]*>.*?</aside>#is',
			'#<footer\b[^>]*>.*?</footer>#is',
			'#<form\b[^>]*>.*?</form>#is',
		);

		return (string) preg_replace( $patterns, '', $html );
	}
}
