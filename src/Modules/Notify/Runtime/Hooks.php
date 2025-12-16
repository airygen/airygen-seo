<?php
/**
 * Runtime scheduler hooks for Notify module.
 *
 * @package Airygen\Modules\Notify\Runtime
 */

declare(strict_types=1);

namespace Airygen\Modules\Notify\Runtime;

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Constants;
use Airygen\Modules\Notify\Admin\Settings;
use Airygen\Modules\Notify\Infrastructure\DigestDispatcher;
use Airygen\Modules\Notify\Infrastructure\LogRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers daily notify dispatch schedule.
 */
final class Hooks {

	/**
	 * Action group.
	 */
	private const ACTION_GROUP = 'airygen';

	/**
	 * Register scheduler hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'schedule_daily' ) );
		add_action( Constants::HOOK_NOTIFY_DAILY_DIGEST, array( __CLASS__, 'dispatch_daily' ) );
		add_action( Constants::HOOK_NOTIFY_LOG_CLEANUP, array( __CLASS__, 'cleanup_logs' ) );
	}

	/**
	 * Schedule daily dispatch.
	 *
	 * @return void
	 */
	public static function schedule_daily(): void {
		if ( ! ModuleSettings::is_enabled( 'notify' ) ) {
			self::clear_scheduled();
			return;
		}

		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			self::clear_scheduled();
			return;
		}
		if ( ! DigestDispatcher::has_enabled_channel( $settings ) ) {
			self::clear_scheduled();
			return;
		}

		if ( self::is_action_scheduler_available() ) {
			if ( false === as_next_scheduled_action( Constants::HOOK_NOTIFY_DAILY_DIGEST, array(), self::ACTION_GROUP ) ) {
				as_schedule_recurring_action( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, Constants::HOOK_NOTIFY_DAILY_DIGEST, array(), self::ACTION_GROUP );
			}
			if ( false === as_next_scheduled_action( Constants::HOOK_NOTIFY_LOG_CLEANUP, array(), self::ACTION_GROUP ) ) {
				as_schedule_recurring_action( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, Constants::HOOK_NOTIFY_LOG_CLEANUP, array(), self::ACTION_GROUP );
			}
		}
	}

	/**
	 * Dispatch job.
	 *
	 * @return void
	 */
	public static function dispatch_daily(): void {
		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}
		if ( ! DigestDispatcher::has_enabled_channel( $settings ) ) {
			return;
		}
		$subject = isset( $settings['message']['subject'] ) && is_string( $settings['message']['subject'] ) ? trim( $settings['message']['subject'] ) : '';
		if ( '' === $subject ) {
			$subject = __( 'Airygen SEO Daily Digest', 'airygen-seo' );
		}

		DigestDispatcher::dispatch(
			$settings,
			$subject,
			__( 'No new records were generated for the selected digest sections.', 'airygen-seo' )
		);
	}

	/**
	 * Purge expired notify logs.
	 *
	 * @return void
	 */
	public static function cleanup_logs(): void {
		$settings       = Settings::get();
		$retention_days = isset( $settings['logs']['retention_days'] ) ? (int) $settings['logs']['retention_days'] : 30;
		$retention_days = max( 1, min( 3650, $retention_days ) );

		$repository = new LogRepository();
		$repository->purge_older_than_days( $retention_days );
	}

	/**
	 * Clear schedule.
	 *
	 * @return void
	 */
	private static function clear_scheduled(): void {
		if ( self::is_action_scheduler_available() ) {
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( Constants::HOOK_NOTIFY_DAILY_DIGEST, array(), self::ACTION_GROUP );
				as_unschedule_all_actions( Constants::HOOK_NOTIFY_LOG_CLEANUP, array(), self::ACTION_GROUP );
			}
		}
	}

	/**
	 * Whether Action Scheduler is available.
	 *
	 * @return bool
	 */
	private static function is_action_scheduler_available(): bool {
		return function_exists( 'as_schedule_recurring_action' )
		&& function_exists( 'as_next_scheduled_action' );
	}
}
