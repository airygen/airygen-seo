<?php
/**
 * Shared helpers for determining which post types the link counter should track.
 *
 * @package Airygen\Modules\LinkCounter\Runtime
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkCounter\Runtime;

use Airygen\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post type helper.
 */
final class PostTypes {

	/**
	 * Retrieve the list of processable post types.
	 *
	 * @return array<int, string>
	 */
	public static function names(): array {
		$types = get_post_types(
			array(
				'public'            => true,
				'show_in_nav_menus' => true,
				'show_ui'           => true,
			),
			'names'
		);

		unset( $types['attachment'] );

		/**
		 * Allow customizing which post types should be analysed for link counts.
		 *
		 * @param array<int, string> $types Post type slugs.
		 */
		return apply_filters( Constants::HOOK_LINK_COUNTER_POST_TYPES, array_values( $types ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook constant is already plugin-prefixed.
	}

	/**
	 * Whether the provided post type should be analysed.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	public static function supports( string $post_type ): bool {
		return in_array( $post_type, self::names(), true );
	}
}
