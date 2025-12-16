<?php
/**
 * Lightweight response log for IndexNow submissions.
 *
 * @package Airygen\Modules\InstantIndexing\Runtime
 */

declare(strict_types=1);

namespace Airygen\Modules\InstantIndexing\Runtime;

use Airygen\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists a short rolling log of IndexNow responses.
 */
final class ResponseLogger {

	private const OPTION      = Constants::OPTION_INDEXNOW_RESPONSES;
	private const MAX_ENTRIES = 20;

	/**
	 * Append entries to the rolling log.
	 *
	 * @param array<int, array<string, mixed>> $entries Entries to store.
	 * @return void
	 */
	public function append( array $entries ): void {
		if ( empty( $entries ) ) {
			return;
		}

		$current = get_option( self::OPTION, array() );
		$current = is_array( $current ) ? $current : array();

		foreach ( $entries as $entry ) {
			$current[] = array(
				'engine'      => isset( $entry['engine'] ) ? (string) $entry['engine'] : '',
				'status_code' => isset( $entry['status_code'] ) ? (int) $entry['status_code'] : null,
				'success'     => ! empty( $entry['success'] ),
				'message'     => isset( $entry['message'] ) ? (string) $entry['message'] : '',
				'timestamp'   => isset( $entry['timestamp'] ) ? (string) $entry['timestamp'] : gmdate( 'c' ),
			);
		}

		$current = array_slice( $current, -1 * self::MAX_ENTRIES );

		update_option( self::OPTION, array_values( $current ), 'no' );
	}

	/**
	 * Retrieve stored entries.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		$current = get_option( self::OPTION, array() );
		return is_array( $current ) ? $current : array();
	}
}
