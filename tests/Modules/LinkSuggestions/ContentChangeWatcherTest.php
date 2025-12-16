<?php
/**
 * Tests for ContentChangeWatcher.
 *
 * @package AirygenTest\Modules\LinkSuggestions
 */

declare(strict_types=1);

namespace AirygenTest\Modules\LinkSuggestions;

use Airygen\Constants;
use Airygen\Modules\LinkSuggestions\Admin\Settings;
use Airygen\Modules\LinkSuggestions\Admin\ContentChangeWatcher;
use AirygenTest\BaseTestCase;

/**
 * @coversDefaultClass \Airygen\Modules\LinkSuggestions\Admin\ContentChangeWatcher
 */
class ContentChangeWatcherTest extends BaseTestCase {

	protected function setUp(): void {
		parent::setUp();
		Settings::update(
			array(
				'enabled' => true,
			)
		);
	}

	/**
	 * @covers ::maybe_handle_save
	 */
	public function test_triggers_when_first_index(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => 'short',
				'post_status'  => 'publish',
			)
		);

		$triggered = false;
		add_action(
			'airygen_link_suggestions_recompute_term_frequency',
			static function () use ( &$triggered ) {
				$triggered = true;
			}
		);

		ContentChangeWatcher::maybe_handle_save( $post_id, get_post( $post_id ), true );

		$this->assertTrue( $triggered );
	}

	/**
	 * @covers ::maybe_handle_save
	 */
	public function test_triggers_when_modified_after_index(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => str_repeat( 'A', 120 ),
				'post_status'  => 'publish',
			)
		);

		update_post_meta( $post_id, Constants::META_KEYPHRASES_INDEXED_AT, 0 );

		$triggered = false;
		add_action(
			'airygen_link_suggestions_recompute_term_frequency',
			static function () use ( &$triggered ) {
				$triggered = true;
			}
		);

		ContentChangeWatcher::maybe_handle_save( $post_id, get_post( $post_id ), true );

		$this->assertTrue( $triggered );
	}

	/**
	 * @covers ::maybe_handle_save
	 */
	public function test_skips_when_not_modified_after_index(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => str_repeat( 'B', 130 ),
				'post_status'  => 'publish',
			)
		);

		update_post_meta( $post_id, Constants::META_KEYPHRASES_INDEXED_AT, 9_999_999_999 );

		$triggered = false;
		add_action(
			'airygen_link_suggestions_recompute_term_frequency',
			static function () use ( &$triggered ) {
				$triggered = true;
			}
		);

		ContentChangeWatcher::maybe_handle_save( $post_id, get_post( $post_id ), true );

		$this->assertFalse( $triggered );
	}
}
