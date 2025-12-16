<?php
/**
 * Aggregates status information for the Link Counter queue.
 *
 * @package Airygen\Modules\LinkCounter\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkCounter\Admin;

use ActionScheduler_Store;
use Airygen\Modules\LinkCounter\Domain\Storage;
use Airygen\Modules\LinkCounter\Runtime\Hooks as RuntimeHooks;
use DateTimeInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds status metadata for the settings UI.
 */
final class StatusReporter {

	/**
	 * Gather queue + backlog status details.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_status(): array {
		$storage                    = new Storage();
		$action_scheduler_available = RuntimeHooks::is_action_scheduler_available();

		$status = array(
			'pendingPosts'             => $storage->count_pending_posts(),
			'processingPosts'          => $storage->count_processing_posts(),
			'failedPosts'              => $storage->count_failed_posts(),
			'processedPosts'           => $storage->count_processed_posts(),
			'actionSchedulerAvailable' => $action_scheduler_available,
			'queue'                    => array(
				'pending'    => null,
				'inProgress' => null,
				'failed'     => null,
				'completed'  => null,
			),
			'nextRunGmt'               => null,
			'lastRunGmt'               => null,
		);

		if ( $action_scheduler_available ) {
			$status['queue']['pending']    = self::count_actions( 'pending' );
			$status['queue']['inProgress'] = self::count_actions( 'in-progress' );
			$status['queue']['failed']     = self::count_actions( 'failed' );
			$status['queue']['completed']  = self::count_actions( 'complete' );
			$status['nextRunGmt']          = self::next_run_gmt();
			$status['lastRunGmt']          = self::last_completed_run_gmt();
		}

		return $status;
	}

	/**
	 * Count scheduled actions for a given status.
	 *
	 * @param string $status Action Scheduler status slug.
	 * @return int|null
	 */
	private static function count_actions( string $status ): ?int {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return null;
		}

		$args = array(
			'hook'     => RuntimeHooks::ACTION_HOOK,
			'group'    => RuntimeHooks::ACTION_GROUP,
			'status'   => $status,
			'per_page' => -1,
		);

		$result = self::query_action_count( $args );
		if ( null === $result ) {
			unset( $args['group'] );
			$result = self::query_action_count( $args );
		}

		return $result;
	}

	/**
	 * Query Action Scheduler for a count result, falling back to 0 when unavailable.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return int|null
	 */
	private static function query_action_count( array $args ): ?int {
		$store = self::get_store();
		if ( $store ) {
			$query_args = array_merge(
				$args,
				array(
					'per_page' => -1,
					'count'    => true,
				)
			);

			$result = $store->query_actions( $query_args );
			if ( is_numeric( $result ) ) {
				return (int) $result;
			}
		}

		$ids = as_get_scheduled_actions( $args, 'ids' );
		if ( is_array( $ids ) ) {
			return count( $ids );
		}

		return null;
	}

	/**
	 * Determine the next scheduled run time.
	 *
	 * @return string|null
	 */
	private static function next_run_gmt(): ?string {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return null;
		}

		$timestamp = as_next_scheduled_action(
			RuntimeHooks::ACTION_HOOK,
			array(),
			RuntimeHooks::ACTION_GROUP
		);

		if ( false === $timestamp ) {
			$timestamp = as_next_scheduled_action( RuntimeHooks::ACTION_HOOK );
		}

		if ( ! is_numeric( $timestamp ) || $timestamp <= 0 ) {
			return null;
		}

		return gmdate( DATE_ATOM, (int) $timestamp );
	}

	/**
	 * Determine when the queue last completed.
	 *
	 * @return string|null
	 */
	private static function last_completed_run_gmt(): ?string {
		$args = array(
			'hook'     => RuntimeHooks::ACTION_HOOK,
			'group'    => RuntimeHooks::ACTION_GROUP,
			'status'   => 'complete',
			'per_page' => 1,
			'orderby'  => 'scheduled_date_gmt',
			'order'    => 'DESC',
		);

		$timestamp = self::fetch_action_timestamp( $args );

		if ( null === $timestamp ) {
			unset( $args['group'] );
			$timestamp = self::fetch_action_timestamp( $args );
		}

		return $timestamp ? gmdate( DATE_ATOM, $timestamp ) : null;
	}

	/**
	 * Fetch a single action timestamp via Action Scheduler.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return int|null
	 */
	private static function fetch_action_timestamp( array $args ): ?int {
		$store = self::get_store();
		if ( $store && method_exists( $store, 'get_date' ) ) {
			$query_args = array_merge(
				$args,
				array(
					'per_page' => $args['per_page'] ?? 1,
					'orderby'  => $args['orderby'] ?? 'scheduled_date_gmt',
					'order'    => $args['order'] ?? 'DESC',
				)
			);

			$ids = $store->query_actions( $query_args );
			if ( is_array( $ids ) && ! empty( $ids ) ) {
				$date = $store->get_date( (int) $ids[0] );
				if ( $date instanceof DateTimeInterface ) {
					return $date->getTimestamp();
				}
			}
		}

		if ( function_exists( 'as_get_scheduled_actions' ) ) {
			$actions = as_get_scheduled_actions( $args, 'objects' );
			if ( is_array( $actions ) && ! empty( $actions ) ) {
				return self::extract_timestamp_from_action( $actions[0] );
			}
		}

		return null;
	}

	/**
	 * Extract a schedule timestamp from an action object.
	 *
	 * @param mixed $action Action instance.
	 * @return int|null
	 */
	private static function extract_timestamp_from_action( $action ): ?int {
		if ( is_array( $action ) && isset( $action[0] ) ) {
			$action = $action[0];
		}

		if ( ! is_object( $action ) || ! method_exists( $action, 'get_schedule' ) ) {
			return null;
		}

		$schedule = $action->get_schedule();
		if ( ! $schedule || ! method_exists( $schedule, 'get_date' ) ) {
			return null;
		}

		$date = $schedule->get_date();
		if ( ! $date instanceof DateTimeInterface ) {
			return null;
		}

		return $date->getTimestamp();
	}

	/**
	 * Retrieve the Action Scheduler store instance when available.
	 *
	 * @return ActionScheduler_Store|null
	 */
	private static function get_store(): ?ActionScheduler_Store {
		if ( ! class_exists( ActionScheduler_Store::class ) ) {
			return null;
		}

		return ActionScheduler_Store::instance();
	}
}
