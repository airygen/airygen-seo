<?php
/**
 * Builds TOC data from HTML content.
 *
 * @package Airygen\Modules\TableOfContents\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\TableOfContents\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parse HTML headings and generate TOC entries.
 */
final class TocBuilder {

	/**
	 * Build headings and inject IDs into content.
	 *
	 * @param string $content Raw HTML content.
	 * @param array<int, int> $levels Heading levels to include.
	 * @param string $prefix Anchor prefix.
	 * @param array<int, string> $exclude Exclusion fragments.
	 *
	 * @return array{content: string, headings: array<int, array{level: int, id: string, text: string}>}
	 */
	public static function build( string $content, array $levels, string $prefix, array $exclude ): array {
		if ( '' === trim( $content ) ) {
			return array(
				'content'  => $content,
				'headings' => array(),
			);
		}

		libxml_use_internal_errors( true );

		$doc     = new \DOMDocument( '1.0', 'UTF-8' );
		$wrapped = '<div>' . $content . '</div>';
		$doc->loadHTML( '<?xml encoding="utf-8" ?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

		$xpath = new \DOMXPath( $doc );

		$tag_query = array();
		foreach ( $levels as $level ) {
			$level = (int) $level;
			if ( $level < 1 || $level > 6 ) {
				continue;
			}
			$tag_query[] = sprintf( '//h%d', $level );
		}

		if ( empty( $tag_query ) ) {
			return array(
				'content'  => $content,
				'headings' => array(),
			);
		}

		$nodes = $xpath->query( implode( ' | ', $tag_query ) );
		if ( ! $nodes ) {
			return array(
				'content'  => $content,
				'headings' => array(),
			);
		}

		$existing_ids = array();
		$headings     = array();

		foreach ( $nodes as $node ) {
			if ( ! $node instanceof \DOMElement ) {
				continue;
			}

			$level = (int) substr( $node->nodeName, 1 ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property name.
			$text  = trim( $node->textContent ?? '' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property name.
			if ( '' === $text ) {
				continue;
			}

			if ( self::is_excluded( $text, $exclude ) ) {
				continue;
			}

			$id = $node->getAttribute( 'id' );
			if ( '' === $id ) {
				$id = self::unique_id( $text, $prefix, $existing_ids );
				$node->setAttribute( 'id', $id );
			} else {
				$id = trim( $id );
				if ( '' === $id ) {
					$id = self::unique_id( $text, $prefix, $existing_ids );
					$node->setAttribute( 'id', $id );
				}
			}

			if ( '' !== $id ) {
				$existing_ids[] = $id;
			}

			$headings[] = array(
				'level' => $level,
				'id'    => $id,
				'text'  => $text,
			);
		}

		$wrapper      = $doc->getElementsByTagName( 'div' )->item( 0 );
		$content_html = '';
		if ( $wrapper instanceof \DOMElement ) {
			foreach ( $wrapper->childNodes as $child ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property name.
				$content_html .= $doc->saveHTML( $child );
			}
		}

		return array(
			'content'  => $content_html,
			'headings' => $headings,
		);
	}

	/**
	 * Determine if a heading text should be excluded.
	 *
	 * @param string $text Heading text.
	 * @param array<int, string> $exclude Exclusion fragments.
	 *
	 * @return bool
	 */
	private static function is_excluded( string $text, array $exclude ): bool {
		if ( empty( $exclude ) ) {
			return false;
		}

		$haystack = strtolower( $text );
		foreach ( $exclude as $needle ) {
			$needle = trim( strtolower( $needle ) );
			if ( '' === $needle ) {
				continue;
			}

			if ( false !== strpos( $haystack, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Generate a unique anchor ID.
	 *
	 * @param string $text Heading text.
	 * @param string $prefix Prefix to apply.
	 * @param array<int, string> $existing Existing IDs.
	 *
	 * @return string
	 */
	private static function unique_id( string $text, string $prefix, array $existing ): string {
		$base = self::slugify( $text );
		if ( '' === $base ) {
			$base = 'section';
		}

		$id = $prefix . $base;
		if ( ! in_array( $id, $existing, true ) ) {
			return $id;
		}

		$counter = 2;
		while ( in_array( $id . '-' . $counter, $existing, true ) ) {
			++$counter;
		}

		return $id . '-' . $counter;
	}

	/**
	 * Generate a slug from heading text.
	 *
	 * @param string $text Heading text.
	 *
	 * @return string
	 */
	private static function slugify( string $text ): string {
		$normalized = strtolower( $text );
		$normalized = preg_replace( '/[^a-z0-9]+/i', '-', $normalized );
		$normalized = is_string( $normalized ) ? $normalized : '';
		$normalized = trim( $normalized, '-' );

		return $normalized;
	}
}
