<?php
/**
 * Builds Article schema nodes.
 *
 * @package Airygen\Modules\SchemaMarkup\Domain\Builder
 */

declare(strict_types=1);

namespace Airygen\Modules\SchemaMarkup\Domain\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds article schema data.
 */
final class ArticleBuilder {

	/**
	 * Build article schema node.
	 *
	 * @param array<string, mixed> $data Article data.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function build( array $data ): ?array {
		$headline = self::string_or_null( $data['headline'] ?? null );
		$url      = self::string_or_null( $data['url'] ?? null );

		if ( null === $headline || null === $url ) {
			return null;
		}

		$type = self::string_or_null( $data['type'] ?? 'Article' );

		$article = array(
			'@type'            => null === $type ? 'Article' : $type,
			'@id'              => untrailingslashit( $url ) . '#article',
			'headline'         => $headline,
			'url'              => $url,
			'datePublished'    => self::string_or_null( $data['datePublished'] ?? null ),
			'dateModified'     => self::string_or_null( $data['dateModified'] ?? null ),
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => untrailingslashit( $url ) . '#webpage',
			),
		);

		$description = self::string_or_null( $data['description'] ?? null );
		if ( null !== $description ) {
			$article['description'] = $description;
		}

		$image = self::string_or_null( $data['image'] ?? null );
		if ( null !== $image ) {
			$article['image'] = $image;
		}

		$image_object = self::image_object( $data['imageObject'] ?? null );
		if ( null !== $image_object ) {
			$article['image'] = $image_object;
		}

		$author = self::author_node( $data['author'] ?? array() );
		if ( null !== $author ) {
			$article['author'] = $author;
		}

		$publisher = self::publisher_node( $data['publisher'] ?? array() );
		if ( null !== $publisher ) {
			$article['publisher'] = $publisher;
		}

		return $article;
	}

	/**
	 * Build author node.
	 *
	 * @param array<string, mixed> $author Author data.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function author_node( array $author ): ?array {
		$name = self::string_or_null( $author['name'] ?? null );
		if ( null === $name ) {
			return null;
		}

		$type = self::string_or_null( $author['type'] ?? 'Person' );
		$url  = self::string_or_null( $author['url'] ?? null );
		$id   = self::string_or_null( $author['@id'] ?? null );

		$node = array(
			'@type' => null === $type ? 'Person' : $type,
			'name'  => $name,
		);

		if ( null !== $id ) {
			$node['@id'] = $id;
		}

		if ( null !== $url ) {
			$node['url'] = $url;
		}

		$same_as = self::same_as( $author['sameAs'] ?? array() );
		if ( ! empty( $same_as ) ) {
			$node['sameAs'] = $same_as;
		}

		return $node;
	}

	/**
	 * Normalize sameAs payload.
	 *
	 * @param mixed $same_as Raw sameAs.
	 *
	 * @return array<int, string>
	 */
	private static function same_as( $same_as ): array {
		if ( ! is_array( $same_as ) ) {
			return array();
		}

		$values = array();
		foreach ( $same_as as $value ) {
			$url = self::string_or_null( $value );
			if ( null === $url ) {
				continue;
			}
			if ( in_array( $url, $values, true ) ) {
				continue;
			}
			$values[] = $url;
		}

		return $values;
	}

	/**
	 * Build image object node when URL provided.
	 *
	 * @param mixed $image Image definition payload.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function image_object( $image ): ?array {
		if ( ! is_array( $image ) ) {
			return null;
		}

		$url = self::string_or_null( $image['url'] ?? null );
		if ( null === $url ) {
			return null;
		}

		return array(
			'@type' => 'ImageObject',
			'url'   => $url,
		);
	}

	/**
	 * Build publisher node.
	 *
	 * @param array<string, mixed> $publisher Publisher data.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function publisher_node( array $publisher ): ?array {
		$name = self::string_or_null( $publisher['name'] ?? null );
		if ( null === $name ) {
			return null;
		}

		$type = self::string_or_null( $publisher['type'] ?? 'Organization' );

		$publisher_node = array(
			'@type' => null === $type ? 'Organization' : $type,
			'name'  => $name,
		);

		$logo = self::string_or_null( $publisher['logo'] ?? null );
		if ( null !== $logo ) {
			$publisher_node['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $logo,
			);
		}

		return $publisher_node;
	}

	/**
	 * Normalize input string.
	 *
	 * @param mixed $value Arbitrary input.
	 *
	 * @return string|null
	 */
	private static function string_or_null( $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		if ( is_scalar( $value ) ) {
			$value = trim( (string) $value );
			return '' === $value ? null : $value;
		}

		return null;
	}
}
