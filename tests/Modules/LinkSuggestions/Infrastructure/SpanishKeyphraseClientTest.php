<?php
/**
 * Tests for Spanish keyphrase client.
 *
 * @package AirygenTest\Modules\LinkSuggestions
 */

declare(strict_types=1);

namespace AirygenTest\Modules\LinkSuggestions;

use Airygen\Modules\LinkSuggestions\Domain\KeyphraseRequest;
use Airygen\Modules\LinkSuggestions\Infrastructure\LocalKeyphraseClientFactory;
use AirygenTest\BaseTestCase;

/**
 * @coversDefaultClass \Airygen\Modules\LinkSuggestions\Infrastructure\SpanishKeyphraseClient
 */
class SpanishKeyphraseClientTest extends BaseTestCase {

	public function test_extracts_tf_for_sample_payload(): void {
		$payload = array(
			'language'          => 'es',
			'title'             => 'Coches rápidos y seguros',
			'description'       => 'Un artículo corto sobre coches rápidos y cómo conducir con seguridad.',
			'content'           => 'Los coches rápidos llegan antes que otros coches. La seguridad es clave cuando conduces rápido. '
				. 'Estos coches fueron probados y son rápidos y seguros. Muchos conductores quieren saber cómo mejorar la seguridad '
				. 'cuando viajan a alta velocidad. Por eso comparan modelos y revisan que los frenos sean fuertes. También los neumáticos '
				. 'importan: deben agarrar bien en lluvia y nieve. Al final importa que el coche sea rápido pero también seguro y cómodo.',
			'focus_keywords'    => array( 'coche rápido', 'seguridad' ),
			'headings'          => array(
				'Por qué los coches rápidos importan',
				'Seguridad a alta velocidad',
			),
			'min_words'         => 5,
			'attributes_weight' => 3,
			'max_terms'         => 20,
		);

		$client = LocalKeyphraseClientFactory::for( 'es' );
		$dto    = $client->fetch( new KeyphraseRequest( $payload ) );

		$this->assertFalse( $dto->is_filtered() );

		$terms = $dto->terms();
		$this->assertSame( 5, (int) ( $terms['coch'] ?? 0 ) );
		$this->assertSame( 5, (int) ( $terms['rap'] ?? 0 ) );
		$this->assertSame( 5, (int) ( $terms['segur'] ?? 0 ) );
		$this->assertArrayNotHasKey( 'y', $dto->terms() );

		$this->assertSame(
			array(
				'filtered'      => false,
				'lang_handled'  => 'es',
				'vector_length' => null,
			),
			$dto->metadata()
		);
	}
}
