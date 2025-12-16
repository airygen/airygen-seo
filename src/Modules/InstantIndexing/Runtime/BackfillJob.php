<?php
/**
 * Handles Action Scheduler jobs for IndexNow backfills.
 *
 * @package Airygen\Modules\InstantIndexing\Runtime
 */

declare(strict_types=1);

namespace Airygen\Modules\InstantIndexing\Runtime;

use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles batched backfill submissions via Action Scheduler.
 */
final class BackfillJob {

	/**
	 * Number of posts to enqueue per chunk.
	 */
	private const PAGE_SIZE = 200;

	/**
	 * Queue repository.
	 *
	 * @var QueueRepository
	 */
	private $queue;

	/**
	 * Constructor.
	 *
	 * @param QueueRepository $queue Queue repository instance.
	 */
	public function __construct( QueueRepository $queue ) {
		$this->queue = $queue;
	}

	/**
	 * Bootstrap the async workflow.
	 *
	 * @param array<int, string> $post_types Post types selected by the user.
	 * @return void
	 */
	public function enqueue( array $post_types ): void {
		$types = $this->normalize_post_types( $post_types );
		if ( empty( $types ) ) {
			return;
		}

		if ( self::is_action_scheduler_available() ) {
			as_enqueue_async_action(
				Hooks::BACKFILL_ACTION,
				array(
					'post_types' => $types,
					'page'       => 1,
				),
				Hooks::ACTION_GROUP
			);
			return;
		}

		// Fallback: process synchronously.
		$this->run_page( $types, 1 );
	}

	/**
	 * Process a queued page of posts.
	 *
	 * @param array<int, string> $post_types Post types.
	 * @param int                $page       Page number (1-indexed).
	 * @return void
	 */
	public function run_page( array $post_types, int $page ): void {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$host = is_string( $host ) ? $host : '';
		if ( '' === $host ) {
			return;
		}

		$query = new WP_Query(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => self::PAGE_SIZE,
				'paged'          => max( 1, $page ),
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		if ( empty( $query->posts ) ) {
			return;
		}

		$urls = array();
		foreach ( $query->posts as $post_id ) {
			$permalink = get_permalink( (int) $post_id );
			if ( ! $permalink ) {
				continue;
			}

			$urls[] = $permalink;
		}

		if ( ! empty( $urls ) ) {
			$this->queue->enqueue_many( $host, $urls, 'update', 'backfill' );
			Hooks::queue_queue_processing();
		}

		$max_pages = (int) $query->max_num_pages;
		if ( $page < $max_pages ) {
			if ( self::is_action_scheduler_available() ) {
				as_enqueue_async_action(
					Hooks::BACKFILL_ACTION,
					array(
						'post_types' => $post_types,
						'page'       => $page + 1,
					),
					Hooks::ACTION_GROUP
				);
			} else {
				$this->run_page( $post_types, $page + 1 );
			}
		}
	}

	/**
	 * Filter list of supported post types.
	 *
	 * @param array<int, string> $post_types Raw array.
	 * @return array<int, string>
	 */
	private function normalize_post_types( array $post_types ): array {
		$supported = PostTypes::names();
		$selected  = array();

		foreach ( $post_types as $type ) {
			$slug = sanitize_key( (string) $type );
			if ( '' !== $slug && in_array( $slug, $supported, true ) ) {
				$selected[] = $slug;
			}
		}

		return array_values( array_unique( $selected ) );
	}

	/**
	 * Whether Action Scheduler functions exist.
	 *
	 * @return bool
	 */
	private static function is_action_scheduler_available(): bool {
		return function_exists( 'as_enqueue_async_action' );
	}
}
