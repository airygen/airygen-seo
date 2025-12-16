<?php
/**
 * Value object representing a keyphrase request payload.
 *
 * @package Airygen\Modules\LinkSuggestions\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkSuggestions\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encapsulates request fields for keyphrase extraction.
 */
class KeyphraseRequest {

	/** @var string */
	public $language;
	/** @var string */
	public $content;
	/** @var string */
	public $title;
	/** @var string */
	public $description;
	/** @var array<int, string> */
	public $focus_keywords;
	/** @var array<int, string> */
	public $headings;
	/** @var int */
	public $attributes_weight;
	/** @var int */
	public $max_terms;

	/**
	 * @param array<string,mixed> $payload
	 */
	public function __construct( array $payload ) {
		$this->language          = (string) ( $payload['language'] ?? '' );
		$this->content           = (string) ( $payload['content'] ?? '' );
		$this->title             = (string) ( $payload['title'] ?? '' );
		$this->description       = (string) ( $payload['description'] ?? '' );
		$this->focus_keywords    = array_values( (array) ( $payload['focus_keywords'] ?? array() ) );
		$this->headings          = array_values( (array) ( $payload['headings'] ?? array() ) );
		$this->attributes_weight = (int) ( $payload['attributes_weight'] ?? 3 );
		$this->max_terms         = (int) ( $payload['max_terms'] ?? 100 );
	}

	/**
	 * Export as array matching the OpenAPI schema.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'language'       => $this->language,
			'content'        => $this->content,
			'title'          => $this->title,
			'description'    => $this->description,
			'focus_keywords' => $this->focus_keywords,
			'headings'       => $this->headings,
			'options'        => array(
				'attributes_weight' => $this->attributes_weight,
				'max_terms'         => $this->max_terms,
			),
		);
	}
}
