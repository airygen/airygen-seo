<?php
/**
 * Represents aggregate queue counts for admin status surfaces.
 *
 * @package Airygen\Modules\InstantIndexing\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\InstantIndexing\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simple DTO encapsulating queue totals.
 */
final class QueueSummary {

	/**
	 * Pending event count.
	 *
	 * @var int
	 */
	private $pending;

	/**
	 * Processing event count.
	 *
	 * @var int
	 */
	private $processing;

	/**
	 * Failed event count.
	 *
	 * @var int
	 */
	private $failed;

	/**
	 * Completed event count.
	 *
	 * @var int
	 */
	private $completed;

	/**
	 * Constructor.
	 *
	 * @param int $pending    Pending total.
	 * @param int $processing Processing total.
	 * @param int $failed     Failed total.
	 * @param int $completed  Completed total.
	 */
	public function __construct( int $pending, int $processing, int $failed, int $completed ) {
		$this->pending    = max( 0, $pending );
		$this->processing = max( 0, $processing );
		$this->failed     = max( 0, $failed );
		$this->completed  = max( 0, $completed );
	}

	/**
	 * Convert the summary to an array.
	 *
	 * @return array<string, int>
	 */
	public function to_array(): array {
		return array(
			'pending'    => $this->pending,
			'processing' => $this->processing,
			'failed'     => $this->failed,
			'completed'  => $this->completed,
		);
	}
}
