<?php
/**
 * Indonesian stemmer (simplified) without external extensions.
 *
 * @package Airygen\Modules\LinkSuggestions\Languages\Id
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkSuggestions\Languages\Id;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use function mb_strlen;
use function mb_strtolower;
use function mb_substr;
use function strlen;
use function substr;

/**
 * Minimal Indonesian stemming for common affixes.
 */
final class Stemmer {

	/**
	 * Stem a single token.
	 *
	 * @param string $token Raw token.
	 * @return string
	 */
	public static function stem( string $token ): string {
		$token = self::lower( $token );
		$len   = self::length( $token );

		if ( $len <= 3 ) {
			return $token;
		}

		$prefixes = array( 'meng', 'meny', 'men', 'mem', 'me', 'ber', 'bel', 'be', 'per', 'pel', 'se', 'ke' );
		foreach ( $prefixes as $prefix ) {
			if ( self::starts_with( $token, $prefix ) && self::length( $token ) - self::length( $prefix ) >= 3 ) {
				$token = self::substr( $token, self::length( $prefix ), self::length( $token ) - self::length( $prefix ) );
				break;
			}
		}

		$suffixes = array( 'kan', 'an', 'nya', 'lah', 'kah', 'pun' );
		foreach ( $suffixes as $suffix ) {
			if ( self::ends_with( $token, $suffix ) && self::length( $token ) - self::length( $suffix ) >= 3 ) {
				$token = self::substr( $token, 0, self::length( $token ) - self::length( $suffix ) );
				break;
			}
		}

		return $token;
	}

	private static function lower( string $value ): string {
		if ( function_exists( 'mb_strtolower' ) ) {
			return (string) mb_strtolower( $value, 'UTF-8' );
		}
		return strtolower( $value );
	}

	private static function length( string $value ): int {
		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( $value, 'UTF-8' );
		}
		return (int) strlen( $value );
	}

	private static function substr( string $value, int $start, int $length ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $value, $start, $length, 'UTF-8' );
		}
		return (string) substr( $value, $start, $length );
	}

	private static function ends_with( string $value, string $suffix ): bool {
		return '' === $suffix || self::substr( $value, self::length( $value ) - self::length( $suffix ), self::length( $suffix ) ) === $suffix;
	}

	private static function starts_with( string $value, string $prefix ): bool {
		return '' === $prefix || self::substr( $value, 0, self::length( $prefix ) ) === $prefix;
	}
}
