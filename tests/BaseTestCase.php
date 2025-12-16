<?php
/**
 * Base PHPUnit test case for Airygen SEO.
 *
 * @package AirygenTest
 */

declare(strict_types=1);

namespace AirygenTest;

use WP_Query;
use WP_UnitTestCase;

/**
 * Class BaseTestCase
 *
 * Provides common setup and utilities for unit tests.
 */
class BaseTestCase extends WP_UnitTestCase {

	/**
	 * Helper to set up global $post and $wp_query for a given post ID.
	 *
	 * @param int    $post_id   The post ID to set as the current post.
	 * @param string $post_type The post type, default is 'post'.
	 * @return void
	 */
	public function set_mock_post( int $post_id, string $post_type = 'post' ) {
		global $post, $wp_query;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_query = new WP_Query(
			array(
				'p'         => $post_id,
				'post_type' => $post_type,
			)
		);
		$wp_query->the_post();
		setup_postdata( $post );
	}
}
