<?php
/**
 * Template tags for Topic Cluster rendering.
 *
 * @package Airygen\Modules\TopicCluster
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\TopicCluster\Public\Hooks;

if ( ! function_exists( 'airygen_get_topic_cluster' ) ) {
	/**
	 * Retrieve Topic Cluster markup for a post.
	 *
	 * @param int $post_id Optional target post ID.
	 *
	 * @return string
	 */
	function airygen_get_topic_cluster( int $post_id = 0 ): string {
		return Hooks::render_for_template( $post_id );
	}
}

if ( ! function_exists( 'airygen_the_topic_cluster' ) ) {
	/**
	 * Echo Topic Cluster markup for a post.
	 *
	 * @param int $post_id Optional target post ID.
	 *
	 * @return void
	 */
	function airygen_the_topic_cluster( int $post_id = 0 ): void {
		echo airygen_get_topic_cluster( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
