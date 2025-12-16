<?php
/**
 * Indonesian keyphrase client using custom lightweight stemmer and stop words.
 *
 * @package Airygen\Modules\LinkSuggestions\Infrastructure
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkSuggestions\Infrastructure;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\LinkSuggestions\Languages\Id\Stemmer;

final class IndonesianKeyphraseClient extends LocalKeyphraseClient {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			LanguageResources::stop_words( 'id' ),
			true,
			true,
			static function ( string $token ): string {
				return Stemmer::stem( $token );
			}
		);
	}
}
