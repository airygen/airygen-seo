<?php
/**
 * Tests for the OnPage SEO head meta builder.
 *
 * @package AirygenTest\Modules\OnPageSeo\Domain
 */

declare(strict_types=1);

namespace AirygenTest\Modules\OnPageSeo\Domain;

use Airygen\Modules\OnPageSeo\Domain\Service\BuildHeadMeta;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Airygen\Modules\OnPageSeo\Domain\Service\BuildHeadMeta
 */
class BuildHeadMetaTest extends TestCase {

	/**
	 * Meta fields should win over post fallbacks.
	 *
	 * @return void
	 */
	public function test_prioritizes_meta_fields(): void {
		$result = BuildHeadMeta::for_post(
			array(
				'post_title'       => 'Default Title',
				'post_excerpt'     => 'Default excerpt',
				'permalink'        => 'https://example.com/post',
				'meta_title'       => 'Custom Title',
				'meta_description' => 'Custom description',
				'canonical'        => 'https://example.com/custom',
				'robots'           => 'noindex,follow',
			)
		)->to_array();

		$this->assertSame(
			array(
				'title'       => 'Custom Title',
				'description' => 'Custom description',
				'canonical'   => 'https://example.com/custom',
				'robots'      => 'noindex,follow',
			),
			$result
		);
	}

	/**
	 * Post level defaults should be used when no meta provided.
	 *
	 * @return void
	 */
	public function test_falls_back_to_post_fields(): void {
		$result = BuildHeadMeta::for_post(
			array(
				'post_title'   => 'Fallback Title',
				'post_excerpt' => 'Fallback description',
				'permalink'    => 'https://example.com/post',
			)
		);

		$this->assertSame( 'Fallback Title', $result->get_title() );
		$this->assertSame( 'Fallback description', $result->get_description() );
		$this->assertSame( 'https://example.com/post', $result->get_canonical() );
		$this->assertNull( $result->get_robots() );
	}

	/**
	 * Robots directives matching the default should be suppressed.
	 *
	 * @return void
	 */
	public function test_discards_default_robots_directive(): void {
		$result = BuildHeadMeta::for_post(
			array(
				'robots' => '  Index ,  Follow ',
			)
		);

		$this->assertNull( $result->get_robots() );
	}

	/**
	 * Templates should populate title and description when meta is absent.
	 *
	 * @return void
	 */
	public function test_applies_templates_when_meta_missing(): void {
		$result = BuildHeadMeta::for_post(
			array(
				'post_title'   => 'Hello World',
				'post_excerpt' => 'Sample excerpt text.',
				'post_type'    => 'post',
				'site_name'    => 'Airygen Site',
				'templates'    => array(
					'global'     => array(
						'title'       => '%post_title% %separator% %site_name%',
						'description' => '%post_excerpt% - %site_name%',
					),
					'post_types' => array(
						'post' => array(
							'title'       => 'Blog: %post_title%',
							'description' => '',
						),
					),
				),
				'separator'    => '|',
			)
		);

		$this->assertSame( 'Blog: Hello World', $result->get_title() );
		$this->assertSame( 'Sample excerpt text. - Airygen Site', $result->get_description() );
	}
}
