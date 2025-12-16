<?php
/**
 * Tracks IndexNow daily quota usage.
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
 * Provides helpers for reserving the IndexNow daily quota.
 */
final class QuotaTracker {

	private const OPTION = Constants::OPTION_INDEXNOW_QUOTA;

	/**
	 * Cached quota state.
	 *
	 * @var array<string, mixed>|null
	 */
	private $state = null;

	/**
	 * Attempt to reserve part of the daily budget.
	 *
	 * @param int $count Number of URLs about to be submitted.
	 * @param int $limit Daily quota. Zero/negative means unlimited.
	 * @return bool True when reservation succeeds.
	 */
	public function reserve( int $count, int $limit ): bool {
		if ( $limit <= 0 ) {
			return true;
		}

		$state = $this->read_state();
		$today = gmdate( 'Y-m-d' );

		if ( ! isset( $state['date'] ) || $state['date'] !== $today ) {
			$state = array(
				'date'  => $today,
				'count' => 0,
			);
		}

		$count = max( 0, $count );

		if ( (int) $state['count'] + $count > $limit ) {
			$this->state = $state;
			return false;
		}

		$state['count'] = (int) $state['count'] + $count;
		$this->state    = $state;

		update_option( self::OPTION, $state, 'no' );

		return true;
	}

	/**
	 * Seconds remaining until the quota resets.
	 *
	 * @return int
	 */
	public function seconds_until_reset(): int {
		$now      = time();
		$tomorrow = strtotime( 'tomorrow', $now );
		return $tomorrow > $now ? $tomorrow - $now : 3600;
	}

	/**
	 * Reset the stored quota (useful for manual overrides/tests).
	 *
	 * @return void
	 */
	public function reset(): void {
		delete_option( self::OPTION );
		$this->state = null;
	}

	/**
	 * Retrieve raw state from the options table.
	 *
	 * @return array<string, mixed>
	 */
	private function read_state(): array {
		if ( is_array( $this->state ) ) {
			return $this->state;
		}

		$value       = get_option( self::OPTION, array() );
		$this->state = is_array( $value ) ? $value : array();

		return $this->state;
	}
}
