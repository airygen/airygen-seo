<?php
/**
 * DTO representing keyphrase extraction result.
 *
 * @package Airygen\Modules\LinkSuggestions\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkSuggestions\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds TF-weighted stems returned from the keyphrase provider.
 */
class KeyphraseDto {

	/**
	 * @var array<string, float>
	 */
	private $terms;

	/**
	 * @var array<string, mixed>
	 */
	private $metadata;

	/**
	 * @param array<string, float> $terms stem => tf
	 * @param array<string, mixed> $metadata Additional metadata from the provider.
	 */
	public function __construct( array $terms, array $metadata = array() ) {
		$this->terms    = $terms;
		$this->metadata = $metadata;
	}

	/**
	 * @return array<string, float>
	 */
	public function terms(): array {
		return $this->terms;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function metadata(): array {
		return $this->metadata;
	}

	/**
	 * Whether the provider intentionally returned an empty set (e.g. below thresholds).
	 *
	 * @return bool
	 */
	public function is_filtered(): bool {
		return (bool) ( $this->metadata['filtered'] ?? false );
	}
}
