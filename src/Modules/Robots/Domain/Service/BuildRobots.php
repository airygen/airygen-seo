<?php
/**
 * Domain service for computing robots directives.
 *
 * @package Airygen\Modules\Robots\Domain\Service
 */

declare(strict_types=1);

namespace Airygen\Modules\Robots\Domain\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\Robots\Domain\Dto\RobotsDirectives;

/**
 * Computes robots meta directives for a page.
 */
final class BuildRobots {

	/**
	 * Build directives for a singular entry.
	 *
	 * @param array<string, mixed> $input Input payload containing defaults and overrides.
	 *
	 * @return RobotsDirectives
	 */
	public static function for_entry( array $input ): RobotsDirectives {
		$global_directive = self::normalize_directive( $input['global'] ?? null );
		$per_post         = self::normalize_directive( $input['per_post'] ?? null );

		$directive = $per_post ?? $global_directive;

		if ( isset( $input['is_search'] ) && $input['is_search'] ) {
			$directive = $directive ?? 'noindex,follow';
		}

		if ( isset( $input['is_attachment'] ) && $input['is_attachment'] ) {
			$directive = $directive ?? 'noindex,follow';
		}

		if ( isset( $input['is_404'] ) && $input['is_404'] ) {
			$directive = $directive ?? 'noindex,follow';
		}

		if ( ! empty( $input['is_author'] ) && ! empty( $input['author_noindex'] ) ) {
			$directive = 'noindex,follow';
		}

		$suppress = isset( $input['suppress_default'] ) ? (bool) $input['suppress_default'] : false;

		return new RobotsDirectives( $directive, $suppress );
	}

	/**
	 * Build robots.txt directives.
	 *
	 * @param array<string, mixed> $input Input payload.
	 *
	 * @return array<int, string>
	 */
	public static function for_robots_txt( array $input ): array {
		$rules = array();

		$base_rules = isset( $input['base_rules'] ) && is_array( $input['base_rules'] ) ? $input['base_rules'] : array();
		foreach ( $base_rules as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			$rules[] = $line;
		}

		if ( ! empty( $input['additional_rules'] ) && is_array( $input['additional_rules'] ) ) {
			foreach ( $input['additional_rules'] as $line ) {
				$line = trim( (string) $line );
				if ( '' === $line ) {
					continue;
				}
				$rules[] = $line;
			}
		}

		return $rules;
	}

	/**
	 * Normalize a robots directive string.
	 *
	 * @param mixed $value Arbitrary input.
	 *
	 * @return string|null
	 */
	private static function normalize_directive( $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		if ( is_scalar( $value ) ) {
			$value = trim( strtolower( (string) $value ) );
			if ( '' === $value || 'index,follow' === $value ) {
				return null;
			}

			return $value;
		}

		return null;
	}
}
