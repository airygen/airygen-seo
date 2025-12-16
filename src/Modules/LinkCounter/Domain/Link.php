<?php
/**
 * Value object representing a discovered link.
 *
 * @package Airygen\Modules\LinkCounter\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkCounter\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Link DTO storing url, target, and type.
 */
final class Link {

	public const TYPE_INTERNAL = 'internal';
	public const TYPE_EXTERNAL = 'external';

	/**
	 * Raw link URL.
	 *
	 * @var string
	 */
	private $url;

	/**
	 * Target post identifier (0 when not pointing to a local post).
	 *
	 * @var int
	 */
	private $target_post_id;

	/**
	 * Link type.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * Constructor.
	 *
	 * @param string $url            Link URL.
	 * @param int    $target_post_id Target post ID.
	 * @param string $type           Link type.
	 */
	public function __construct( string $url, int $target_post_id, string $type ) {
		$this->url            = $url;
		$this->target_post_id = $target_post_id;
		$this->type           = $type;
	}

	/**
	 * Get link URL.
	 *
	 * @return string
	 */
	public function get_url(): string {
		return $this->url;
	}

	/**
	 * Get target post ID.
	 *
	 * @return int
	 */
	public function get_target_post_id(): int {
		return $this->target_post_id;
	}

	/**
	 * Get link type.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return $this->type;
	}
}
