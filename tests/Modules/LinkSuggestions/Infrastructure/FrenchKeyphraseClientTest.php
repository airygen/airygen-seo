<?php
/**
 * Tests for French keyphrase client.
 *
 * @package AirygenTest\Modules\LinkSuggestions
 */

declare(strict_types=1);

namespace AirygenTest\Modules\LinkSuggestions;

use Airygen\Modules\LinkSuggestions\Domain\KeyphraseRequest;
use Airygen\Modules\LinkSuggestions\Infrastructure\LocalKeyphraseClientFactory;
use AirygenTest\BaseTestCase;

/**
 * @coversDefaultClass \Airygen\Modules\LinkSuggestions\Infrastructure\FrenchKeyphraseClient
 */
class FrenchKeyphraseClientTest extends BaseTestCase {

	public function test_extracts_tf_for_sample_payload(): void {
		$payload = array(
			'language'          => 'fr',
			'title'             => 'Voitures rapides et sûres',
			'description'       => 'Un court article sur les voitures rapides et la sécurité routière.',
			'content'           => 'Les voitures rapides arrivent plus vite que les autres voitures. La sécurité est essentielle quand on conduit rapidement. '
				. 'Ces voitures ont été testées et sont rapides et sûres. De nombreux conducteurs veulent savoir comment améliorer la sécurité '
				. 'lorsqu\'ils roulent à grande vitesse. Ils comparent donc plusieurs modèles et vérifient que les freins sont solides. '
				. 'Les pneus comptent aussi : ils doivent bien adhérer sous la pluie et la neige. Au final, il faut que la voiture soit rapide '
				. 'mais aussi sûre et confortable pour arriver en toute sécurité.',
			'focus_keywords'    => array( 'voiture rapide', 'sécurité' ),
			'headings'          => array(
				'Pourquoi les voitures rapides comptent',
				'Sécurité à grande vitesse',
			),
			'min_words'         => 5,
			'attributes_weight' => 3,
			'max_terms'         => 20,
		);

		$client = LocalKeyphraseClientFactory::for( 'fr' );
		$dto    = $client->fetch( new KeyphraseRequest( $payload ) );

		$this->assertFalse( $dto->is_filtered() );
		$terms = $dto->terms();
		$this->assertSame( 5, (int) ( $terms['voitur'] ?? 0 ) );
		$this->assertSame( 5, (int) ( $terms['rapid'] ?? 0 ) );
		$this->assertSame( 5, (int) ( $terms['sécur'] ?? 0 ) );
		$this->assertArrayNotHasKey( 'et', $dto->terms() );

		$this->assertSame(
			array(
				'filtered'      => false,
				'lang_handled'  => 'fr',
				'vector_length' => null,
			),
			$dto->metadata()
		);
	}
}
