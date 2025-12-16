<?php
/**
 * Factory to create cached LocalKeyphraseClient instances per language.
 *
 * @package Airygen\Modules\LinkSuggestions\Infrastructure
 */

declare(strict_types=1);

namespace Airygen\Modules\LinkSuggestions\Infrastructure;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airygen\Modules\LinkSuggestions\Domain\KeyphraseClientInterface;
use Airygen\Modules\LinkSuggestions\Infrastructure\IndonesianKeyphraseClient;
use Airygen\Modules\LinkSuggestions\Infrastructure\SnowballKeyphraseClient;
use Wamania\Snowball\StemmerFactory;

/**
 * Builds LocalKeyphraseClient with language-specific stop words.
 */
final class LocalKeyphraseClientFactory {

	/** @var array<string, KeyphraseClientInterface> */
	private static $cache = array();

	/**
	 * Get a LocalKeyphraseClient for a language (cached).
	 *
	 * @param string $language Language code (lowercase).
	 * @return KeyphraseClientInterface
	 */
	public static function for( string $language ): KeyphraseClientInterface {
		$key = strtolower( $language );

		if ( isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}

		$custom_clients = array(
			'id' => IndonesianKeyphraseClient::class,
		);

		if ( isset( $custom_clients[ $key ] ) ) {
			$class               = $custom_clients[ $key ];
			$client              = new $class();
			self::$cache[ $key ] = $client;
			return $client;
		}

		$supported = array(
			'en',
			'de',
			'es',
			'fr',
			'it',
			'pt',
			'nl',
			'ru',
			'da',
			'fi',
			'no',
			'nb',
			'ro',
			'sv',
			'tr',
		);

		if ( in_array( $key, $supported, true ) ) {
			$stemmer_lang = 'nb' === $key ? 'no' : $key;
			$stemmer      = StemmerFactory::create( $stemmer_lang );
			$stop_words   = LanguageResources::stop_words( $key );

			$client              = new SnowballKeyphraseClient( $stemmer, $stop_words );
			self::$cache[ $key ] = $client;
			return $client;
		}

		$stop_words     = LanguageResources::stop_words( $key );
		$use_stemming   = false;
		$has_morphology = false;

		$client              = new LocalKeyphraseClient( $stop_words, $use_stemming, $has_morphology );
		self::$cache[ $key ] = $client;

		return $client;
	}
}
