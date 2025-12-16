<?php
/**
 * Tests for the OnPage SEO settings store.
 *
 * @package AirygenTest\Modules\OnPageSeo\Admin
 */

declare(strict_types=1);

namespace AirygenTest\Modules\OnPageSeo\Admin;

use Airygen\Modules\OnPageSeo\Admin\Settings;
use AirygenTest\BaseTestCase;

/**
 * @covers \Airygen\Modules\OnPageSeo\Admin\Settings
 */
final class SettingsTest extends BaseTestCase {

	/**
	 * Ensure option is reset between tests.
	 */
	public function set_up(): void {
		parent::set_up();
		delete_option( 'airygen_onpage' );
	}

	/**
	 * Clean up option after each test.
	 */
	public function tear_down(): void {
		delete_option( 'airygen_onpage' );
		parent::tear_down();
	}

	/**
	 * ensure_exists should seed defaults that match the domain contract.
	 *
	 * @return void
	 */
	public function test_ensure_exists_seeds_defaults(): void {
		Settings::ensure_exists();

		$config = Settings::get();

		$this->assertTrue( $config['output']['title'] );
		$this->assertTrue( $config['output']['description'] );
		$this->assertTrue( $config['output']['canonical'] );
		$this->assertTrue( $config['output']['robots'] );

		$this->assertSame( '%post_title% %separator% %site_name%', $config['templates']['global']['title'] );
		$this->assertSame( '%post_excerpt%', $config['templates']['global']['description'] );
		$this->assertSame( '–', $config['templates']['separator'] );
		$this->assertSame( array(), $config['templates']['post_types'] );
	}

	/**
	 * update should sanitize payloads and discard invalid or empty values.
	 *
	 * @return void
	 */
	public function test_update_sanitizes_payload(): void {
		$payload = array(
			'output'    => array(
				'title'     => false,
				'canonical' => false,
			),
			'templates' => array(
				'global'     => array(
					'title'       => '  %post_title% + extra  ',
					'description' => str_repeat( 'd', 250 ),
				),
				'separator'  => '//Airygen!!',
				'post_types' => array(
					'post'    => array(
						'title'       => '%site_name% • %post_title%',
						'description' => '%post_excerpt%',
					),
					'invalid' => array(
						'title' => 'Ignore Me',
					),
				),
			),
		);

		Settings::update( $payload );

		$config = Settings::get();

		$this->assertFalse( $config['output']['title'] );
		$this->assertFalse( $config['output']['canonical'] );
		$this->assertTrue( $config['output']['description'] );
		$this->assertTrue( $config['output']['robots'] );

		$this->assertSame( '%post_title% + extra', $config['templates']['global']['title'] );
		$this->assertSame( 180, mb_strlen( $config['templates']['global']['description'] ) );
		$this->assertSame( '//Airygen!', $config['templates']['separator'] );

		$this->assertArrayHasKey( 'post', $config['templates']['post_types'] );
		$this->assertArrayNotHasKey( 'invalid', $config['templates']['post_types'] );
		$this->assertSame(
			'%site_name% • %post_title%',
			$config['templates']['post_types']['post']['title']
		);
	}

	/**
	 * Empty template separator values should fall back to defaults.
	 *
	 * @return void
	 */
	public function test_update_rejects_empty_separator(): void {
		Settings::update(
			array(
				'templates' => array(
					'separator' => '',
				),
			)
		);

		$config = Settings::get();

		$this->assertSame( '–', $config['templates']['separator'] );
	}
}
