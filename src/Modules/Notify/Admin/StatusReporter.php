<?php
/**
 * Aggregates queue status information for Notify Daily Digest.
 *
 * @package Airygen\Modules\Notify\Admin
 */

declare(strict_types=1);

namespace Airygen\Modules\Notify\Admin;

use ActionScheduler_Store;
use Airygen\Constants;
use DateTimeInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds queue metadata for the Notify settings UI.
 */
final class StatusReporter {

	/**
	 * Action Scheduler group.
	 */
	private const ACTION_GROUP = 'airygen';

	/**
	 * Gather queue status details.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_status(): array {
		$available = self::is_action_scheduler_available();

		$status = array(
			'actionSchedulerAvailable' => $available,
			'queue'                    => array(
				'pending'    => null,
				'inProgress' => null,
				'failed'     => null,
				'completed'  => null,
			),
			'nextRunGmt'               => null,
			'lastRunGmt'               => null,
		);

		if ( $available ) {
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
	 * Count scheduled actions for a given Action Scheduler status.
	 *
	 * @param string $status Action Scheduler status.
	 * @return int|null
	 */
	private static function count_actions( string $status ): ?int {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return null;
		}

		$args = array(
			'hook'     => Constants::HOOK_NOTIFY_DAILY_DIGEST,
			'group'    => self::ACTION_GROUP,
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
	 * Query Action Scheduler for count result.
	 *
	 * @param array<string,mixed> $args Query args.
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
	 * Determine next run time.
	 *
	 * @return string|null
	 */
	private static function next_run_gmt(): ?string {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return null;
		}

		$timestamp = as_next_scheduled_action(
			Constants::HOOK_NOTIFY_DAILY_DIGEST,
			array(),
			self::ACTION_GROUP
		);

		if ( false === $timestamp ) {
			$timestamp = as_next_scheduled_action( Constants::HOOK_NOTIFY_DAILY_DIGEST );
		}

		if ( ! is_numeric( $timestamp ) || $timestamp <= 0 ) {
			return null;
		}

		return gmdate( DATE_ATOM, (int) $timestamp );
	}

	/**
	 * Determine when queue last completed.
	 *
	 * @return string|null
	 */
	private static function last_completed_run_gmt(): ?string {
		$args = array(
			'hook'     => Constants::HOOK_NOTIFY_DAILY_DIGEST,
			'group'    => self::ACTION_GROUP,
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
	 * Fetch one action timestamp.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return int|null
	 */
	private static function fetch_action_timestamp( array $args ): ?int {
		$store = self::get_store();
		if ( $store && method_exists( $store, 'get_date' ) ) {
			$ids = $store->query_actions( $args );
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
				$action = $actions[0];
				if ( is_array( $action ) && isset( $action[0] ) ) {
					$action = $action[0];
				}
				if ( is_object( $action ) && method_exists( $action, 'get_schedule' ) ) {
					$schedule = $action->get_schedule();
					if ( $schedule && method_exists( $schedule, 'get_date' ) ) {
						$date = $schedule->get_date();
						if ( $date instanceof DateTimeInterface ) {
							return $date->getTimestamp();
						}
					}
				}
			}
		}

		return null;
	}

	/**
	 * Whether Action Scheduler helpers are available.
	 *
	 * @return bool
	 */
	private static function is_action_scheduler_available(): bool {
		return function_exists( 'as_get_scheduled_actions' )
		&& function_exists( 'as_next_scheduled_action' );
	}

	/**
	 * Get Action Scheduler store.
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
