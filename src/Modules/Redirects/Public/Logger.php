<?php
/**
 * Handles 404 logging for redirects feature.
 *
 * @package Airygen\Modules\Redirects\Public
 */

declare(strict_types=1);

namespace Airygen\Modules\Redirects\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\Redirects\Admin\Settings;

/**
 * Records limited 404 entries per day.
 */
final class Logger {

	private const DAILY_LIMIT = 50;

	/**
	 * Store a 404 record for the current day.
	 *
	 * @param string $path Requested path.
	 * @return void
	 */
	public static function log_404( string $path ): void {
		Settings::ensure_exists();

		$log   = Settings::get_log();
		$today = gmdate( 'Y-m-d' );

		if ( ! isset( $log[ $today ] ) || ! is_array( $log[ $today ] ) ) {
			$log[ $today ] = array();
		}

		if ( count( $log[ $today ] ) >= self::DAILY_LIMIT ) {
			Settings::update_log( $log );
			return;
		}

		$log[ $today ][] = array(
			'path'      => $path,
			'timestamp' => gmdate( 'c' ),
		);

		// Keep only the latest 7 days for storage efficiency.
		$log = array_slice( $log, -7, null, true );

		Settings::update_log( $log );
	}
}
