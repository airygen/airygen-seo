<?php
/**
 * Value object describing article schema payload.
 *
 * @package Airygen\Modules\SchemaMarkup\Domain\Contexts
 */

declare(strict_types=1);

namespace Airygen\Modules\SchemaMarkup\Domain\Contexts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable context representation for Article-like schemas.
 *
 * Domain objects must not rely on WordPress functions.
 */
final class ArticleContext {

	/**
	 * Schema type identifier.
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * Primary headline.
	 *
	 * @var string|null
	 */
	private ?string $headline;

	/**
	 * Human-friendly description.
	 *
	 * @var string|null
	 */
	private ?string $description;

	/**
	 * Canonical URL for the article.
	 *
	 * @var string|null
	 */
	private ?string $url;

	/**
	 * Publication timestamp (ISO 8601).
	 *
	 * @var string|null
	 */
	private ?string $date_published;

	/**
	 * Last modified timestamp (ISO 8601).
	 *
	 * @var string|null
	 */
	private ?string $date_modified;

	/**
	 * Publication status (optional).
	 *
	 * @var string|null
	 */
	private ?string $status;

	/**
	 * Representative image URL.
	 *
	 * @var string|null
	 */
	private ?string $image;

	/**
	 * Author metadata.
	 *
	 * @var array<string, mixed>
	 */
	private array $author;

	/**
	 * Publisher metadata.
	 *
	 * @var array<string, string>
	 */
	private array $publisher;

	/**
	 * Instantiate the value object.
	 *
	 * @param string               $type      Schema type identifier.
	 * @param array<string, mixed> $data      Article scalar data.
	 * @param array<string, mixed> $author    Author metadata.
	 * @param array<string, mixed> $publisher Publisher metadata.
	 */
	private function __construct( string $type, array $data, array $author, array $publisher ) {
		$this->type           = $type;
		$this->headline       = self::to_optional_string( $data['headline'] ?? null );
		$this->description    = self::to_optional_string( $data['description'] ?? null );
		$this->url            = self::to_optional_string( $data['url'] ?? null );
		$this->date_published = self::to_optional_string( $data['datePublished'] ?? null );
		$this->date_modified  = self::to_optional_string( $data['dateModified'] ?? null );
		$this->status         = self::to_optional_string( $data['status'] ?? null );
		$this->image          = self::to_optional_string( $data['image'] ?? null );

		$this->author    = self::normalize_author_map( $author );
		$this->publisher = self::filter_string_map( $publisher );
	}

	/**
	 * Build context from provided payload.
	 *
	 * @param string               $type      Schema type identifier.
	 * @param array<string, mixed> $data      Article scalar data.
	 * @param array<string, mixed> $author    Author metadata.
	 * @param array<string, mixed> $publisher Publisher metadata.
	 */
	public static function from_payload( string $type, array $data, array $author, array $publisher ): self {
		return new self( $type, $data, $author, $publisher );
	}

	/**
	 * Export canonical schema array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$schema = array(
			'type'          => $this->type,
			'headline'      => $this->headline,
			'description'   => $this->description,
			'url'           => $this->url,
			'datePublished' => $this->date_published,
			'dateModified'  => $this->date_modified,
			'author'        => $this->author,
			'publisher'     => $this->publisher,
			'image'         => $this->image,
			'imageObject'   => $this->image ? array( 'url' => $this->image ) : null,
		);

		if ( null !== $this->status ) {
			$schema['publicationStatus'] = $this->status;
		}

		return array_filter(
			$schema,
			static function ( $value ) {
				if ( is_array( $value ) ) {
					return ! empty( $value );
				}

				return null !== $value && '' !== $value;
			}
		);
	}

	/**
	 * Helper to normalize optional string values.
	 *
	 * @param mixed $value Potential string value.
	 */
	private static function to_optional_string( $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		if ( is_string( $value ) ) {
			$trimmed = trim( $value );
			return '' === $trimmed ? null : $trimmed;
		}

		if ( is_scalar( $value ) ) {
			$trimmed = trim( (string) $value );
			return '' === $trimmed ? null : $trimmed;
		}

		return null;
	}

	/**
	 * Normalize associative arrays to string maps.
	 *
	 * @param array<string, mixed> $payload Raw array.
	 *
	 * @return array<string, string>
	 */
	private static function filter_string_map( array $payload ): array {
		$result = array();

		foreach ( $payload as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}

			$string_value = self::to_optional_string( $value );
			if ( null === $string_value ) {
				continue;
			}

			$result[ $key ] = $string_value;
		}

		return $result;
	}

	/**
	 * Normalize author payload while preserving sameAs arrays.
	 *
	 * @param array<string, mixed> $payload Raw author map.
	 *
	 * @return array<string, mixed>
	 */
	private static function normalize_author_map( array $payload ): array {
		$author = self::filter_string_map( $payload );

		if ( isset( $payload['sameAs'] ) && is_array( $payload['sameAs'] ) ) {
			$same_as = array();
			foreach ( $payload['sameAs'] as $value ) {
				$string_value = self::to_optional_string( $value );
				if ( null === $string_value ) {
					continue;
				}

				if ( in_array( $string_value, $same_as, true ) ) {
					continue;
				}

				$same_as[] = $string_value;
			}

			if ( ! empty( $same_as ) ) {
				$author['sameAs'] = $same_as;
			}
		}

		return $author;
	}
}
