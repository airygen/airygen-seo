<?php
/**
 * Runtime hooks for the Broken Link Checker.
 *
 * @package Airygen\Modules\BrokenLinkChecker\Runtime
 */

declare(strict_types=1);

namespace Airygen\Modules\BrokenLinkChecker\Runtime;

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Constants;
use Airygen\Modules\BrokenLinkChecker\Admin\Settings;
use Airygen\Modules\BrokenLinkChecker\Domain\HttpChecker;
use Airygen\Modules\BrokenLinkChecker\Domain\LinkDataRepository;
use Airygen\Modules\BrokenLinkChecker\Domain\LogRepository;
use Airygen\Modules\BrokenLinkChecker\Domain\Processor;
use Airygen\Modules\LinkCounter\Domain\Storage as LinkCounterStorage;
use Airygen\Modules\LinkCounter\Runtime\Hooks as LinkCounterRuntimeHooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schedules and executes Broken Link Checker batches.
 */
final class Hooks {

	private const ACTION_HOOK  = Constants::HOOK_BROKEN_LINK_CHECKER_RUN;
	private const CLEANUP_HOOK = Constants::HOOK_BROKEN_LINK_CHECKER_CLEANUP;
	private const ACTION_GROUP = 'airygen';

	/**
	 * Bootstrap runtime hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		$self = new self();
		add_action( 'init', array( $self, 'maybe_schedule' ) );
		add_action( self::ACTION_HOOK, array( $self, 'run_checker' ) );
		add_action( self::CLEANUP_HOOK, array( $self, 'purge_expired_logs' ) );
	}

	/**
	 * Schedule the processor if requirements are met.
	 *
	 * @return void
	 */
	public function maybe_schedule(): void {
		$settings = Settings::get();
		if ( ! $this->modules_enabled() || empty( $settings['enabled'] ) ) {
			$this->clear_scheduled_runs();
			$this->clear_cleanup_schedule();
			return;
		}

		$this->schedule_log_cleanup();

		if ( ! $this->should_run() ) {
			$this->clear_scheduled_runs();
			return;
		}

		if ( self::is_action_scheduler_available() ) {
			if ( false === as_next_scheduled_action( self::ACTION_HOOK, array(), self::ACTION_GROUP ) ) {
				as_schedule_single_action( time(), self::ACTION_HOOK, array(), self::ACTION_GROUP );
			}
			return;
		}

		if ( ! wp_next_scheduled( self::ACTION_HOOK ) ) {
			wp_schedule_single_event( time(), self::ACTION_HOOK );
		}
	}

	/**
	 * Process a batch of links.
	 *
	 * @return void
	 */
	public function run_checker(): void {
		$settings = Settings::get();

		if ( ! $this->modules_enabled() || empty( $settings['enabled'] ) ) {
			$this->clear_scheduled_runs();
			return;
		}

		if ( ! $this->is_link_counter_ready() ) {
			$this->schedule_in( 5 * MINUTE_IN_SECONDS );
			return;
		}

		$repository = new LinkDataRepository();
		$logs       = new LogRepository();
		$http       = new HttpChecker( $settings );
		$processor  = new Processor( $repository, $logs, $http );
		$processed  = $processor->run( $settings );

		$batch_delay    = max( 1, (int) ( $settings['batch_delay_minutes'] ?? 5 ) ) * MINUTE_IN_SECONDS;
		$interval_delay = max( 1, (int) ( $settings['check_interval_hours'] ?? 1 ) ) * HOUR_IN_SECONDS;
		$delay          = $processed > 0 ? $batch_delay : $interval_delay;

		if ( 0 === $processed ) {
			// No work found; retry sooner than the long interval when backlog is empty.
			$delay = min( $delay, 10 * MINUTE_IN_SECONDS );
		}

		$this->schedule_in( $delay );
	}

	/**
	 * Purge expired log entries.
	 *
	 * @return void
	 */
	public function purge_expired_logs(): void {
		$settings = Settings::get();

		if ( ! $this->modules_enabled() || empty( $settings['enabled'] ) ) {
			return;
		}

		$retention_days = isset( $settings['log_retention_days'] ) ? (int) $settings['log_retention_days'] : 7;

		$logs = new LogRepository();
		$logs->purge_older_than_days( $retention_days );
	}

