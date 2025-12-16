<?php
/**
 * Approximates text width in pixels for scoring heuristics.
 *
 * @package Airygen\Modules\ScoreCalculator\Domain
 */

declare(strict_types=1);

namespace Airygen\Modules\ScoreCalculator\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides a rough pixel estimation for titles using character averages.
 */
final class TitlePixelEstimator {

	private const DEFAULT_WIDTH = 9;
	private const CJK_WIDTH     = 12;

	/**
	 * Character width map derived from common SERP fonts.
	 *
	 * @var array<string, int>
	 */
	private static array $width_map = array(
		'a'  => 8,
		'b'  => 9,
		'c'  => 8,
		'd'  => 9,
		'e'  => 8,
		'f'  => 6,
		'g'  => 9,
		'h'  => 9,
		'i'  => 4,
		'j'  => 5,
		'k'  => 8,
		'l'  => 4,
		'm'  => 12,
		'n'  => 9,
		'o'  => 9,
		'p'  => 9,
		'q'  => 9,
		'r'  => 6,
		's'  => 8,
		't'  => 6,
		'u'  => 9,
		'v'  => 8,
		'w'  => 12,
		'x'  => 8,
		'y'  => 8,
		'z'  => 8,
		'0'  => 9,
		'1'  => 7,
		'2'  => 9,
		'3'  => 9,
		'4'  => 9,
		'5'  => 9,
		'6'  => 9,
		'7'  => 9,
		'8'  => 9,
		'9'  => 9,
		' '  => 4,
		'-'  => 6,
		'_'  => 6,
		'.'  => 4,
		','  => 4,
		':'  => 4,
		';'  => 4,
		'!'  => 4,
		'?'  => 8,
		'('  => 5,
		')'  => 5,
		'['  => 5,
		']'  => 5,
		'{'  => 6,
		'}'  => 6,
		"'"  => 4,
		'"'  => 6,
		'/'  => 6,
		'\\' => 6,
		'@'  => 12,
		'#'  => 10,
		'%'  => 12,
		'&'  => 11,
		'+'  => 9,
		'='  => 9,
	);

	/**
	 * Estimate pixel width for provided text.
	 *
	 * @param string $text Text to estimate.
	 */
	public static function estimate( string $text ): int {
		if ( '' === $text ) {
			return 0;
		}

		$characters = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $characters ) ) {
			return 0;
		}

		$total_width = 0;
		foreach ( $characters as $character ) {
			$normalized_character = mb_strtolower( $character );
			if ( isset( self::$width_map[ $normalized_character ] ) ) {
				$total_width += self::$width_map[ $normalized_character ];
			} elseif ( self::is_cjk( $character ) ) {
				$total_width += self::CJK_WIDTH;
			} else {
				$total_width += self::DEFAULT_WIDTH;
			}
		}

		return (int) round( $total_width );
	}

	/**
	 * Detect whether a character is CJK (approximate wider glyph width).
	 *
	 * @param string $character Character to evaluate.
	 *
	 * @return bool
	 */
	private static function is_cjk( string $character ): bool {
		return 1 === preg_match( '/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', $character );
	}
}
