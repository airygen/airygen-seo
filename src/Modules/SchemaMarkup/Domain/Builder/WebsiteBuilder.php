<?php
/**
 * Builds WebSite schema nodes.
 *
 * @package Airygen\Modules\SchemaMarkup\Domain\Builder
 */

declare(strict_types=1);

namespace Airygen\Modules\SchemaMarkup\Domain\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds schema for the site itself.
 */
final class WebsiteBuilder {

	/**
	 * Build website schema node.
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

		$website = array(
			'@type' => 'WebSite',
			'@id'   => untrailingslashit( $url ) . '#website',
			'name'  => $name,
			'url'   => $url,
		);

		$language = self::string_or_null( $data['language'] ?? null );
		if ( null !== $language ) {
			$website['inLanguage'] = $language;
		}

		$search_url     = self::string_or_null( $data['search_url'] ?? null );
		$search_query   = self::string_or_null( $data['search_query_param'] ?? 's' );
		$potential_name = self::string_or_null( $data['potential_action_name'] ?? 'Search' );
		$query_param    = null === $search_query ? 's' : $search_query;
		$action_name    = null === $potential_name ? 'Search' : $potential_name;

		if ( $search_url ) {
			$website['potentialAction'] = array(
				'@type'       => 'SearchAction',
				'target'      => $search_url,
				'query-input' => 'required name=' . $query_param,
				'name'        => $action_name,
			);
		}

		return $website;
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
