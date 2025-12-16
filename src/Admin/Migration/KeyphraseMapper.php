<?php
/**
 * Keyphrase mapping helper for migration controllers.
 *
 * @package Airygen\Admin\Migration
 */

declare(strict_types=1);

namespace Airygen\Admin\Migration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Support\Meta\PostData;

/**
 * Normalize imported keyphrases into Airygen focus + long-tail metas.
 */
final class KeyphraseMapper {

	/**
	 * Apply focus/long-tail keyphrases from imported source values.
	 *
	 * @param int                $post_id       Post ID.
	 * @param array<int, mixed>  $source_values Source values that may contain one or multiple keyphrases.
	 *
	 * @return void
	 */
	public static function apply( int $post_id, array $source_values ): void {
		$phrases = array();
		foreach ( $source_values as $value ) {
			$phrases = array_merge( $phrases, self::extract_phrases( $value ) );
		}
		$phrases = self::unique_phrases( $phrases );
		if ( empty( $phrases ) ) {
			return;
		}

		$post_data         = PostData::get( $post_id );
		$current_focus     = trim( $post_data['focusKeyphrase'] );
		$current_long_tail = trim( $post_data['focusLongTail'] );

		if ( '' !== $current_focus ) {
			if ( '' === $current_long_tail ) {
				$remaining = self::remove_phrase( $phrases, $current_focus );
				if ( ! empty( $remaining ) ) {
					PostData::save_field( $post_id, 'focusLongTail', implode( ', ', $remaining ) );
				}
			}
			return;
		}

		$focus = self::shortest_phrase( $phrases );
		if ( '' === $focus ) {
			return;
		}

		PostData::save_field( $post_id, 'focusKeyphrase', $focus );

		if ( '' !== $current_long_tail ) {
			return;
		}

		$remaining = self::remove_phrase( $phrases, $focus );
		if ( ! empty( $remaining ) ) {
			PostData::save_field( $post_id, 'focusLongTail', implode( ', ', $remaining ) );
		}
	}

	/**
	 * Extract keyphrase candidates from mixed source value.
	 *
	 * @param mixed $value Source value.
	 *
	 * @return array<int, string>
	 */
	private static function extract_phrases( $value ): array {
		if ( is_array( $value ) ) {
			$phrases = array();
			foreach ( $value as $item ) {
				$phrases = array_merge( $phrases, self::extract_phrases( $item ) );
			}
			return $phrases;
		}

		if ( is_object( $value ) ) {
			return self::extract_phrases( (array) $value );
		}

		if ( ! is_string( $value ) ) {
			return array();
		}

		$value = trim( $value );
		if ( '' === $value ) {
			return array();
		}

		$unserialized = maybe_unserialize( $value );
		if ( is_array( $unserialized ) || is_object( $unserialized ) ) {
			return self::extract_phrases( $unserialized );
		}

		$decoded = json_decode( $value, true );
		if ( is_array( $decoded ) ) {
			return self::extract_phrases( $decoded );
		}

		$parts = preg_split( '/[\r\n,]+/', $value );
		if ( ! is_array( $parts ) ) {
			return array();
		}

		$phrases = array();
		foreach ( $parts as $part ) {
			$part = sanitize_text_field( trim( (string) $part ) );
			if ( '' === $part ) {
				continue;
			}
			$phrases[] = $part;
		}

		return $phrases;
	}

	/**
	 * Remove duplicates (case-insensitive), preserving order.
	 *
	 * @param array<int, string> $phrases Input phrases.
	 *
	 * @return array<int, string>
	 */
	private static function unique_phrases( array $phrases ): array {
		$seen   = array();
		$result = array();
		foreach ( $phrases as $phrase ) {
			$key = strtolower( trim( $phrase ) );
			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$result[]     = $phrase;
		}
		return $result;
	}

	/**
	 * Select the shortest phrase.
	 *
	 * @param array<int, string> $phrases Candidate phrases.
	 *
	 * @return string
	 */
	private static function shortest_phrase( array $phrases ): string {
		$selected     = '';
		$selected_len = 0;
		foreach ( $phrases as $phrase ) {
			$length = strlen( $phrase );
			if ( '' === $selected || $length < $selected_len ) {
				$selected     = $phrase;
				$selected_len = $length;
			}
		}
		return $selected;
	}

	/**
	 * Remove a phrase from the list (case-insensitive).
	 *
	 * @param array<int, string> $phrases Phrases.
	 * @param string             $remove  Phrase to remove.
	 *
	 * @return array<int, string>
	 */
	private static function remove_phrase( array $phrases, string $remove ): array {
		$remove = strtolower( trim( $remove ) );
		if ( '' === $remove ) {
			return $phrases;
		}

		$result = array();
		foreach ( $phrases as $phrase ) {
			if ( strtolower( trim( $phrase ) ) === $remove ) {
				continue;
			}
			$result[] = $phrase;
		}

		return $result;
	}
}
