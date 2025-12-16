<?php
/**
 * Admin hooks for llms.txt module.
 *
 * @package Airygen\Modules\LlmsTxt\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\LlmsTxt\Admin;

use Airygen\Constants;
use Airygen\Modules\LlmsTxt\Infrastructure\RenderCache;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers admin lifecycle hooks.
 */
final class Hooks {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'ensure_option' ), 20 );
		add_action( 'save_post', array( __CLASS__, 'handle_save_post' ), 20, 3 );
		add_action( 'deleted_post', array( __CLASS__, 'handle_post_change' ) );
		add_action( 'trashed_post', array( __CLASS__, 'handle_post_change' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'handle_post_change' ) );
		add_action( 'updated_option', array( __CLASS__, 'handle_updated_option' ), 20, 3 );
	}

	/**
	 * Ensure settings option exists.
	 *
	 * @return void
	 */
	public static function ensure_option(): void {
		Settings::ensure_exists();
	}

	/**
	 * Invalidate cache when a post is saved.
	 *
	 * @param int     $post_id Post identifier.
	 * @param WP_Post $post    Saved post.
	 * @param bool    $update  Whether this is an update.
	 *
	 * @return void
	 */
	public static function handle_save_post( int $post_id, WP_Post $post, bool $update ): void {
		unset( $update );

		if ( wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}

		RenderCache::invalidate_all();
	}

	/**
	 * Invalidate cache when a post lifecycle event occurs.
	 *
	 * @param int $post_id Post identifier.
	 *
	 * @return void
	 */
	public static function handle_post_change( int $post_id ): void {
		if ( $post_id <= 0 ) {
			return;
		}

		RenderCache::invalidate_all();
	}

	/**
	 * Invalidate cache when relevant options change.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Previous value.
	 * @param mixed  $value     New value.
	 *
	 * @return void
	 */
	public static function handle_updated_option( string $option, $old_value, $value ): void {
		unset( $old_value, $value );

		if ( ! in_array( $option, array( Constants::OPTION_MARKDOWN_FOR_AGENTS, Constants::OPTION_TOPIC_CLUSTER ), true ) ) {
			return;
		}

		RenderCache::invalidate_all();
	}
}
