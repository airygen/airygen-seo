<?php
/**
 * REST-polled recalculation queue state for Score Calculator.
 *
 * @package Airygen\Modules\ScoreCalculator\Runtime
 */

declare(strict_types=1);

namespace Airygen\Modules\ScoreCalculator\Runtime;

use Airygen\Constants;
use Airygen\Modules\ScoreCalculator\Admin\RestController as ScoreRestController;
use Throwable;
use WP_Post;

use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores and advances score recalculation progress through REST polling.
 */
final class Hooks {

	/**
	 * Supported post statuses for recalculation.
	 *
	 * @var array<int, string>
	 */
	private const SCOPED_POST_STATUSES = array( 'publish', 'future', 'draft', 'pending', 'private' );

	/**
	 * Cache group for transient queue lookups.
	 *
	 * @var string
	 */
	private const CACHE_GROUP = 'airygen_score_calculator';

	/**
	 * Start a recalculation queue for selected post types.
	 *
	 * @param array<int, string> $post_types Selected post type slugs.
	 *
	 * @return array<string, mixed>
	 */
	public static function start_recalculation( array $post_types ): array {
		$post_types = self::sanitize_post_types( $post_types );
		self::reset_post_type_tracking( $post_types );
		$state = self::build_initial_state( $post_types );
		self::save_state( $state );
		return self::get_status();
	}

	/**
	 * Process one queue item and return latest status.
	 *
	 * @return array<string, mixed>
	 */
	public static function process_next(): array {
		$state = self::load_state();
		if ( ! is_array( $state ) || empty( $state['running'] ) ) {
			return self::get_status();
		}

		$post_types = isset( $state['postTypes'] ) && is_array( $state['postTypes'] ) ? $state['postTypes'] : array();
		if ( empty( $post_types ) ) {
			self::finalize_state( $state );
			return self::get_status();
		}

		$processed_item = false;

		foreach ( array_keys( $post_types ) as $slug ) {
			if ( ! isset( $post_types[ $slug ] ) || ! is_array( $post_types[ $slug ] ) ) {
				continue;
			}

			$item  = $post_types[ $slug ];
			$total = isset( $item['total'] ) ? max( 0, (int) $item['total'] ) : 0;
			$done  = isset( $item['processed'] ) ? max( 0, (int) $item['processed'] ) : 0;
			if ( $done >= $total ) {
				continue;
			}

			$last_id = isset( $item['lastId'] ) ? max( 0, (int) $item['lastId'] ) : 0;
			$post_id = self::next_post_id( $slug, $last_id );

			if ( $post_id <= 0 ) {
				$item['processed']   = $total;
				$item['current']     = null;
				$post_types[ $slug ] = $item;
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post instanceof WP_Post ) {
				$item['processed'] = $done + 1;
				$item['failed']    = isset( $item['failed'] ) ? ( (int) $item['failed'] + 1 ) : 1;
				$item['lastId']    = $post_id;
				$item['current']   = array(
					'id'    => $post_id,
					'title' => '',
					'score' => null,
				);
				self::record_post_type_progress( (string) $slug, $post_id );
				$post_types[ $slug ] = $item;
				$processed_item      = true;
				break;
			}

			try {
				$response = ScoreRestController::calculate_score_for_post( $post );
				$score    = isset( $response['total']['score'] ) && is_numeric( $response['total']['score'] )
				? (float) $response['total']['score']
				: null;

				$item['processed'] = $done + 1;
				$item['lastId']    = $post_id;
				$item['current']   = array(
					'id'    => $post_id,
					'title' => (string) get_the_title( $post_id ),
					'score' => $score,
				);
			} catch ( Throwable $throwable ) {
				$item['processed'] = $done + 1;
				$item['failed']    = isset( $item['failed'] ) ? ( (int) $item['failed'] + 1 ) : 1;
				$item['lastId']    = $post_id;
				$item['current']   = array(
					'id'    => $post_id,
					'title' => (string) get_the_title( $post_id ),
					'score' => null,
				);
			}

			self::record_post_type_progress( (string) $slug, $post_id );
			$post_types[ $slug ] = $item;
			$processed_item      = true;
			break;
		}

		$state['postTypes'] = $post_types;
		$state['processed'] = self::sum_post_type_field( $post_types, 'processed' );
		$state['failed']    = self::sum_post_type_field( $post_types, 'failed' );
		$state['total']     = self::sum_post_type_field( $post_types, 'total' );
		$state['current']   = self::resolve_current_item( $post_types );
		$state['updatedAt'] = gmdate( DATE_ATOM );

		if ( ! $processed_item || (int) $state['processed'] >= (int) $state['total'] ) {
			self::finalize_state( $state );
			return self::get_status();
		}

		self::save_state( $state );
		return self::get_status();
	}

