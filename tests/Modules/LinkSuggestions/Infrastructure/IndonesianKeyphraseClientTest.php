<?php
/**
 * Tests for Indonesian keyphrase client.
 *
 * @package AirygenTest\Modules\LinkSuggestions
 */

declare(strict_types=1);

namespace AirygenTest\Modules\LinkSuggestions;

use Airygen\Modules\LinkSuggestions\Domain\KeyphraseRequest;
use Airygen\Modules\LinkSuggestions\Infrastructure\LocalKeyphraseClientFactory;
use AirygenTest\BaseTestCase;

/**
 * @coversDefaultClass \Airygen\Modules\LinkSuggestions\Infrastructure\IndonesianKeyphraseClient
 */
class IndonesianKeyphraseClientTest extends BaseTestCase {

	public function test_extracts_tf_for_sample_payload(): void {
		$payload = array(
			'language'          => 'id',
			'title'             => 'Mobil cepat dan aman',
			'description'       => 'Artikel singkat tentang mobil cepat dan keamanan.',
			'content'           => 'Mobil cepat tiba lebih dulu dari mobil lain. Keamanan penting saat berkendara cepat. '
				. 'Mobil ini sudah diuji dan terbukti cepat serta aman. Banyak pengemudi ingin tahu cara meningkatkan keamanan '
				. 'saat melaju dengan kecepatan tinggi. Mereka membandingkan berbagai model dan memeriksa remnya kuat. '
				. 'Ban juga penting: harus punya cengkeraman yang baik saat hujan dan salju. Pada akhirnya, mobil harus cepat tetapi juga aman dan nyaman.',
			'focus_keywords'    => array( 'mobil cepat', 'keamanan' ),
			'headings'          => array(
				'Mengapa mobil cepat penting',
				'Keamanan pada kecepatan tinggi',
			),
			'min_words'         => 5,
			'attributes_weight' => 3,
			'max_terms'         => 20,
		);

		$client = LocalKeyphraseClientFactory::for( 'id' );
		$dto    = $client->fetch( new KeyphraseRequest( $payload ) );

		$this->assertFalse( $dto->is_filtered() );
		$terms = $dto->terms();
		$this->assertSame( 5, (int) ( $terms['cepat'] ?? 0 ) );
		$this->assertSame( 5, (int) ( $terms['mobil'] ?? 0 ) );
		$this->assertSame( 5, (int) ( $terms['aman'] ?? 0 ) );
		$this->assertArrayNotHasKey( 'dan', $dto->terms() );

		$this->assertSame(
			array(
				'filtered'      => false,
				'lang_handled'  => 'id',
				'vector_length' => null,
			),
			$dto->metadata()
		);
	}
}