	/**
	 * Determine if Broken Link Checker should run.
	 *
	 * @return bool
	 */
	private function should_run(): bool {
		$settings = Settings::get();

		if ( ! $this->modules_enabled() || empty( $settings['enabled'] ) ) {
			return false;
		}

		return $this->is_link_counter_ready();
	}

	/**
	 * Ensure Link Counter and Broken Link Checker modules are enabled.
	 *
	 * @return bool
	 */
	private function modules_enabled(): bool {
		return ModuleSettings::is_enabled( 'brokenLinkChecker' )
		&& ModuleSettings::is_enabled( 'linkCounter' );
	}

	/**
	 * Whether the link counter backlog has completed.
	 *
	 * @return bool
	 */
	private function is_link_counter_ready(): bool {
		$storage = new LinkCounterStorage();

		if ( $storage->has_pending_posts() ) {
			return false;
		}

		if ( $storage->count_processing_posts() > 0 ) {
			return false;
		}

		return ! $this->link_counter_jobs_pending();
	}

	/**
	 * Determine whether Link Counter still has queued Action Scheduler jobs.
	 *
	 * @return bool
	 */
	private function link_counter_jobs_pending(): bool {
		if ( ! LinkCounterRuntimeHooks::is_action_scheduler_available() ) {
			return false;
		}

		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return false;
		}

		return false !== as_next_scheduled_action(
			LinkCounterRuntimeHooks::ACTION_HOOK,
			array(),
			LinkCounterRuntimeHooks::ACTION_GROUP
		);
	}

	/**
	 * Schedule the next run using Action Scheduler or WP-Cron.
	 *
	 * @param int $delay Seconds until the next run.
	 * @return void
	 */
	private function schedule_in( int $delay ): void {
		$timestamp = time() + max( MINUTE_IN_SECONDS, $delay );

		if ( self::is_action_scheduler_available() ) {
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( self::ACTION_HOOK, array(), self::ACTION_GROUP );
			}
			as_schedule_single_action( $timestamp, self::ACTION_HOOK, array(), self::ACTION_GROUP );
			return;
		}

		wp_clear_scheduled_hook( self::ACTION_HOOK );
		wp_schedule_single_event( $timestamp, self::ACTION_HOOK );
	}

	/**
	 * Check if Action Scheduler helpers are available.
	 *
	 * @return bool
	 */
	private static function is_action_scheduler_available(): bool {
		return function_exists( 'as_enqueue_async_action' )
		&& function_exists( 'as_next_scheduled_action' )
		&& function_exists( 'as_schedule_single_action' )
		&& function_exists( 'as_schedule_recurring_action' );
	}

	/**
	 * Remove any scheduled Broken Link Checker runs.
	 *
	 * @return void
	 */
	private function clear_scheduled_runs(): void {
		if ( self::is_action_scheduler_available() && function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::ACTION_HOOK, array(), self::ACTION_GROUP );
			return;
		}

		wp_clear_scheduled_hook( self::ACTION_HOOK );
	}

	/**
	 * Schedule a daily cleanup job for expired logs.
	 *
	 * @return void
	 */
	private function schedule_log_cleanup(): void {
		if ( ! self::is_action_scheduler_available() ) {
			return;
		}

		if ( false !== as_next_scheduled_action( self::CLEANUP_HOOK, array(), self::ACTION_GROUP ) ) {
			return;
		}

		as_schedule_recurring_action(
			time() + DAY_IN_SECONDS,
			DAY_IN_SECONDS,
			self::CLEANUP_HOOK,
			array(),
			self::ACTION_GROUP
		);
	}

	/**
	 * Remove scheduled cleanup jobs.
	 *
	 * @return void
	 */
	private function clear_cleanup_schedule(): void {
		if ( self::is_action_scheduler_available() && function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::CLEANUP_HOOK, array(), self::ACTION_GROUP );
		}
	}
}
