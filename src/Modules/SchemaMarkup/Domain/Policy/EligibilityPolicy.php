<?php
/**
 * Determines eligibility for schema nodes.
 *
 * @package Airygen\Modules\SchemaMarkup\Domain\Policy
 */

declare(strict_types=1);

namespace Airygen\Modules\SchemaMarkup\Domain\Policy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Eligibility checks for schema inclusion.
 */
final class EligibilityPolicy {

	/**
	 * Determine if article schema should be included.
	 *
	 * @param array<string, mixed> $article Article data payload.
	 *
	 * @return bool
	 */
	public static function allows_article( array $article ): bool {
		$headline = self::string_or_null( $article['headline'] ?? null );
		$url      = self::string_or_null( $article['url'] ?? null );

		if ( null === $headline || null === $url ) {
			return false;
		}

		$status = self::string_or_null( $article['status'] ?? null );
		if ( 'draft' === $status || 'auto-draft' === $status ) {
			return false;
		}

		return true;
	}

	/**
	 * Normalize string.
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
