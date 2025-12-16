<?php
/**
 * Determines the redirect to apply for a request.
 *
 * @package Airygen\Modules\Redirects\Domain\Service
 */

declare(strict_types=1);

namespace Airygen\Modules\Redirects\Domain\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\Redirects\Domain\Dto\RedirectMatch;

/**
 * Computes redirect matches based on rule priority.
 */
final class ResolveRedirect {

	/**
	 * Determine redirect match for a path.
	 *
	 * @param string                           $request_path Normalized request path.
	 * @param array<int, array<string, mixed>> $rules        Redirect rules.
	 *
	 * @return RedirectMatch|null
	 */
	public static function from_path( string $request_path, array $rules ): ?RedirectMatch {
		$request_path = self::normalize_path( $request_path );

		if ( '' === $request_path ) {
			$request_path = '/';
		}

		$exact_rules    = array();
		$wildcard_rules = array();
		$regex_rules    = array();

		foreach ( $rules as $rule ) {
			if ( empty( $rule['enabled'] ) || empty( $rule['source'] ) || empty( $rule['target'] ) ) {
				continue;
			}

			switch ( $rule['type'] ?? 'exact' ) {
				case 'wildcard':
					$wildcard_rules[] = $rule;
					break;
				case 'regex':
					$regex_rules[] = $rule;
					break;
				case 'exact':
				default:
					$exact_rules[] = $rule;
					break;
			}
		}

		$match = self::match_exact( $request_path, $exact_rules );
		if ( $match ) {
			return $match;
		}

		$match = self::match_wildcard( $request_path, $wildcard_rules );
		if ( $match ) {
			return $match;
		}

		return self::match_regex( $request_path, $regex_rules );
	}

	/**
	 * Attempt exact matches.
	 *
	 * @param string                           $request_path Request path.
	 * @param array<int, array<string, mixed>> $rules        Rules.
	 *
	 * @return RedirectMatch|null
	 */
	private static function match_exact( string $request_path, array $rules ): ?RedirectMatch {
		foreach ( $rules as $rule ) {
			$source = self::normalize_path( $rule['source'] ?? '' );
			if ( '' === $source ) {
				continue;
			}

			if ( self::paths_equal( $request_path, $source ) ) {
				return new RedirectMatch(
					(string) $rule['target'],
					(int) ( $rule['status'] ?? 301 )
				);
			}
		}

		return null;
	}

	/**
	 * Attempt wildcard matches.
	 *
	 * @param string                           $request_path Request path.
	 * @param array<int, array<string, mixed>> $rules        Rules.
	 *
	 * @return RedirectMatch|null
	 */
	private static function match_wildcard( string $request_path, array $rules ): ?RedirectMatch {
		foreach ( $rules as $rule ) {
			$pattern = self::normalize_path( $rule['source'] ?? '' );
			if ( '' === $pattern ) {
				continue;
			}

			// Convert wildcard to regex using fnmatch when available.
			if ( fnmatch( $pattern, $request_path ) || fnmatch( $pattern, rawurldecode( $request_path ) ) ) {
				$target = self::replace_wildcard_target( $pattern, $request_path, (string) $rule['target'] );

				return new RedirectMatch(
					$target,
					(int) ( $rule['status'] ?? 301 )
				);
			}
		}

		return null;
	}

	/**
	 * Attempt regex matches.
	 *
	 * @param string                           $request_path Request path.
	 * @param array<int, array<string, mixed>> $rules        Rules.
	 *
	 * @return RedirectMatch|null
	 */
	private static function match_regex( string $request_path, array $rules ): ?RedirectMatch {
		foreach ( $rules as $rule ) {
			$pattern = trim( (string) ( $rule['source'] ?? '' ) );
			if ( '' === $pattern ) {
				continue;
			}

			$regex = self::wrap_regex( $pattern );

			if ( @preg_match( $regex, $request_path, $matches ) ) { // phpcs:ignore
				if ( ! preg_match( $regex, $request_path, $matches ) ) {
					continue;
				}

				$target = (string) $rule['target'];
				if ( false !== strpos( $target, '$' ) ) {
					$target = (string) preg_replace( $regex, $target, $request_path );
				}

				return new RedirectMatch(
					$target,
					(int) ( $rule['status'] ?? 301 )
				);
			}
		}

		return null;
	}

	/**
	 * Normalize path by ensuring leading slash and stripping query.
	 *
	 * @param string $path Raw request path.
	 *
	 * @return string
	 */
	private static function normalize_path( string $path ): string {
		$path = parse_url( $path, PHP_URL_PATH ) ?? $path; // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- domain layer cannot depend on wp_parse_url.
		$path = trim( $path );

		if ( '' === $path ) {
			return '/';
		}

		return '/' === $path[0] ? $path : '/' . $path;
	}

	/**
	 * Compare paths while ignoring trailing slashes (unless root).
	 *
	 * @param string $a First path.
	 * @param string $b Second path.
	 *
	 * @return bool
	 */
	private static function paths_equal( string $a, string $b ): bool {
		$a = self::strip_trailing_slash( rawurldecode( $a ) );
		$b = self::strip_trailing_slash( rawurldecode( $b ) );

		return $a === $b;
	}

	/**
	 * Strip trailing slash from path except for root.
	 *
	 * @param string $path Input path.
	 *
	 * @return string
	 */
	private static function strip_trailing_slash( string $path ): string {
		return '/' === $path ? $path : rtrim( $path, '/' );
	}

	/**
	 * Replace wildcard placeholders in target when possible.
	 *
	 * @param string $pattern Wildcard pattern (fnmatch syntax).
	 * @param string $path    Request path.
	 * @param string $target  Target path / URL.
	 *
	 * @return string
	 */
	private static function replace_wildcard_target( string $pattern, string $path, string $target ): string {
		if ( false === strpos( $pattern, '*' ) ) {
			return $target;
		}

		$regex = '#^' . str_replace( '\*', '(.*)', preg_quote( $pattern, '#' ) ) . '$#';
		if ( preg_match( $regex, $path, $matches ) && isset( $matches[1] ) ) {
			$wildcard_value = $matches[1];
			return str_replace( '*', $wildcard_value, $target );
		}

		return $target;
	}

	/**
	 * Wrap user-provided regex with delimiters.
	 *
	 * @param string $pattern Raw pattern.
	 *
	 * @return string
	 */
	private static function wrap_regex( string $pattern ): string {
		$delim = '#';
		if ( $pattern[0] === $pattern[ strlen( $pattern ) - 1 ] && false === ctype_alnum( $pattern[0] ) ) {
			return $pattern;
		}

		return $delim . str_replace( $delim, '\\' . $delim, $pattern ) . $delim . 'i';
	}
}