	/**
	 * Get current queue status.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_status(): array {
		$state = self::load_state();
		if ( ! is_array( $state ) ) {
			return self::default_status();
		}

		$post_types = array();
		$raw_types  = isset( $state['postTypes'] ) && is_array( $state['postTypes'] ) ? $state['postTypes'] : array();
		foreach ( $raw_types as $slug => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$current = null;
			if ( isset( $item['current'] ) && is_array( $item['current'] ) ) {
				$current = array(
					'id'    => isset( $item['current']['id'] ) ? (int) $item['current']['id'] : 0,
					'title' => isset( $item['current']['title'] ) ? (string) $item['current']['title'] : '',
					'score' => isset( $item['current']['score'] ) && is_numeric( $item['current']['score'] )
						? (float) $item['current']['score']
						: null,
				);
			}

			$post_types[] = array(
				'slug'            => sanitize_key( (string) $slug ),
				'label'           => isset( $item['label'] ) ? (string) $item['label'] : (string) $slug,
				'total'           => isset( $item['total'] ) ? max( 0, (int) $item['total'] ) : 0,
				'processed'       => isset( $item['processed'] ) ? max( 0, (int) $item['processed'] ) : 0,
				'failed'          => isset( $item['failed'] ) ? max( 0, (int) $item['failed'] ) : 0,
				'current'         => $current,
				'lastProcessedAt' => self::get_last_processed_at( (string) $slug ),
			);
		}

		return array(
			'running'    => ! empty( $state['running'] ),
			'processed'  => isset( $state['processed'] ) ? max( 0, (int) $state['processed'] ) : 0,
			'total'      => isset( $state['total'] ) ? max( 0, (int) $state['total'] ) : 0,
			'failed'     => isset( $state['failed'] ) ? max( 0, (int) $state['failed'] ) : 0,
			'current'    => isset( $state['current'] ) && is_array( $state['current'] ) ? $state['current'] : null,
			'postTypes'  => $post_types,
			'startedAt'  => isset( $state['startedAt'] ) ? (string) $state['startedAt'] : null,
			'finishedAt' => isset( $state['finishedAt'] ) ? (string) $state['finishedAt'] : null,
			'updatedAt'  => isset( $state['updatedAt'] ) ? (string) $state['updatedAt'] : null,
		);
	}

	/**
	 * Build initial queue state.
	 *
	 * @param array<int, string> $post_types Selected post type slugs.
	 *
	 * @return array<string, mixed>
	 */
	private static function build_initial_state( array $post_types ): array {
		$now        = gmdate( DATE_ATOM );
		$post_state = array();

		foreach ( $post_types as $post_type ) {
			$object = get_post_type_object( $post_type );
			$label  = $object && isset( $object->labels->singular_name )
			? (string) $object->labels->singular_name
			: $post_type;

			$post_state[ $post_type ] = array(
				'label'     => $label,
				'total'     => self::count_posts( $post_type ),
				'processed' => 0,
				'failed'    => 0,
				'lastId'    => 0,
				'current'   => null,
			);
		}

		$total = self::sum_post_type_field( $post_state, 'total' );

		return array(
			'running'    => $total > 0,
			'processed'  => 0,
			'failed'     => 0,
			'total'      => $total,
			'current'    => null,
			'postTypes'  => $post_state,
			'startedAt'  => $now,
			'updatedAt'  => $now,
			'finishedAt' => $total > 0 ? null : $now,
		);
	}

