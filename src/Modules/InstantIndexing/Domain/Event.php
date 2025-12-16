<?php
/**
 * Immutable representation of an IndexNow queue event.
 *
 * @package Airygen\Modules\InstantIndexing\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\InstantIndexing\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Value object encapsulating queue metadata for a single URL.
 */
final class Event {

	/**
	 * Event ID.
	 *
	 * @var int
	 */
	private $id;

	/**
	 * Hostname associated with the URL.
	 *
	 * @var string
	 */
	private $host;

	/**
	 * Canonical URL.
	 *
	 * @var string
	 */
	private $url;

	/**
	 * Action type (add/update/delete).
	 *
	 * @var string
	 */
	private $action;

	/**
	 * Source identifier (auto/manual/backfill/etc).
	 *
	 * @var string
	 */
	private $source;

	/**
	 * Current status string.
	 *
	 * @var string
	 */
	private $status;

	/**
	 * Attempt count.
	 *
	 * @var int
	 */
	private $attempts;

	/**
	 * Constructor.
	 *
	 * @param int    $id       Event ID.
	 * @param string $host     Hostname.
	 * @param string $url      Canonical URL.
	 * @param string $action   Action type.
	 * @param string $source   Queue source.
	 * @param string $status   Current status.
	 * @param int    $attempts Attempt count.
	 */
	public function __construct(
		int $id,
		string $host,
		string $url,
		string $action,
		string $source,
		string $status,
		int $attempts
	) {
		$this->id       = $id;
		$this->host     = $host;
		$this->url      = $url;
		$this->action   = $action;
		$this->source   = $source;
		$this->status   = $status;
		$this->attempts = $attempts;
	}

	/**
	 * Get the event ID.
	 *
	 * @return int
	 */
	public function get_id(): int {
		return $this->id;
	}

	/**
	 * Get the host name.
	 *
	 * @return string
	 */
	public function get_host(): string {
		return $this->host;
	}

	/**
	 * Get the canonical URL.
	 *
	 * @return string
	 */
	public function get_url(): string {
		return $this->url;
	}

	/**
	 * Get the action type.
	 *
	 * @return string
	 */
	public function get_action(): string {
		return $this->action;
	}

	/**
	 * Get the source identifier.
	 *
	 * @return string
	 */
	public function get_source(): string {
		return $this->source;
	}

	/**
	 * Get the current status.
	 *
	 * @return string
	 */
	public function get_status(): string {
		return $this->status;
	}

	/**
	 * Get the retry attempts.
	 *
	 * @return int
	 */
	public function get_attempts(): int {
		return $this->attempts;
	}
}
