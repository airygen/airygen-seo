<?php
/**
 * Value object that stores computed head metadata for a post.
 *
 * @package Airygen\Modules\OnPageSeo\Domain\Dto
 */

declare(strict_types=1);

namespace Airygen\Modules\OnPageSeo\Domain\Dto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable payload for head metadata.
 */
final class HeadMeta {

	/**
	 * Computed page title.
	 *
	 * @var string|null
	 */
	private ?string $title;

	/**
	 * Computed description.
	 *
	 * @var string|null
	 */
	private ?string $description;

	/**
	 * Canonical URL.
	 *
	 * @var string|null
	 */
	private ?string $canonical;

	/**
	 * Robots directive string.
	 *
	 * @var string|null
	 */
	private ?string $robots;

	/**
	 * Constructor.
	 *
	 * @param string|null $title       The computed page title.
	 * @param string|null $description The computed description.
	 * @param string|null $canonical   The canonical URL.
	 * @param string|null $robots      The robots directive string.
	 */
	public function __construct( ?string $title, ?string $description, ?string $canonical, ?string $robots ) {
		$this->title       = $this->empty_to_null( $title );
		$this->description = $this->empty_to_null( $description );
		$this->canonical   = $this->empty_to_null( $canonical );
		$this->robots      = $this->empty_to_null( $robots );
	}

	/**
	 * Get computed title.
	 */
	public function get_title(): ?string {
		return $this->title;
	}

	/**
	 * Get computed description.
	 */
	public function get_description(): ?string {
		return $this->description;
	}

	/**
	 * Get canonical URL.
	 */
	public function get_canonical(): ?string {
		return $this->canonical;
	}

	/**
	 * Get robots directive.
	 */
	public function get_robots(): ?string {
		return $this->robots;
	}

	/**
	 * Export value object as associative array.
	 *
	 * @return array<string, string|null>
	 */
	public function to_array(): array {
		return array(
			'title'       => $this->get_title(),
			'description' => $this->get_description(),
			'canonical'   => $this->get_canonical(),
			'robots'      => $this->get_robots(),
		);
	}

	/**
	 * Normalize empty strings to null.
	 *
	 * @param string|null $value Arbitrary value.
	 *
	 * @return string|null
	 */
	private function empty_to_null( ?string $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		$trimmed = trim( $value );
		return '' === $trimmed ? null : $trimmed;
	}
}
