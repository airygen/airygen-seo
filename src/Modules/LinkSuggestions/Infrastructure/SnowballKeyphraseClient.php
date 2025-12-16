<?php
/**
 * Snowball-based keyphrase client for non-CJK languages.
 *
 * @package Airygen\Modules\LinkSuggestions\Infrastructure
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkSuggestions\Infrastructure;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wraps a Snowball stemmer with shared LocalKeyphraseClient logic.
 */
class SnowballKeyphraseClient extends LocalKeyphraseClient {

	/**
	 * Constructor.
	 *
	 * @param object             $stemmer    Snowball stemmer instance (must expose stem(string):string).
	 * @param array<string,bool> $stop_words Stop words lookup.
	 */
	public function __construct( $stemmer, array $stop_words ) {
		parent::__construct(
			$stop_words,
			true,
			true,
			static function ( string $token ) use ( $stemmer ): string {
				return $stemmer->stem( $token );
			}
		);
	}
}
