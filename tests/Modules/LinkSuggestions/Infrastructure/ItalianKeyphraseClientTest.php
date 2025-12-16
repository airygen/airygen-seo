<?php
/**
 * Tests for Italian keyphrase client.
 *
 * @package AirygenTest\Modules\LinkSuggestions
 */

declare(strict_types=1);

namespace AirygenTest\Modules\LinkSuggestions;

use Airygen\Modules\LinkSuggestions\Domain\KeyphraseRequest;
use Airygen\Modules\LinkSuggestions\Infrastructure\LocalKeyphraseClientFactory;
use AirygenTest\BaseTestCase;

/**
 * @coversDefaultClass \Airygen\Modules\LinkSuggestions\Infrastructure\ItalianKeyphraseClient
 */
class ItalianKeyphraseClientTest extends BaseTestCase {

	public function test_extracts_tf_for_sample_payload(): void {
		$payload = array(
			'language'          => 'it',
			'title'             => 'Auto veloci e sicure',
			'description'       => 'Un breve articolo sulle auto veloci e la sicurezza stradale.',
			'content'           => 'Le auto veloci arrivano prima delle altre auto. La sicurezza è essenziale quando si guida veloce. '
				. 'Queste auto sono state testate e sono veloci e sicure. Molti conducenti vogliono sapere come migliorare la sicurezza '
				. 'quando viaggiano ad alta velocità. Confrontano quindi diversi modelli e verificano che i freni siano forti. '
				. 'Anche gli pneumatici contano: devono aderire bene con pioggia e neve. Alla fine conta che l’auto sia veloce ma anche sicura e confortevole.',
			'focus_keywords'    => array( 'auto veloce', 'sicurezza' ),
			'headings'          => array(
				'Perché le auto veloci contano',
				'Sicurezza ad alta velocità',
			),
			'min_words'         => 5,
			'attributes_weight' => 3,
			'max_terms'         => 20,
		);

		$client = LocalKeyphraseClientFactory::for( 'it' );
		$dto    = $client->fetch( new KeyphraseRequest( $payload ) );

		$this->assertFalse( $dto->is_filtered() );
		$terms = $dto->terms();
		$this->assertSame( 5, (int) ( $terms['veloc'] ?? 0 ) );
		$this->assertSame( 5, (int) ( $terms['aut'] ?? 0 ) );
		$this->assertSame( 5, (int) ( $terms['sicurezz'] ?? 0 ) );
		$this->assertArrayNotHasKey( 'e', $dto->terms() );

		$this->assertSame(
			array(
				'filtered'      => false,
				'lang_handled'  => 'it',
				'vector_length' => null,
			),
			$dto->metadata()
		);
	}
}
