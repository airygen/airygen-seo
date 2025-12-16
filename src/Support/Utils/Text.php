<?php
/**
 * Text utilities shared across modules.
 *
 * @package Airygen\Support\Utils
 */

declare(strict_types=1);

namespace Airygen\Support\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Text {

	/**
	 * Detect if a string contains at least $min characters from CJK ranges.
	 *
	 * @param string $text Input text.
	 * @param int    $min  Minimum CJK characters required to consider the text CJK.
	 *
	 * @return bool
	 */
	public static function is_cjk( string $text, int $min = 1 ): bool {
		if ( '' === $text || $min <= 0 ) {
			return false;
		}

		if ( ! preg_match_all( '/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', $text, $matches ) ) {
			return false;
		}

		$count = isset( $matches[0] ) ? count( $matches[0] ) : 0;

		return $count >= $min;
	}
}