	/**
	 * Finalize queue state.
	 *
	 * @param array<string, mixed> $state Queue state.
	 *
	 * @return void
	 */
	private static function finalize_state( array $state ): void {
		$state['running']    = false;
		$state['current']    = self::resolve_current_item( is_array( $state['postTypes'] ?? null ) ? $state['postTypes'] : array() );
		$state['updatedAt']  = gmdate( DATE_ATOM );
		$state['finishedAt'] = gmdate( DATE_ATOM );
		self::save_state( $state );
	}

	/**
	 * Resolve latest current item from post type entries.
	 *
	 * @param array<string, mixed> $post_types Post type state map.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function resolve_current_item( array $post_types ): ?array {
		$latest = null;

		foreach ( $post_types as $slug => $item ) {
			if ( ! is_array( $item ) || empty( $item['current'] ) || ! is_array( $item['current'] ) ) {
				continue;
			}

			$latest = array(
				'postType'      => sanitize_key( (string) $slug ),
				'postTypeLabel' => isset( $item['label'] ) ? (string) $item['label'] : (string) $slug,
				'id'            => isset( $item['current']['id'] ) ? (int) $item['current']['id'] : 0,
				'title'         => isset( $item['current']['title'] ) ? (string) $item['current']['title'] : '',
				'score'         => isset( $item['current']['score'] ) && is_numeric( $item['current']['score'] )
					? (float) $item['current']['score']
					: null,
			);
		}

		return $latest;
	}

	/**
	 * Sum a numeric field from post type state map.
	 *
	 * @param array<string, mixed> $post_types Post type state map.
	 * @param string               $field      Field name.
	 *
	 * @return int
	 */
	private static function sum_post_type_field( array $post_types, string $field ): int {
		$sum = 0;
		foreach ( $post_types as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$sum += isset( $item[ $field ] ) ? max( 0, (int) $item[ $field ] ) : 0;
		}

		return $sum;
	}

	/**
	 * Count posts for a post type.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return int
	 */
	private static function count_posts( string $post_type ): int {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb ) {
			return 0;
		}

