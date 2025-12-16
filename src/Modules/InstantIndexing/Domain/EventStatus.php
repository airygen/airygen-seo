<?php
/**
 * Enum-like collection of queue statuses.
 *
 * @package Airygen\Modules\InstantIndexing\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\InstantIndexing\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enum-style status constants for queue events.
 */
final class EventStatus {
	public const PENDING    = 'pending';
	public const PROCESSING = 'processing';
	public const COMPLETED  = 'completed';
	public const FAILED     = 'failed';

	/**
	 * Retrieve all supported status strings.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array(
			self::PENDING,
			self::PROCESSING,
			self::COMPLETED,
			self::FAILED,
		);
	}
}
