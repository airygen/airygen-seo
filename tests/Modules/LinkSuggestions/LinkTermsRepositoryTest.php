<?php
/**
 * Tests for LinkTermsRepository.
 *
 * @package AirygenTest\Modules\LinkSuggestions
 */

declare(strict_types=1);

namespace AirygenTest\Modules\LinkSuggestions;

use Airygen\Modules\LinkSuggestions\Persistence\LinkTermsRepository;
use AirygenTest\BaseTestCase;
use AirygenTest\Support\DatabaseHelpers;

/**
 * @coversDefaultClass \Airygen\Modules\LinkSuggestions\Persistence\LinkTermsRepository
 */
class LinkTermsRepositoryTest extends BaseTestCase {

	protected function setUp(): void {
		parent::setUp();
		DatabaseHelpers::truncate_custom_tables();
	}

	/**
	 * @covers ::save_terms
	 * @covers ::get_terms_for_content
	 * @covers ::get_df_for_stems
	 * @covers ::total_documents
	 */
	public function test_save_terms_persists_and_updates_df(): void {
		$repo = new LinkTermsRepository();

		$repo->save_terms(
			10,
			'post',
			array(
				'run' => 2.0,
				'seo' => 1.0,
			)
		);

		$this->assertSame(
			array(
				'run' => 2.0,
				'seo' => 1.0,
			),
			$repo->get_terms_for_content( 10, 'post' )
		);

		$this->assertSame(
			1,
			$repo->total_documents()
		);

		$this->assertSame(
			array(
				'run' => 1,
				'seo' => 1,
			),
			$repo->get_df_for_stems( array( 'run', 'seo' ) )
		);
	}

	/**
	 * @covers ::save_terms
	 * @covers ::purge_content
	 */
	public function test_save_terms_updates_df_on_diff(): void {
		$repo = new LinkTermsRepository();

		// Seed with run/seo.
		$repo->save_terms(
			10,
			'post',
			array(
				'run' => 2.0,
				'seo' => 1.0,
			)
		);

		// Replace with seo only.
		$repo->save_terms(
			10,
			'post',
			array(
				'seo' => 3.0,
			)
		);

		$this->assertSame(
			array(
				'seo' => 3.0,
			),
			$repo->get_terms_for_content( 10, 'post' )
		);

		$df = $repo->get_df_for_stems( array( 'run', 'seo' ) );

		$this->assertSame( 0, $df['run'] ?? 0 );
		$this->assertSame( 1, $df['seo'] ?? 0 );

		// Purge should decrement df.
		$repo->purge_content( 10, 'post' );
		$df_after = $repo->get_df_for_stems( array( 'seo' ) );
		$this->assertSame( 0, $df_after['seo'] ?? 0 );
	}

	/**
	 * @covers ::find_candidate_ids_by_stems
	 */
	public function test_find_candidate_ids_filters_status_and_stems(): void {
		$repo = new LinkTermsRepository();

		$published = self::factory()->post->create(
			array(
				'post_status' => 'publish',
			)
		);

		$draft = self::factory()->post->create(
			array(
				'post_status' => 'draft',
			)
		);

		$repo->save_terms(
			$published,
			'post',
			array(
				'run' => 1.0,
			)
		);

		$repo->save_terms(
			$draft,
			'post',
			array(
				'run' => 1.0,
			)
		);

		$candidates = $repo->find_candidate_ids_by_stems(
			array( 'run' ),
			array( 'post' ),
			array( 'publish' ),
			10
		);

		$this->assertContains( $published, $candidates );
		$this->assertNotContains( $draft, $candidates );

		$missing = $repo->find_candidate_ids_by_stems(
			array( 'missing' ),
			array( 'post' ),
			array( 'publish' ),
			10
		);

		$this->assertSame( array(), $missing );
	}
}
