<?php
/**
 * WP-aware builder for webpage schema context.
 *
 * @package Airygen\Modules\SchemaMarkup\Public\ContextBuilders
 */

declare(strict_types=1);

namespace Airygen\Modules\SchemaMarkup\Public\ContextBuilders;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Post;
use WP_Term;
use WP_User;

/**
 * Builds WebPage context from current query data.
 */
final class WebPage {

	/**
	 * Build webpage context for current request.
	 *
	 * @param string $site_name Site name.
	 * @param string $site_desc Site description.
	 * @param string $locale    Site locale.
	 *
	 * @return array<string, mixed>
	 */
	public static function from_current_query( string $site_name, string $site_desc, string $locale ): array {
		$url = self::resolve_url();
		if ( '' === $url ) {
			$url = home_url( '/' );
		}

		$name = trim( wp_strip_all_tags( wp_get_document_title() ) );
		if ( '' === $name ) {
			$name = $site_name;
		}

		$description = trim( $site_desc );
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				$excerpt = trim( wp_strip_all_tags( (string) get_post_field( 'post_excerpt', $post->ID ) ) );
				if ( '' !== $excerpt ) {
					$description = $excerpt;
				}
			}
		}

		return array(
			'name'        => $name,
			'url'         => $url,
			'description' => '' !== $description ? $description : null,
			'language'    => $locale,
		);
	}

	/**
	 * Resolve canonical URL for current request.
	 */
	private static function resolve_url(): string {
		if ( is_singular() ) {
			$object = get_queried_object();
			if ( $object instanceof WP_Post ) {
				$url = get_permalink( $object );
				return is_string( $url ) ? $url : '';
			}
		}

		if ( is_author() ) {
			$object = get_queried_object();
			if ( $object instanceof WP_User ) {
				return get_author_posts_url( $object->ID, $object->user_nicename );
			}
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$object = get_queried_object();
			if ( $object instanceof WP_Term ) {
				$url = get_term_link( $object );
				return is_string( $url ) ? $url : '';
			}
		}

		if ( is_search() ) {
			return get_search_link();
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

		if ( is_home() || is_front_page() ) {
			return home_url( '/' );
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$path        = strtok( $request_uri, '?' );
		if ( false === $path || '' === $path ) {
			return home_url( '/' );
		}

		return home_url( $path );
	}
}
