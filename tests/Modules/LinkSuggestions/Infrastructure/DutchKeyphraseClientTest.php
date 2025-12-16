<?php
/**
 * Tests for Dutch keyphrase client.
 *
 * @package AirygenTest\Modules\LinkSuggestions
 */

declare(strict_types=1);

namespace AirygenTest\Modules\LinkSuggestions;

use Airygen\Modules\LinkSuggestions\Domain\KeyphraseRequest;
use Airygen\Modules\LinkSuggestions\Infrastructure\LocalKeyphraseClientFactory;
use AirygenTest\BaseTestCase;

/**
 * @coversDefaultClass \Airygen\Modules\LinkSuggestions\Infrastructure\DutchKeyphraseClient
 */
class DutchKeyphraseClientTest extends BaseTestCase {

	public function test_extracts_tf_for_sample_payload(): void {
		$payload = array(
			'language'          => 'nl',
			'title'             => 'Snelle en veilige auto\'s',
			'description'       => 'Een kort artikel over snelle auto’s en veiligheid.',
			'content'           => 'Snelle auto\'s komen eerder aan dan andere auto\'s. Veiligheid is cruciaal wanneer je snel rijdt. '
				. 'Deze auto\'s zijn getest en zijn snel en veilig. Veel bestuurders willen weten hoe ze de veiligheid kunnen verbeteren '
				. 'bij hoge snelheid. Daarom vergelijken ze modellen en controleren ze of de remmen sterk genoeg zijn. '
				. 'Banden zijn ook belangrijk: ze moeten goed grip hebben bij regen en sneeuw. Uiteindelijk moet de auto snel, veilig en comfortabel zijn.',
			'focus_keywords'    => array( 'snelle auto', 'veiligheid' ),
			'headings'          => array(
				'Waarom snelle auto\'s belangrijk zijn',
				'Veiligheid bij hoge snelheid',
			),
			'min_words'         => 5,
			'attributes_weight' => 3,
			'max_terms'         => 20,
		);

		$client = LocalKeyphraseClientFactory::for( 'nl' );
		$dto    = $client->fetch( new KeyphraseRequest( $payload ) );

		$this->assertFalse( $dto->is_filtered() );
		$terms = $dto->terms();
		$this->assertSame( 5, (int) ( $terms['snell'] ?? 0 ) );
		$this->assertSame( 5, (int) ( $terms['auto'] ?? 0 ) );
		$this->assertSame( 5, (int) ( $terms['veilig'] ?? 0 ) );
		$this->assertArrayHasKey( 'autos', $terms );
		$this->assertArrayNotHasKey( 'en', $dto->terms() );

		$this->assertSame(
			array(
				'filtered'      => false,
				'lang_handled'  => 'nl',
				'vector_length' => null,
			),
			$dto->metadata()
		);
	}
}
