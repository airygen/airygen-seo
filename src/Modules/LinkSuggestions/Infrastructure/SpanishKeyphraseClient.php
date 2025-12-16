<?php
/**
 * Spanish keyphrase client using Snowball stemmer and shared stop words.
 *
 * @package Airygen\Modules\LinkSuggestions\Infrastructure
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkSuggestions\Infrastructure;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Wamania\Snowball\StemmerFactory;

/**
 * Local keyphrase client for Spanish.
 */
final class SpanishKeyphraseClient extends SnowballKeyphraseClient {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			StemmerFactory::create( 'es' ),
			LanguageResources::stop_words( 'es' )
		);
	}
}
