<?php
/**
 * Resolves hreflang alternates for the current request.
 *
 * @package Airygen\Modules\Hreflang\Domain\Service
 */

declare(strict_types=1);

namespace Airygen\Modules\Hreflang\Domain\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aggregates hreflang alternates for a page.
 */
final class ResolveAlternates {

	/**
	 * Resolve alternate URLs with language codes.
	 *
	 * @param array<string, mixed> $input Input data including integrations and manual map.
	 *
	 * @return array<int, array{hreflang: string, url: string}>
	 */
	public static function for_entry( array $input ): array {
		$alternates = array();

		if ( ! empty( $input['integrations'] ) && is_array( $input['integrations'] ) ) {
			foreach ( $input['integrations'] as $integration ) {
				if ( ! is_callable( $integration['resolver'] ?? null ) ) {
					continue;
				}

				$resolved = call_user_func( $integration['resolver'], $input );
				if ( is_array( $resolved ) ) {
					$alternates = array_merge( $alternates, $resolved );
				}
			}
		}

		if ( ! empty( $input['manual_map'] ) && is_array( $input['manual_map'] ) ) {
			foreach ( $input['manual_map'] as $code => $url ) {
				$code = self::normalize_code( $code );
				$url  = self::normalize_url( $url );

				if ( null === $code || null === $url ) {
					continue;
				}

				$alternates[] = array(
					'hreflang' => $code,
					'url'      => $url,
				);
			}
		}

		$alternates = self::deduplicate( $alternates );

		if ( ! empty( $input['include_x_default'] ) && ! self::has_x_default( $alternates ) ) {
			$self_url = self::normalize_url( $input['self_url'] ?? null );
			if ( $self_url ) {
				$alternates[] = array(
					'hreflang' => 'x-default',
					'url'      => $self_url,
				);
			}
		}

		return $alternates;
	}

	/**
	 * Normalize hreflang code.
	 *
	 * @param mixed $code Hreflang code.
	 *
	 * @return string|null
	 */
	private static function normalize_code( $code ): ?string {
		if ( ! is_string( $code ) ) {
			return null;
		}

		$code = strtolower( trim( $code ) );

		if ( '' === $code ) {
			return null;
		}

		return $code;
	}

	/**
	 * Normalize URL string.
	 *
	 * @param mixed $url URL value.
	 *
	 * @return string|null
	 */
	private static function normalize_url( $url ): ?string {
		if ( ! is_string( $url ) ) {
			return null;
		}

		$url = trim( $url );
		if ( '' === $url ) {
			return null;
		}

		return $url;
	}

	/**
	 * Remove duplicates and invalid entries.
	 *
	 * @param array<int, array{hreflang: string, url: string}> $alternates Alternates.
	 *
	 * @return array<int, array{hreflang: string, url: string}>
	 */
	private static function deduplicate( array $alternates ): array {
		$map = array();

		foreach ( $alternates as $alternate ) {
			$code = $alternate['hreflang'] ?? '';
			$url  = $alternate['url'] ?? '';

			if ( '' === $code || '' === $url ) {
				continue;
			}

			$map[ strtolower( $code ) ] = array(
				'hreflang' => strtolower( $code ),
				'url'      => $url,
			);
		}

		return array_values( $map );
	}

	/**
	 * Determine if x-default is already included.
	 *
	 * @param array<int, array{hreflang: string, url: string}> $alternates Alternates.
	 *
	 * @return bool
	 */
	private static function has_x_default( array $alternates ): bool {
		foreach ( $alternates as $alternate ) {
			if ( 'x-default' === strtolower( $alternate['hreflang'] ?? '' ) ) {
				return true;
			}
		}

		return false;
	}
}
