<?php
/**
 * Tracks content changes to decide when to recompute keyphrases.
 *
 * @package Airygen\Modules\LinkSuggestions\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkSuggestions\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Constants;
use WP_Post;

use function get_post_meta;
use function get_post_types;
use function in_array;
use function preg_replace;
use function strlen;
use function time;
use function trim;
use function wp_is_post_autosave;
use function wp_is_post_revision;
use function wp_strip_all_tags;

/**
 * Observes post saves and triggers recompute when thresholds are met.
 */
class ContentChangeWatcher {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'save_post', array( __CLASS__, 'maybe_handle_save' ), 20, 3 );
		add_action( 'wp_after_insert_post', array( __CLASS__, 'handle_after_insert' ), 20, 4 );
	}

	/**
	 * Proxy for wp_after_insert_post to reuse save logic.
	 *
	 * @param int     $post_id     Post ID.
	 * @param WP_Post $post        Post object.
	 * @param bool    $update      Whether this is an update.
	 * @param mixed   $post_before Unused.
	 *
	 * @return void
	 */
	public static function handle_after_insert( int $post_id, WP_Post $post, bool $update, $post_before = null ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		self::maybe_handle_save( $post_id, $post, $update );
	}

	/**
	 * Evaluate whether to trigger keyphrase recompute.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an update.
	 *
	 * @return void
	 */
	public static function maybe_handle_save( int $post_id, WP_Post $post, bool $update ): void {
		self::log_debug(
			sprintf(
				'maybe_handle_save post_id=%d update=%s status=%s type=%s',
				$post_id,
				$update ? 'yes' : 'no',
				$post->post_status,
				$post->post_type
			)
		);

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! $update && 'auto-draft' === $post->post_status ) {
			return;
		}

		$current_count = self::count_characters( (string) $post->post_content );

		$settings      = Settings::get();
		$allowed_types = ! empty( $settings['allowed_post_types'] ) ? (array) $settings['allowed_post_types'] : get_post_types( array( 'public' => true ) );

		if ( ! in_array( $post->post_type, $allowed_types, true ) ) {
			self::log_debug( sprintf( 'skip post_type post_id=%d type=%s', $post_id, $post->post_type ) );
			return;
		}

		if ( 'trash' === $post->post_status ) {
			self::log_debug( sprintf( 'skip trash post_id=%d', $post_id ) );
			return;
		}

		if ( ! $settings['enabled'] ) {
			self::log_debug( sprintf( 'module disabled post_id=%d', $post_id ) );
			return;
		}

		$last_indexed_at      = (int) get_post_meta( $post_id, Constants::META_KEYPHRASES_INDEXED_AT, true );
		$last_modified_gmt_ts = strtotime( (string) $post->post_modified_gmt );

		if ( $last_indexed_at > 0 && $last_modified_gmt_ts <= $last_indexed_at ) {
			self::log_debug( sprintf( 'skip recompute post_id=%d already indexed', $post_id ) );
			return;
		}

		self::log_debug( sprintf( 'queue recompute post_id=%d', $post_id ) );

		/**
		 * Fires when keyphrases should be recomputed due to significant content changes.
		 *
		 * @param int     $post_id      The post ID.
		 * @param int     $word_count   Latest character count.
		 * @param WP_Post $post         Post object.
		 */
		do_action( Constants::HOOK_LINK_SUGGESTIONS_RECOMPUTE_TF, $post_id, $current_count, $post ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook constant is already plugin-prefixed.
	}

	/**
	 * Count UTF-8 characters after stripping HTML and collapsing whitespace.
	 *
	 * @param string $content Raw content.
	 *
	 * @return int
	 */
	private static function count_characters( string $content ): int {
		$text = wp_strip_all_tags( $content );
		$text = preg_replace( '/\s+/u', ' ', $text );
		$text = trim( (string) $text );

		if ( '' === $text ) {
			return 0;
		}

		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( $text, 'UTF-8' );
		}

		return (int) strlen( $text );
	}

	/**
	 * Lightweight debug logger.
	 *
	 * @param string $message Log message.
	 *
	 * @return void
	 */
	private static function log_debug( string $message ): void {
	}
}
