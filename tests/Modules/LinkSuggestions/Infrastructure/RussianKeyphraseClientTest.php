<?php
/**
 * Tests for Russian keyphrase client.
 *
 * @package AirygenTest\Modules\LinkSuggestions
 */

declare(strict_types=1);

namespace AirygenTest\Modules\LinkSuggestions;

use Airygen\Modules\LinkSuggestions\Domain\KeyphraseRequest;
use Airygen\Modules\LinkSuggestions\Infrastructure\LocalKeyphraseClientFactory;
use AirygenTest\BaseTestCase;

/**
 * @coversDefaultClass \Airygen\Modules\LinkSuggestions\Infrastructure\RussianKeyphraseClient
 */
class RussianKeyphraseClientTest extends BaseTestCase {

	public function test_extracts_tf_for_sample_payload(): void {
		$payload = array(
			'language'          => 'ru',
			'title'             => 'Быстрые и безопасные автомобили',
			'description'       => 'Короткая статья о быстрых автомобилях и безопасности.',
			'content'           => 'Быстрые автомобили приезжают раньше других. Безопасность важна, когда едешь быстро. '
				. 'Эти автомобили тестировали, они быстрые и безопасные. Многие водители хотят знать, как повысить безопасность '
				. 'на высокой скорости. Поэтому они сравнивают модели и проверяют, что тормоза достаточно сильные. '
				. 'Также важны шины: им нужна хорошая сцепка на дожде и снегу. В итоге машина должна быть быстрой, но и безопасной и комфортной.',
			'focus_keywords'    => array( 'быстрый автомобиль', 'безопасность' ),
			'headings'          => array(
				'Почему важны быстрые автомобили',
				'Безопасность на высокой скорости',
			),
			'min_words'         => 5,
			'attributes_weight' => 3,
			'max_terms'         => 20,
		);

		$client = LocalKeyphraseClientFactory::for( 'ru' );
		$dto    = $client->fetch( new KeyphraseRequest( $payload ) );

		$this->assertFalse( $dto->is_filtered() );
		$terms = $dto->terms();
		$this->assertSame( 5, (int) ( $terms['быстр'] ?? 0 ) );
		$this->assertGreaterThanOrEqual( 4, (int) ( $terms['автомоб'] ?? 0 ) );
		$this->assertSame( 5, (int) ( $terms['безопасн'] ?? 0 ) );
		$this->assertArrayHasKey( 'автомобил', $terms );
		$this->assertArrayNotHasKey( 'и', $dto->terms() );

		$this->assertSame(
			array(
				'filtered'      => false,
				'lang_handled'  => 'ru',
				'vector_length' => null,
			),
			$dto->metadata()
		);
	}
}
