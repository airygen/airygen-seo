<?php
/**
 * Extracts link information from post content and persists results.
 *
 * @package Airygen\Modules\LinkCounter\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkCounter\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content analysis service used by the link counter module.
 */
final class ContentProcessor {

	/**
	 * Storage layer.
	 *
	 * @var Storage
	 */
	private $storage;

	/**
	 * Normalized site host for internal link detection.
	 *
	 * @var string
	 */
	private $site_host;

	/**
	 * Site base URL without trailing slash.
	 *
	 * @var string
	 */
	private $site_base;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->storage   = new Storage();
		$this->site_base = untrailingslashit( home_url() );

		$parsed_host     = wp_parse_url( $this->site_base, PHP_URL_HOST );
		$this->site_host = is_string( $parsed_host ) ? strtolower( $parsed_host ) : '';
	}

	/**
	 * Parse and persist link data for the provided post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content Raw post content.
	 *
	 * @return void
	 */
	public function process( int $post_id, string $content ): void {
		// Do not run full `the_content` filters in async jobs.
		// Some front-end filter chains (e.g. WooCommerce Blocks) depend on
		// runtime-only functions that are unavailable in WP-Cron contexts.
		// Strategy: process raw saved content in both real-time save hooks and
		// backlog jobs for maximum stability. Backlog is for gap-filling, while
		// save_post provides near real-time updates for edited posts.
		$content = str_replace( ']]>', ']]&gt;', $content );

		$links  = $this->extract_links( $content );
		$counts = array(
			'internal_link_count' => 0,
			'external_link_count' => 0,
		);

		$new_links      = array();
		$self_permalink = $this->normalize_for_comparison( get_permalink( $post_id ) ? get_permalink( $post_id ) : '' );

		foreach ( $links as $raw_link ) {
			$comparison_value = $this->normalize_for_comparison( $raw_link );
			if ( '' !== $self_permalink && $comparison_value === $self_permalink ) {
				continue;
			}

			$type = $this->determine_type( $raw_link );
			if ( null === $type ) {
				continue;
			}

			$target_post_id = 0;
			if ( Link::TYPE_INTERNAL === $type ) {
				$target_post_id = $this->resolve_internal_post_id( $raw_link );
			}

			++$counts[ Link::TYPE_INTERNAL === $type ? 'internal_link_count' : 'external_link_count' ];

			$new_links[] = new Link(
				$this->normalize_for_storage( $raw_link ),
				$target_post_id,
				$type
			);
		}

		$previous_links = $this->get_stored_internal_links( $post_id );

		$this->storage->cleanup( $post_id );
		$this->storage->save_links( $post_id, $new_links );
		$this->storage->save_counts( $post_id, $counts );
		$this->storage->update_incoming_links( $post_id, array_merge( $new_links, $previous_links ) );
		$this->storage->mark_processed( $post_id );
	}

	/**
	 * Retrieve internal links currently stored for the post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, Link>
	 */
	public function get_stored_internal_links( int $post_id ): array {
		$links = $this->storage->get_links( $post_id );

		return array_filter(
			$links,
			static function ( Link $link ): bool {
				return Link::TYPE_INTERNAL === $link->get_type();
			}
		);
	}

	/**
	 * Extract all href values from a content string.
	 *
	 * @param string $content Post content.
	 * @return array<int, string>
	 */
	private function extract_links( string $content ): array {
		if ( false === stripos( $content, 'href' ) ) {
			return array();
		}

		$pattern = '#<a\s[^>]*href=("|\')(.*?)\1#i';
		if ( ! preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
			return array();
		}

		$links = array();
		foreach ( $matches as $match ) {
			if ( isset( $match[2] ) ) {
				$links[] = trim( (string) $match[2] );
			}
		}

		return $links;
	}

	/**
	 * Determine whether a link is internal or external.
	 *
	 * @param string $link Link URL.
	 * @return string|null
	 */
	private function determine_type( string $link ): ?string {
		$link = trim( $link );
		if ( '' === $link ) {
			return null;
		}

		$lower = strtolower( $link );
		if ( str_starts_with( $lower, '#' ) || str_starts_with( $lower, 'mailto:' ) || str_starts_with( $lower, 'tel:' ) || str_starts_with( $lower, 'javascript:' ) ) {
			return null;
		}

		$parsed = wp_parse_url( $link );

		$host = isset( $parsed['host'] ) ? strtolower( (string) $parsed['host'] ) : '';

		if ( '' === $host ) {
			if ( isset( $parsed['path'] ) && str_starts_with( (string) $parsed['path'], '/' ) ) {
				return Link::TYPE_INTERNAL;
			}

			return null;
		}

		if ( $this->site_host === $host ) {
			return Link::TYPE_INTERNAL;
		}

		return Link::TYPE_EXTERNAL;
	}

	/**
	 * Look up the post ID for an internal link.
	 *
	 * @param string $link Link URL.
	 * @return int
	 */
	private function resolve_internal_post_id( string $link ): int {
		$absolute = $this->to_absolute_url( $link );
		if ( '' === $absolute ) {
			return 0;
		}

		$post_id = url_to_postid( $absolute );
		if ( $post_id > 0 ) {
			return $post_id;
		}

		return 0;
	}

	/**
	 * Convert a link to an absolute URL for processing.
	 *
	 * @param string $link Link URL.
	 * @return string
	 */
	private function to_absolute_url( string $link ): string {
		$link = trim( $link );
		if ( '' === $link ) {
			return '';
		}

		if ( str_starts_with( $link, '//' ) ) {
			$scheme        = wp_parse_url( $this->site_base, PHP_URL_SCHEME );
			$parsed_scheme = $scheme ? $scheme : 'https';
			return $parsed_scheme . ':' . $link;
		}

		if ( str_starts_with( $link, '/' ) ) {
			return $this->site_base . $link;
		}

		if ( ! preg_match( '#^https?://#i', $link ) ) {
			return '';
		}

		return $link;
	}

	/**
	 * Normalize link for equality comparisons.
	 *
	 * @param string $link Link URL.
	 * @return string
	 */
	private function normalize_for_comparison( string $link ): string {
		$absolute = $this->to_absolute_url( $link );
		if ( '' === $absolute ) {
			return '';
		}

		$absolute = untrailingslashit( $absolute );
		$absolute = strtok( $absolute, '#' );

		return $absolute ? strtolower( $absolute ) : '';
	}

	/**
	 * Normalize link prior to storage.
	 *
	 * @param string $link Link URL.
	 * @return string
	 */
	private function normalize_for_storage( string $link ): string {
		$absolute = $this->to_absolute_url( $link );
		return $absolute ? esc_url_raw( $absolute ) : esc_url_raw( $link );
	}
}
