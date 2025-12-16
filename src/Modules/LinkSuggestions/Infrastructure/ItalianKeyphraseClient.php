<?php
/**
 * Italian keyphrase client using Snowball stemmer and shared stop words.
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
 * Local keyphrase client for Italian.
 */
final class ItalianKeyphraseClient extends SnowballKeyphraseClient {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			StemmerFactory::create( 'it' ),
			LanguageResources::stop_words( 'it' )
		);
	}
}
