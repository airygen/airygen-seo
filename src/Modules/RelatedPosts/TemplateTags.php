<?php
/**
 * Template tags for rendering Airygen related posts.
 *
 * @package Airygen\Modules\RelatedPosts
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\RelatedPosts\Public\Hooks;

if ( ! function_exists( 'airygen_get_related_posts' ) ) {
	/**
	 * Retrieve related posts markup for a post.
	 *
	 * @param int $post_id Optional target post ID.
	 *
	 * @return string
	 */
	function airygen_get_related_posts( int $post_id = 0 ): string {
		return Hooks::render_for_template( $post_id );
	}
}

if ( ! function_exists( 'airygen_the_related_posts' ) ) {
	/**
	 * Echo related posts markup for a post.
	 *
	 * @param int $post_id Optional target post ID.
	 *
	 * @return void
	 */
	function airygen_the_related_posts( int $post_id = 0 ): void {
		echo airygen_get_related_posts( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
