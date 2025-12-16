<?php
/**
 * Post type helper for IndexNow auto submissions.
 *
 * @package Airygen\Modules\InstantIndexing\Runtime
 */

declare(strict_types=1);

namespace Airygen\Modules\InstantIndexing\Runtime;

use Airygen\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves which post types qualify for IndexNow submissions.
 */
final class PostTypes {

	/**
	 * Retrieve whitelisted post type slugs.
	 *
	 * @return array<int, string>
	 */
	public static function names(): array {
		$types = get_post_types(
			array(
				'public'            => true,
				'show_in_nav_menus' => true,
			),
			'names'
		);

		unset( $types['attachment'] );

		/**
		 * Filter which post types should trigger IndexNow submissions.
		 *
		 * @param array<int, string> $types Supported post types.
		 */
		return apply_filters( Constants::HOOK_INDEXNOW_POST_TYPES, array_values( $types ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook constant is already plugin-prefixed.
	}

	/**
	 * Whether the provided post type is supported.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	public static function supports( string $post_type ): bool {
		return in_array( $post_type, self::names(), true );
	}
}