		$cache_key = 'count_posts:' . $post_type;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return is_numeric( $cached ) ? max( 0, (int) $cached ) : 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( self::SCOPED_POST_STATUSES ), '%s' ) );
		$sql          = "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$placeholders})";
		$params       = array_merge( array( $post_type ), self::SCOPED_POST_STATUSES );
		$query        = $wpdb->prepare( $sql, ...$params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared with a dynamic placeholder list.
		if ( ! is_string( $query ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$count = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared -- Prepared query string for an internal maintenance query.
		$value = is_numeric( $count ) ? max( 0, (int) $count ) : 0;
		wp_cache_set( $cache_key, $value, self::CACHE_GROUP, MINUTE_IN_SECONDS );

		return $value;
	}

	/**
	 * Resolve next post id by post type and last processed id.
	 *
	 * @param string $post_type Post type slug.
	 * @param int    $last_id   Last processed post ID.
	 *
	 * @return int
	 */
	private static function next_post_id( string $post_type, int $last_id ): int {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb ) {
			return 0;
		}

		$cache_key = 'next_post_id:' . $post_type . ':' . $last_id;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return is_numeric( $cached ) ? (int) $cached : 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( self::SCOPED_POST_STATUSES ), '%s' ) );
		$sql          = "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$placeholders}) AND ID > %d ORDER BY ID ASC LIMIT 1";
		$params       = array_merge( array( $post_type ), self::SCOPED_POST_STATUSES, array( $last_id ) );
		$query        = $wpdb->prepare( $sql, ...$params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared with a dynamic placeholder list.
		if ( ! is_string( $query ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$post_id = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared -- Prepared query string for an internal maintenance query.
		$value   = is_numeric( $post_id ) ? (int) $post_id : 0;
		wp_cache_set( $cache_key, $value, self::CACHE_GROUP, MINUTE_IN_SECONDS );

		return $value;
	}

	/**
	 * Sanitize incoming post type list.
	 *
	 * @param array<int, string> $post_types Raw post types.
	 *
	 * @return array<int, string>
	 */
	private static function sanitize_post_types( array $post_types ): array {
		$normalized = array();
		foreach ( $post_types as $post_type ) {
			$slug = sanitize_key( (string) $post_type );
			if ( '' === $slug || ! post_type_exists( $slug ) ) {
				continue;
			}
			if ( in_array( $slug, array( 'wp_block', 'wp_navigation' ), true ) ) {
				continue;
			}
			$normalized[] = $slug;
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Reset per-post-type tracking options for a new recalculation run.
	 *
	 * @param array<int, string> $post_types Selected post type slugs.
	 *
	 * @return void
	 */
	private static function reset_post_type_tracking( array $post_types ): void {
		foreach ( $post_types as $post_type ) {
			$slug = sanitize_key( $post_type );
			if ( '' === $slug ) {
				continue;
			}

			update_option( self::last_option_name( $slug ), '', false );
			update_option( self::done_option_name( $slug ), array(), false );
		}
	}

	/**
	 * Persist done IDs and last timestamp for a processed post type item.
	 *
	 * @param string $post_type Post type slug.
	 * @param int    $post_id   Processed post ID.
	 *
	 * @return void
	 */
	private static function record_post_type_progress( string $post_type, int $post_id ): void {
		$slug = sanitize_key( $post_type );
		if ( '' === $slug || $post_id <= 0 ) {
			return;
		}

		$done_option = self::done_option_name( $slug );
		$done_raw    = get_option( $done_option, array() );
		$done_ids    = is_array( $done_raw ) ? array_map( 'intval', $done_raw ) : array();
		$done_ids[]  = $post_id;
		$done_ids    = array_values( array_unique( array_filter( $done_ids ) ) );

		update_option( $done_option, $done_ids, false );
		update_option( self::last_option_name( $slug ), gmdate( DATE_ATOM ), false );
	}

	/**
	 * Resolve option name for last processed timestamp by post type.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return string
	 */
	private static function last_option_name( string $post_type ): string {
		return Constants::OPTION_SCORE_RECALCULATE_LAST_PREFIX . sanitize_key( $post_type );
	}

	/**
	 * Resolve option name for done post IDs by post type.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return string
	 */
	private static function done_option_name( string $post_type ): string {
		return Constants::OPTION_SCORE_RECALCULATE_DONE_PREFIX . sanitize_key( $post_type );
	}

	/**
	 * Get last processed timestamp for a post type.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return string|null
	 */
	private static function get_last_processed_at( string $post_type ): ?string {
		$value = get_option( self::last_option_name( $post_type ), '' );
		if ( ! is_string( $value ) || '' === $value ) {
			return null;
		}

		return $value;
	}

	/**
	 * Persist queue state.
	 *
	 * @param array<string, mixed> $state Queue state.
	 *
	 * @return void
	 */
	private static function save_state( array $state ): void {
		update_option( Constants::OPTION_SCORE_RECALCULATE_STATE, $state, false );
	}

	/**
	 * Load queue state from option.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function load_state(): ?array {
		$value = get_option( Constants::OPTION_SCORE_RECALCULATE_STATE, null );
		return is_array( $value ) ? $value : null;
	}

	/**
	 * Default status payload.
	 *
	 * @return array<string, mixed>
	 */
	private static function default_status(): array {
		return array(
			'running'    => false,
			'processed'  => 0,
			'total'      => 0,
			'failed'     => 0,
			'current'    => null,
			'postTypes'  => array(),
			'startedAt'  => null,
			'finishedAt' => null,
			'updatedAt'  => null,
		);
	}
}
