<?php
/**
 * Tests for Portuguese keyphrase client.
 *
 * @package AirygenTest\Modules\LinkSuggestions
 */

declare(strict_types=1);

namespace AirygenTest\Modules\LinkSuggestions;

use Airygen\Modules\LinkSuggestions\Domain\KeyphraseRequest;
use Airygen\Modules\LinkSuggestions\Infrastructure\LocalKeyphraseClientFactory;
use AirygenTest\BaseTestCase;

/**
 * @coversDefaultClass \Airygen\Modules\LinkSuggestions\Infrastructure\PortugueseKeyphraseClient
 */
class PortugueseKeyphraseClientTest extends BaseTestCase {

	public function test_extracts_tf_for_sample_payload(): void {
		$payload = array(
			'language'          => 'pt',
			'title'             => 'Carros rápidos e seguros',
			'description'       => 'Um artigo curto sobre carros rápidos e segurança.',
			'content'           => 'Os carros rápidos chegam antes de outros carros. A segurança é essencial quando se dirige rápido. '
				. 'Esses carros foram testados e são rápidos e seguros. Muitos motoristas querem saber como melhorar a segurança '
				. 'quando viajam em alta velocidade. Por isso comparam modelos e verificam se os freios são fortes. '
				. 'Os pneus também importam: precisam ter boa aderência na chuva e na neve. No fim, o carro deve ser rápido, seguro e confortável.',
			'focus_keywords'    => array( 'carro rápido', 'segurança' ),
			'headings'          => array(
				'Por que carros rápidos importam',
				'Segurança em alta velocidade',
			),
			'min_words'         => 5,
			'attributes_weight' => 3,
			'max_terms'         => 20,
		);

		$client = LocalKeyphraseClientFactory::for( 'pt' );
		$dto    = $client->fetch( new KeyphraseRequest( $payload ) );

		$this->assertFalse( $dto->is_filtered() );
		$terms = $dto->terms();
		$this->assertSame( 5, (int) ( $terms['carr'] ?? 0 ) );
		$this->assertSame( 5, (int) ( $terms['ráp'] ?? 0 ) );
		$this->assertSame( 5, (int) ( $terms['seguranc'] ?? 0 ) );
		$this->assertArrayNotHasKey( 'e', $dto->terms() );

		$this->assertSame(
			array(
				'filtered'      => false,
				'lang_handled'  => 'pt',
				'vector_length' => null,
			),
			$dto->metadata()
		);
	}
}
