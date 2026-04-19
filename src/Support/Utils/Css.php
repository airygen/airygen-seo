<?php
/**
 * CSS sanitization helpers shared across front-end renderers.
 *
 * @package Airygen\Support\Utils
 */

declare(strict_types=1);

namespace Airygen\Support\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Css {

	private const KEYWORD_COLORS = array(
		'transparent',
		'inherit',
		'initial',
		'unset',
		'currentcolor',
		'revert',
	);

	private const BORDER_STYLES = array(
		'none',
		'hidden',
		'dotted',
		'dashed',
		'solid',
		'double',
		'groove',
		'ridge',
		'inset',
		'outset',
	);

	private const LIST_STYLES = array(
		'none',
		'disc',
		'decimal',
	);

	/**
	 * Sanitize a CSS color value, returning the fallback when invalid.
	 *
	 * Accepts hex (#rgb, #rgba, #rrggbb, #rrggbbaa), rgb()/rgba(), hsl()/hsla(),
	 * and a short list of CSS color keywords. Anything else falls back so that
	 * untrusted input cannot break out of a CSS rule context.
	 *
	 * @param mixed  $value    Candidate value.
	 * @param string $fallback Fallback returned when the value is invalid.
	 *
	 * @return string
	 */
	public static function sanitize_color( $value, string $fallback = '' ): string {
		if ( ! is_string( $value ) ) {
			return $fallback;
		}

		$candidate = trim( $value );
		if ( '' === $candidate ) {
			return $fallback;
		}

		$lower = strtolower( $candidate );
		if ( in_array( $lower, self::KEYWORD_COLORS, true ) ) {
			return $lower;
		}

		if ( 1 === preg_match( '/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $candidate ) ) {
			return $candidate;
		}

		if ( 1 === preg_match( '/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/i', $candidate ) ) {
			return $candidate;
		}

		if ( 1 === preg_match( '/^hsla?\(\s*-?\d{1,3}(?:\.\d+)?\s*,\s*\d{1,3}(?:\.\d+)?%\s*,\s*\d{1,3}(?:\.\d+)?%(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/i', $candidate ) ) {
			return $candidate;
		}

		if ( 1 === preg_match( '/^[a-z]{3,20}$/i', $candidate ) ) {
			return $lower;
		}

		return $fallback;
	}

	/**
	 * Sanitize a CSS border-style value against the standard keyword list.
	 *
	 * @param mixed  $value    Candidate value.
	 * @param string $fallback Fallback returned when the value is invalid.
	 *
	 * @return string
	 */
	public static function sanitize_border_style( $value, string $fallback = 'solid' ): string {
		if ( ! is_string( $value ) ) {
			return $fallback;
		}

		$lower = strtolower( trim( $value ) );
		if ( in_array( $lower, self::BORDER_STYLES, true ) ) {
			return $lower;
		}

		return $fallback;
	}

	/**
	 * Sanitize a list-style value against the supported keyword list.
	 *
	 * @param mixed  $value    Candidate value.
	 * @param string $fallback Fallback returned when the value is invalid.
	 *
	 * @return string
	 */
	public static function sanitize_list_style( $value, string $fallback = 'none' ): string {
		if ( ! is_string( $value ) ) {
			return $fallback;
		}

		$lower = strtolower( trim( $value ) );
		if ( in_array( $lower, self::LIST_STYLES, true ) ) {
			return $lower;
		}

		return $fallback;
	}
}
