<?php
/**
 * DTO representing a resolved redirect.
 *
 * @package Airygen\Modules\Redirects\Domain\Dto
 */

declare(strict_types=1);

namespace Airygen\Modules\Redirects\Domain\Dto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encapsulates redirect target and status.
 */
final class RedirectMatch {

	/**
	 * Resolved redirect target URL.
	 *
	 * @var string
	 */
	private string $target;

	/**
	 * HTTP status code associated with the redirect.
	 *
	 * @var int
	 */
	private int $status;

	/**
	 * Create a redirect match value object.
	 *
	 * @param string $target Redirect destination.
	 * @param int    $status HTTP status code.
	 */
	public function __construct( string $target, int $status ) {
		$this->target = $target;
		$this->status = $status;
	}

	/**
	 * Retrieve redirect target.
	 *
	 * @return string
	 */
	public function get_target(): string {
		return $this->target;
	}

	/**
	 * Retrieve redirect status code.
	 *
	 * @return int
	 */
	public function get_status(): int {
		return $this->status;
	}
}
