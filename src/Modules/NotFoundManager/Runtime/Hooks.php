<?php
/**
 * Runtime hooks for 404 Manager.
 *
 * @package Airygen\Modules\NotFoundManager\Runtime
 */

declare(strict_types=1);

namespace Airygen\Modules\NotFoundManager\Runtime;

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Constants;
use Airygen\Modules\NotFoundManager\Admin\Settings;
use Airygen\Modules\NotFoundManager\Infrastructure\LogRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles runtime capture and cleanup for 404 logs.
 */
final class Hooks {

	private const ACTION_GROUP = 'airygen';

	/**
	 * Register runtime hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		$self = new self();
		add_action( 'template_redirect', array( $self, 'capture_404' ), 99 );
		add_action( 'init', array( $self, 'schedule_cleanup' ) );
		add_action( Constants::HOOK_404_MANAGER_CLEANUP, array( $self, 'cleanup' ) );
	}

	/**
	 * Capture 404 requests.
	 *
	 * @return void
	 */
	public function capture_404(): void {
		if ( ! ModuleSettings::is_enabled( 'notFoundManager' ) ) {
			return;
		}

		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ! is_404() ) {
			return;
		}

		$settings = Settings::get();

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$request_uri = is_string( $request_uri ) ? $request_uri : '/';

		$path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( '' === $path ) {
			$path = '/';
		}
		$path = '/' . ltrim( $path, '/' );

		if ( $this->is_excluded( $path, $settings ) ) {
			return;
		}

		$query        = (string) wp_parse_url( $request_uri, PHP_URL_QUERY );
		$ignore_query = isset( $settings['ignore_query_params'] ) ? (bool) $settings['ignore_query_params'] : true;
		$query_hash   = null;
		if ( ! $ignore_query && '' !== $query ) {
			$query_hash = md5( $query );
		}

		$advanced   = isset( $settings['monitor_mode'] ) && 'advanced' === $settings['monitor_mode'];
		$referer    = null;
		$user_agent = null;
		if ( $advanced && isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referer = sanitize_text_field( (string) wp_unslash( $_SERVER['HTTP_REFERER'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}
		if ( $advanced && isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$user_agent = sanitize_text_field( (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		$repo = new LogRepository();
		$repo->upsert( $path, $query_hash, $referer, $user_agent, $advanced );

		$this->apply_fallback_redirect( $settings, $path );
	}

	/**
	 * Schedule cleanup.
	 *
	 * @return void
	 */
	public function schedule_cleanup(): void {
		if ( ! ModuleSettings::is_enabled( 'notFoundManager' ) ) {
			$this->clear_cleanup();
			return;
		}

		if ( self::is_action_scheduler_available() ) {
			if ( false === as_next_scheduled_action( Constants::HOOK_404_MANAGER_CLEANUP, array(), self::ACTION_GROUP ) ) {
				as_schedule_recurring_action( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, Constants::HOOK_404_MANAGER_CLEANUP, array(), self::ACTION_GROUP );
			}
		}
	}

	/**
	 * Cleanup old rows.
	 *
	 * @return void
	 */
	public function cleanup(): void {
		if ( ! ModuleSettings::is_enabled( 'notFoundManager' ) ) {
			return;
		}

		$settings = Settings::get();
		$days     = isset( $settings['retention_days'] ) ? (int) $settings['retention_days'] : 30;
		$repo     = new LogRepository();
		$repo->purge_older_than_days( $days );
	}

	/**
	 * Check excluded path patterns.
	 *
	 * @param string              $path Path.
	 * @param array<string,mixed> $settings Settings.
	 * @return bool
	 */
	private function is_excluded( string $path, array $settings ): bool {
		$patterns = isset( $settings['exclude_patterns'] ) && is_array( $settings['exclude_patterns'] )
		? $settings['exclude_patterns']
		: array();

		foreach ( $patterns as $pattern ) {
			$pattern = trim( (string) $pattern );
			if ( '' === $pattern ) {
				continue;
			}

			if ( 0 === strpos( $pattern, 'regex:' ) ) {
				$regex = substr( $pattern, 6 );
				if ( is_string( $regex ) && '' !== $regex && @preg_match( $regex, $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					if ( 1 === preg_match( $regex, $path ) ) {
						return true;
					}
				}
				continue;
			}

			$wildcard_regex = '#^' . str_replace( '\\*', '.*', preg_quote( $pattern, '#' ) ) . '$#';
			if ( 1 === preg_match( $wildcard_regex, $path ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Apply fallback behavior for unmatched 404 requests.
	 *
	 * @param array<string,mixed> $settings Module settings.
	 * @param string              $request_path Current request path.
	 *
	 * @return void
	 */
	private function apply_fallback_redirect( array $settings, string $request_path ): void {
		$mode = isset( $settings['fallback_redirect_mode'] ) ? (string) $settings['fallback_redirect_mode'] : 'off';
		if ( ! in_array( $mode, array( 'home', 'custom' ), true ) ) {
			return;
		}

		$code = isset( $settings['fallback_redirect_code'] ) ? (int) $settings['fallback_redirect_code'] : 301;
		if ( ! in_array( $code, array( 301, 302, 307, 410, 451 ), true ) ) {
			$code = 301;
		}

		if ( 410 === $code || 451 === $code ) {
			status_header( $code );
			nocache_headers();
			exit;
		}

		$target = '';
		if ( 'home' === $mode ) {
			$target = home_url( '/' );
		} elseif ( 'custom' === $mode ) {
			$target = isset( $settings['fallback_redirect_target'] ) ? trim( (string) $settings['fallback_redirect_target'] ) : '';
		}

		if ( '' === $target ) {
			return;
		}

		$target_path = (string) wp_parse_url( $target, PHP_URL_PATH );
		$target_path = '/' . ltrim( $target_path, '/' );
		$current     = untrailingslashit( $request_path );
		$target_norm = untrailingslashit( $target_path );
		if ( '' === $current ) {
			$current = '/';
		}
		if ( '' === $target_norm ) {
			$target_norm = '/';
		}
		if ( $current === $target_norm ) {
			return;
		}

		wp_safe_redirect( $target, $code );
		exit;
	}

	/**
	 * Clear scheduled cleanup job.
	 *
	 * @return void
	 */
	private function clear_cleanup(): void {
		if ( self::is_action_scheduler_available() ) {
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( Constants::HOOK_404_MANAGER_CLEANUP, array(), self::ACTION_GROUP );
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
