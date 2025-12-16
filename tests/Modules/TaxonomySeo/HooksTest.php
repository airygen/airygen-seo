<?php
/**
 * Tests for Taxonomy SEO public hooks.
 *
 * @package AirygenTest\Modules\TaxonomySeo
 */

declare(strict_types=1);

namespace AirygenTest\Modules\TaxonomySeo;

use Airygen\Constants;
use Airygen\Modules\TaxonomySeo\Admin\Settings;
use Airygen\Modules\TaxonomySeo\Public\Hooks;
use AirygenTest\BaseTestCase;

/**
 * @covers \Airygen\Modules\TaxonomySeo\Public\Hooks
 */
final class HooksTest extends BaseTestCase {

	/**
	 * Title and head output should use term overrides first.
	 *
	 * @return void
	 */
	public function test_term_overrides_are_used_for_title_and_head(): void {
		Settings::update(
			array(
				'enabled'            => true,
				'enabled_taxonomies' => array( 'category' ),
				'templates'          => array(
					'global'        => array(
						'title'       => '%term_name% %separator% %site_name%',
						'description' => '%term_description%',
					),
					'separator'     => '|',
					'custom_tokens' => array(
						'custom_1' => '',
						'custom_2' => '',
						'custom_3' => '',
					),
				),
			)
		);

		$term_id = self::factory()->term->create(
			array(
				'taxonomy'    => 'category',
				'name'        => 'Taxonomy Test',
				'description' => 'Default taxonomy description',
			)
		);

		update_term_meta( $term_id, Constants::META_TERM_TITLE, 'Override Term Title' );
		update_term_meta( $term_id, Constants::META_TERM_DESCRIPTION, 'Override term description.' );
		update_term_meta( $term_id, Constants::META_TERM_CANONICAL, 'https://example.com/custom-taxonomy' );

		$link = get_term_link( (int) $term_id, 'category' );
		$this->assertIsString( $link );
		$this->go_to( $link );

		$title = Hooks::filter_document_title( 'Fallback Title' );
		$this->assertSame( 'Override Term Title', $title );

		ob_start();
		Hooks::emit_head();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'meta name="description" content="Override term description."', $output );
		$this->assertStringContainsString( 'link rel="canonical" href="https://example.com/custom-taxonomy"', $output );
	}

	/**
	 * Template fallback should be used when term overrides are absent.
	 *
	 * @return void
	 */
	public function test_template_fallback_when_term_overrides_missing(): void {
		Settings::update(
			array(
				'enabled'            => true,
				'enabled_taxonomies' => array( 'category' ),
				'templates'          => array(
					'global'        => array(
						'title'       => '%term_name% %separator% %site_name%',
						'description' => '%term_description%',
					),
					'separator'     => '|',
					'custom_tokens' => array(
						'custom_1' => '',
						'custom_2' => '',
						'custom_3' => '',
					),
				),
			)
		);

		$term_id = self::factory()->term->create(
			array(
				'taxonomy'    => 'category',
				'name'        => 'Template Term',
				'description' => 'Template term description',
			)
		);

		$link = get_term_link( (int) $term_id, 'category' );
		$this->assertIsString( $link );
		$this->go_to( $link );

		$title = Hooks::filter_document_title( 'Fallback Title' );
		$this->assertStringContainsString( 'Template Term', $title );
		$this->assertStringContainsString( get_bloginfo( 'name' ), $title );
	}
}
