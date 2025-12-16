<?php
/**
 * Builds WebPage schema nodes.
 *
 * @package Airygen\Modules\SchemaMarkup\Domain\Builder
 */

declare(strict_types=1);

namespace Airygen\Modules\SchemaMarkup\Domain\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds schema for the current page.
 */
final class WebPageBuilder {

	/**
	 * Build webpage schema node.
	 *
	 * @param array<string, mixed> $data Input array.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function build( array $data ): ?array {
		$name = self::string_or_null( $data['name'] ?? null );
		$url  = self::string_or_null( $data['url'] ?? null );

		if ( null === $name || null === $url ) {
			return null;
		}

		$page = array(
			'@type' => 'WebPage',
			'@id'   => untrailingslashit( $url ) . '#webpage',
			'name'  => $name,
			'url'   => $url,
		);

		$description = self::string_or_null( $data['description'] ?? null );
		if ( null !== $description ) {
			$page['description'] = $description;
		}

		$language = self::string_or_null( $data['language'] ?? null );
		if ( null !== $language ) {
			$page['inLanguage'] = $language;
		}

		return $page;
	}

	/**
	 * Normalize string input.
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
