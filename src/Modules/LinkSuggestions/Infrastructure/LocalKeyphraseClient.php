<?php
/**
 * Local (non-CJK) keyphrase extraction using simple tokenization and stemming.
 *
 * @package Airygen\Modules\LinkSuggestions\Infrastructure
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkSuggestions\Infrastructure;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\LinkSuggestions\Domain\KeyphraseClientInterface;
use Airygen\Modules\LinkSuggestions\Domain\KeyphraseDto;
use Airygen\Modules\LinkSuggestions\Domain\KeyphraseRequest;

use function array_filter;
use function array_slice;
use function arsort;
use function mb_strlen;
use function mb_strtolower;
use function preg_split;
use function strlen;
use function trim;
use function wp_strip_all_tags;

/**
 * Basic TF extractor for non-CJK languages (with optional stemming/stopwords).
 */
class LocalKeyphraseClient implements KeyphraseClientInterface {

	/** @var array<string, bool> */
	private $stop_words;

	/** @var bool */
	private $use_stemming;

	/** @var bool */
	private $has_morphology;

	/** @var callable|null */
	protected $custom_stemmer;

	/**
	 * @param array<string,bool> $stop_words    Stop words lookup.
	 * @param bool               $use_stemming  Whether to apply lightweight stemming.
	 * @param bool               $has_morphology Whether the language has morphology data (affects min occurrence threshold).
	 * @param callable|null      $custom_stemmer Optional custom stemmer callback accepting (string):string.
	 */
	public function __construct( array $stop_words, bool $use_stemming = true, bool $has_morphology = true, $custom_stemmer = null ) {
		$this->stop_words     = $stop_words;
		$this->use_stemming   = $use_stemming;
		$this->has_morphology = $has_morphology;
		$this->custom_stemmer = $custom_stemmer;
	}

	/**
	 * {@inheritdoc}
	 */
	public function fetch( KeyphraseRequest $request ): KeyphraseDto {
		$text_blocks = array(
			(string) $request->content,
		);

		// Title and description are included once.
		$text_blocks[] = (string) $request->title;
		$text_blocks[] = (string) $request->description;

		foreach ( (array) $request->headings as $heading ) {
			$text_blocks[] = (string) $heading;
		}

		$combined        = trim( implode( "\n", array_filter( $text_blocks ) ) );
		$tokens          = $this->tokenize( $combined );
		$max_terms       = $request->max_terms > 0 ? $request->max_terms : 100;
		$min_occurrences = $this->has_morphology ? 4 : 2;

		$freq = array();
		foreach ( $tokens as $token ) {
			if ( '' === $token ) {
				continue;
			}
			$freq[ $token ] = isset( $freq[ $token ] ) ? $freq[ $token ] + 1 : 1;
		}

		$focus_terms = $this->tokenize( implode( ' ', (array) $request->focus_keywords ) );
		foreach ( $focus_terms as $focus_term ) {
			if ( '' === $focus_term ) {
				continue;
			}
			$freq[ $focus_term ] = 5;
		}

		if ( empty( $freq ) ) {
			return new KeyphraseDto(
				array(),
				array(
					'filtered'      => true,
					'lang_handled'  => $request->language,
					'vector_length' => null,
				)
			);
		}

		arsort( $freq, SORT_NUMERIC );
		$filtered = array_filter(
			$freq,
			static function ( int $count ) use ( $min_occurrences ): bool {
				return $count >= $min_occurrences;
			}
		);

		arsort( $filtered, SORT_NUMERIC );
		$limited = array_slice( $filtered, 0, $max_terms, true );

		return new KeyphraseDto(
			$limited,
			array(
				'filtered'      => false,
				'lang_handled'  => $request->language,
				'vector_length' => null,
			)
		);
	}

	/**
	 * Tokenize and stem ASCII/Latin words.
	 *
	 * @param string $text Raw text.
	 *
	 * @return array<int,string>
	 */
	private function tokenize( string $text ): array {
		$plain  = wp_strip_all_tags( $text );
		$tokens = preg_split( '/[^\\p{L}\\p{N}\']+/u', $plain );
		if ( ! is_array( $tokens ) ) {
			$tokens = array();
		}
		$result = array();
		$stop   = $this->stop_words;

		foreach ( $tokens as $token ) {
			$token = trim( $token );
			$token = trim( $token, "'’" );
			$token = str_replace( array( "'", '’' ), '', $token );
			if ( '' === $token ) {
				continue;
			}

			$lower = $this->lower( $token );
			if ( isset( $stop[ $lower ] ) ) {
				continue;
			}

			$stem = $this->stem( $lower );
			if ( '' === $stem ) {
				continue;
			}

			$result[] = $stem;
		}

		return $result;
	}

	/**
	 * Repeat text block to simulate weighting.
	 *
	 * @param string $text     Text to repeat.
	 * @param int    $times    Times to repeat.
	 *
	 * @return string
	 */
	private function repeat_text( string $text, int $times ): string {
		if ( $times <= 1 ) {
			return $text;
		}
		return trim( ( str_repeat( $text . ' ', $times ) ) );
	}

	/**
	 * Safe lowercase that prefers mbstring.
	 *
	 * @param string $value Input.
	 *
	 * @return string
	 */
	private function lower( string $value ): string {
		if ( function_exists( 'mb_strtolower' ) ) {
			return (string) mb_strtolower( $value, 'UTF-8' );
		}
		return (string) strtolower( $value );
	}


	/**
	 * Very lightweight stemming for English-like tokens.
	 *
	 * @param string $token Raw token.
	 *
	 * @return string
	 */
	protected function stem( string $token ): string {
		if ( $this->custom_stemmer ) {
			return (string) call_user_func( $this->custom_stemmer, $token );
		}

		if ( ! $this->use_stemming ) {
			return $token;
		}

		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $token, 'UTF-8' ) : strlen( $token );
		if ( $len <= 3 ) {
			return $token;
		}

		// Common endings.
		foreach ( array( 'ing', 'ed', 'ly', 'es', 's' ) as $suffix ) {
			if ( ! $this->ends_with( $token, $suffix ) ) {
				continue;
			}

			$trimmed = $this->substr( $token, 0, $len - strlen( $suffix ) );
			if ( '' !== $trimmed ) {
				return $trimmed;
			}
		}

		return $token;
	}

	/**
	 * Check suffix.
	 *
	 * @param string $value String.
	 * @param string $suffix Suffix.
	 *
	 * @return bool
	 */
	private function ends_with( string $value, string $suffix ): bool {
		return '' === $suffix || substr( $value, -strlen( $suffix ) ) === $suffix;
	}

	/**
	 * mb_substr fallback.
	 *
	 * @param string $value String.
	 * @param int    $start Start.
	 * @param int    $length Length.
	 *
	 * @return string
	 */
	private function substr( string $value, int $start, int $length ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $value, $start, $length, 'UTF-8' );
		}
		return (string) substr( $value, $start, $length );
	}
}
