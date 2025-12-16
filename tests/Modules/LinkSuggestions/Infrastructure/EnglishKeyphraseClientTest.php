<?php
/**
 * Tests for English keyphrase client end-to-end TF extraction.
 *
 * @package AirygenTest\Modules\LinkSuggestions
 */

declare(strict_types=1);

namespace AirygenTest\Modules\LinkSuggestions;

use Airygen\Modules\LinkSuggestions\Domain\KeyphraseRequest;
use Airygen\Modules\LinkSuggestions\Infrastructure\LocalKeyphraseClientFactory;
use AirygenTest\BaseTestCase;

/**
 * @coversDefaultClass \Airygen\Modules\LinkSuggestions\Infrastructure\LocalKeyphraseClient
 */
class EnglishKeyphraseClientTest extends BaseTestCase {

	public function test_extracts_tf_for_sample_payload(): void {
		$payload = array(
			'language'          => 'en',
			'title'             => 'Rust keyphrase extraction demo',
			'description'       => 'A short article describing how the keyphrase API works in a content platform.',
			'content'           => 'The keyphrase API processes an article, removes URLs and emails, and then counts how often prominent words appear. It boosts focus keywords, headings, titles, and descriptions to make related terms stand out. For example, if your content talks about Rust performance, memory safety, and developer productivity, the algorithm will stem the words and collapse variations so that rust and Rust both count. It also ignores stopwords and filters tokens that only contain special characters. After the words are tallied, the service sorts by frequency, applies the minimum threshold, and returns the top weighted terms. This makes it easy to suggest internal links, related posts, or SEO hints without scanning the whole document manually.',
			'focus_keywords'    => array( 'rust', 'performance', 'memory safety' ),
			'headings'          => array(
				'Why memory safety matters',
				'Boosting developer productivity with Rust',
			),
			'min_words'         => 50,
			'attributes_weight' => 3,
			'max_terms'         => 50,
		);

		$client = LocalKeyphraseClientFactory::for( 'en' );
		$dto    = $client->fetch( new KeyphraseRequest( $payload ) );

		$this->assertFalse( $dto->is_filtered() );
		$terms = $dto->terms();
		$this->assertSame( 5, (int) ( $terms['rust'] ?? 0 ) );
		$this->assertSame( 5, (int) ( $terms['perform'] ?? 0 ) );
		$this->assertSame( 5, (int) ( $terms['memori'] ?? 0 ) );
		$this->assertSame( 5, (int) ( $terms['safeti'] ?? 0 ) );

		$this->assertSame(
			array(
				'filtered'      => false,
				'lang_handled'  => 'en',
				'vector_length' => null,
			),
			$dto->metadata()
		);
	}
}
