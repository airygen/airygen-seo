<?php
/**
 * WP-aware builder for breadcrumb context.
 *
 * @package Airygen\Modules\SchemaMarkup\Public\ContextBuilders
 */

declare(strict_types=1);

namespace Airygen\Modules\SchemaMarkup\Public\ContextBuilders;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\Breadcrumbs\Public\TrailBuilder as BreadcrumbTrailBuilder;
use Airygen\Modules\Breadcrumbs\Public\TrailStore;
use Airygen\Modules\SchemaMarkup\Domain\Contexts\BreadcrumbContext;

/**
 * Builds breadcrumb context from the current query.
 */
final class Breadcrumb {

	/**
	 * Build breadcrumb context for current singular request.
	 *
	 * @param string $site_name Site display name.
	 * @param string $site_url  Canonical site URL.
	 */
	public static function from_current_query( string $site_name, string $site_url ): ?BreadcrumbContext {
		$current_url   = self::resolve_current_url();
		$breadcrumb_id = '' !== $current_url ? untrailingslashit( $current_url ) . '#breadcrumb' : null;

		$trail = TrailStore::current();
		if ( null === $trail ) {
			$trail = BreadcrumbTrailBuilder::from_current_query();
		}

		if ( null !== $trail ) {
			$items = $trail->to_schema_items();
			if ( ! empty( $items ) ) {
				$context = BreadcrumbContext::from_items( $items, $breadcrumb_id );
				return $context->is_empty() ? null : $context;
			}
		}

		if ( ! is_singular() ) {
			return null;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return null;
		}

		$items = array(
			array(
				'name' => $site_name,
				'url'  => trailingslashit( $site_url ),
			),
		);

		$ancestors = array_reverse( get_post_ancestors( $post_id ) );

		foreach ( $ancestors as $ancestor ) {
			$items[] = array(
				'name' => get_the_title( $ancestor ),
				'url'  => get_permalink( $ancestor ),
			);
		}

		$items[] = array(
			'name' => get_the_title( $post_id ),
			'url'  => get_permalink( $post_id ),
		);

		$context = BreadcrumbContext::from_items( $items, $breadcrumb_id );
		return $context->is_empty() ? null : $context;
	}

	/**
	 * Resolve current canonical URL.
	 */
	private static function resolve_current_url(): string {
		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			$url     = $post_id ? get_permalink( $post_id ) : '';
			return is_string( $url ) ? $url : '';
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			$url  = $term ? get_term_link( $term ) : '';
			return is_string( $url ) ? $url : '';
		}

		if ( is_author() ) {
			$author = get_queried_object();
			if ( $author instanceof \WP_User ) {
				return get_author_posts_url( $author->ID, $author->user_nicename );
			}
		}

		if ( is_post_type_archive() ) {
			$post_type = get_query_var( 'post_type' );
			if ( is_array( $post_type ) ) {
				$post_type = reset( $post_type );
			}
			if ( is_string( $post_type ) && '' !== $post_type ) {
				$url = get_post_type_archive_link( $post_type );
				return is_string( $url ) ? $url : '';
			}
		}

		if ( is_search() ) {
			return get_search_link();
		}

		if ( is_home() || is_front_page() ) {
			return trailingslashit( home_url( '/' ) );
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$path        = strtok( $request_uri, '?' );
		if ( false === $path || '' === $path ) {
			return trailingslashit( home_url( '/' ) );
		}

		return home_url( $path );
	}
}
