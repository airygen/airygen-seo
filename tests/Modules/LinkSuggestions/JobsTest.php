<?php
/**
 * Tests for LinkSuggestions Jobs pipeline.
 *
 * @package AirygenTest\Modules\LinkSuggestions
 */

declare(strict_types=1);

namespace AirygenTest\Modules\LinkSuggestions;

use Airygen\Admin\Modules\Settings as ModuleSettings;
use Airygen\Constants;
use Airygen\Modules\LinkSuggestions\Admin\Settings;
use Airygen\Modules\LinkSuggestions\Persistence\LinkTermsRepository;
use Airygen\Modules\LinkSuggestions\Runtime\Jobs;
use Airygen\Support\Meta\PostData;
use AirygenTest\BaseTestCase;

/**
 * @coversDefaultClass \Airygen\Modules\LinkSuggestions\Runtime\Jobs
 */
class JobsTest extends BaseTestCase {

	protected function setUp(): void {
		parent::setUp();
		ModuleSettings::ensure_exists();
		$modules                    = ModuleSettings::get();
		$modules['linkSuggestions'] = true;
		ModuleSettings::update( $modules );
		Settings::update(
			array(
				'enabled'            => true,
				'allowed_post_types' => array( 'post' ),
			)
		);
	}

	/**
	 * @covers ::handle_recompute
	 */
	public function test_strips_cjk_from_content_title_description_and_headings(): void {
		$content  = str_repeat( 'alpha ', 60 ) . '中文 中文';
		$content .= '<h2>Beta 中文</h2>';

		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Alpha 中文',
				'post_content' => $content,
				'post_status'  => 'publish',
			)
		);

		PostData::save(
			$post_id,
			array(
				'description' => 'Desc 中文',
			)
		);

		Jobs::handle_recompute( $post_id );

		$repository = new LinkTermsRepository();
		$terms      = $repository->get_terms_for_content( $post_id, 'post' );

		$this->assertArrayHasKey( 'alpha', $terms );

		foreach ( array_keys( $terms ) as $term ) {
			$this->assertSame( 0, preg_match( '/[\\p{Han}\\p{Hiragana}\\p{Katakana}\\p{Hangul}]/u', $term ) );
		}
	}

	/**
	 * @covers ::handle_recompute
	 */
	public function test_focus_keywords_and_taxonomies_allow_empty_content_indexing(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => '',
				'post_content' => '',
				'post_status'  => 'publish',
			)
		);

		PostData::save(
			$post_id,
			array(
				'focusKeyphrase' => '中文 中文 中文 中文',
				'focusLongTail'  => 'longtail longtail',
			)
		);

		$tag_id = self::factory()->term->create(
			array(
				'taxonomy' => 'post_tag',
				'name'     => 'tagterm tagterm',
			)
		);
		$cat_id = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'catterm catterm',
			)
		);

		wp_set_post_terms( $post_id, array( $tag_id ), 'post_tag' );
		wp_set_post_terms( $post_id, array( $cat_id ), 'category' );

		$this->assertSame( '中文 中文 中文 中文', PostData::get_field( $post_id, 'focusKeyphrase' ) );
		$this->assertNotEmpty( get_the_terms( $post_id, 'post_tag' ) );
		$this->assertNotEmpty( get_the_terms( $post_id, 'category' ) );

		$reflection = new \ReflectionMethod( Jobs::class, 'extract_focus_keywords' );
		$reflection->setAccessible( true );
		$focus = $reflection->invoke( null, $post_id );
		$this->assertNotEmpty( $focus );
		$this->assertTrue( Settings::get()['enabled'] );
		$this->assertContains( 'post', Settings::get()['allowed_post_types'] );

		Jobs::handle_recompute( $post_id );

		$repository = new LinkTermsRepository();
		$terms      = $repository->get_terms_for_content( $post_id, 'post' );

		$this->assertNotEmpty( get_post_meta( $post_id, Constants::META_KEYPHRASES_INDEXED_AT, true ) );
		$this->assertNotEmpty( $terms );
		$this->assertArrayHasKey( '中文', $terms );
		$this->assertArrayHasKey( 'longtail', $terms );
		$this->assertArrayHasKey( 'tagterm', $terms );
		$this->assertArrayHasKey( 'catterm', $terms );
	}
}
