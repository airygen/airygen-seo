<?php
/**
 * Builds Organization schema nodes.
 *
 * @package Airygen\Modules\SchemaMarkup\Domain\Builder
 */

declare(strict_types=1);

namespace Airygen\Modules\SchemaMarkup\Domain\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds organization schema data.
 */
final class OrganizationBuilder {

	/**
	 * Build organization schema node.
	 *
	 * @param array<string, mixed> $data Input data.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function build( array $data ): ?array {
		$name = self::string_or_null( $data['name'] ?? null );
		if ( null === $name ) {
			return null;
		}

		$type = self::string_or_null( $data['type'] ?? 'Organization' );

		$organization = array(
			'@type' => null === $type ? 'Organization' : $type,
			'name'  => $name,
		);

		$url = self::string_or_null( $data['url'] ?? null );
		if ( null !== $url ) {
			$organization['url'] = $url;
			$organization['@id'] = trailingslashit( $url ) . '#identity';
		}

		$logo_url = self::string_or_null( $data['logo'] ?? null );
		if ( null !== $logo_url ) {
			$organization['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $logo_url,
			);
		}

		return $organization;
	}

	/**
	 * Normalize input string.
	 *
	 * @param mixed $value Arbitrary value.
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
