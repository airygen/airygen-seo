<?php
/**
 * DTO storing robots directives for page-level output.
 *
 * @package Airygen\Modules\Robots\Domain\Dto
 */

declare(strict_types=1);

namespace Airygen\Modules\Robots\Domain\Dto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Represents page-level robots values.
 */
final class RobotsDirectives {

	/**
	 * Robots meta directive string.
	 *
	 * @var string|null
	 */
	private ?string $meta_directive;

	/**
	 * Whether default meta should be suppressed.
	 *
	 * @var bool
	 */
	private bool $suppress_default;

	/**
	 * Constructor.
	 *
	 * @param string|null $meta_directive Robots meta directive.
	 * @param bool        $suppress_default Whether to suppress default meta output.
	 */
	public function __construct( ?string $meta_directive, bool $suppress_default ) {
		$this->meta_directive   = $this->normalize( $meta_directive );
		$this->suppress_default = $suppress_default;
	}

	/**
	 * Retrieve the meta directive value.
	 *
	 * @return string|null
	 */
	public function get_meta_directive(): ?string {
		return $this->meta_directive;
	}

	/**
	 * Determine whether default output should be suppressed.
	 *
	 * @return bool
	 */
	public function should_suppress_default(): bool {
		return $this->suppress_default;
	}

	/**
	 * Normalize meta directive input.
	 *
	 * @param string|null $value Meta directive value.
	 *
	 * @return string|null
	 */
	private function normalize( ?string $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		$value = trim( strtolower( $value ) );
		if ( '' === $value || 'index,follow' === $value ) {
			return null;
		}

		return $value;
	}
}
