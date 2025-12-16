<?php
/**
 * Tests for consolidated output mode post meta handling.
 *
 * @package AirygenTest\Support\Meta
 */

declare(strict_types=1);

namespace AirygenTest\Support\Meta;

use Airygen\Constants;
use Airygen\Support\Meta\OutputModes;
use WP_UnitTestCase;

final class OutputModesTest extends WP_UnitTestCase {

	/**
	 * Ensure output modes are normalized when saved and loaded.
	 *
	 * @return void
	 */
	public function test_save_and_get_normalize_output_modes(): void {
		$post_id = self::factory()->post->create();

		OutputModes::save(
			$post_id,
			array(
				'toc'            => 'manual',
				'faq'            => 'disabled',
				'topicExpansion' => 'invalid',
			)
		);

		$stored = json_decode( (string) get_post_meta( $post_id, Constants::META_OUTPUT_MODES, true ), true );

		$this->assertSame( 'manual', $stored['toc'] ?? null );
		$this->assertSame( 'disabled', $stored['faq'] ?? null );
		$this->assertSame( 'auto', $stored['topicExpansion'] ?? null );
		$this->assertSame( 'manual', OutputModes::get_mode( $post_id, 'toc' ) );
		$this->assertSame( 'disabled', OutputModes::get_mode( $post_id, 'faq' ) );
		$this->assertSame( 'auto', OutputModes::get_mode( $post_id, 'topicExpansion' ) );
	}
}
