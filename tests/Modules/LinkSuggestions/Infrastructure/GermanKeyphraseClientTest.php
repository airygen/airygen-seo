<?php
/**
 * Tests for the German keyphrase client.
 *
 * @package Airygen\Tests\Modules\LinkSuggestions\Infrastructure
 */

declare(strict_types=1);

namespace Airygen\Tests\Modules\LinkSuggestions\Infrastructure;

use Airygen\Modules\LinkSuggestions\Infrastructure\GermanKeyphraseClient;
use Airygen\Modules\LinkSuggestions\Domain\KeyphraseRequest;
use AirygenTest\BaseTestCase;

/**
 * @covers \Airygen\Modules\LinkSuggestions\Infrastructure\GermanKeyphraseClient
 */
class GermanKeyphraseClientTest extends BaseTestCase {

	public function test_extracts_tf_for_sample_payload(): void {
		$payload = array(
			'language'          => 'de',
			'title'             => 'Schnelle Autos und Sicherheit',
			'description'       => 'Ein kurzer Artikel über schnelle Autos und wie man sicher fährt.',
			'content'           => 'Die schnellen Autos fahren schneller als andere Autos. Sicherheit ist wichtig, besonders wenn man schnell fährt. '
				. 'Diese Autos wurden getestet und sind schnell und sicher. Viele Fahrer möchten wissen, wie sie die Sicherheit erhöhen können, '
				. 'wenn sie mit hoher Geschwindigkeit unterwegs sind. Deshalb vergleichen sie verschiedene Modelle und achten darauf, '
				. 'dass die Bremsen stark genug sind. Auch die Reifen spielen eine Rolle: sie müssen bei Regen und Schnee gut haften. '
				. 'Am Ende zählt, dass das Auto schnell, aber auch sicher und komfortabel bleibt, damit jeder sicher ankommt.',
			'focus_keywords'    => array( 'schnelles Auto', 'Sicherheit' ),
			'headings'          => array(
				'Warum schnelle Autos wichtig sind',
				'Sicherheit bei hoher Geschwindigkeit',
			),
			'min_words'         => 5,
			'attributes_weight' => 3,
			'max_terms'         => 20,
		);

		$client = new GermanKeyphraseClient();
		$dto    = $client->fetch( new KeyphraseRequest( $payload ) );

		$this->assertFalse( $dto->is_filtered() );

		$terms = $dto->terms();
		$this->assertSame( 5, (int) ( $terms['schnell'] ?? 0 ) );
		$this->assertSame( 5, (int) ( $terms['auto'] ?? 0 ) );
		$this->assertSame( 5, (int) ( $terms['sich'] ?? 0 ) );
		$this->assertArrayHasKey( 'autos', $terms );
		$this->assertArrayNotHasKey( 'und', $dto->terms(), 'Stopword und should be filtered' );

		$this->assertSame(
			array(
				'filtered'      => false,
				'lang_handled'  => 'de',
				'vector_length' => null,
			),
			$dto->metadata()
		);
	}
}
